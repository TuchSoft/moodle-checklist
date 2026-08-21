<?php

namespace Tuchsoft\MoodleChecklist\Check;

use Exception;
use PHPMD\PHPMD;
use PHPMD\Renderer\JSONRenderer;
use PHPMD\Report as PhpmdReport;
use PHPMD\RuleSetFactory;
use PHPMD\Writer\StreamWriter;
use Tuchsoft\MoodleChecklist\Check\Subcheck\GetAllFile;

class PhpMessDetectorCheck extends AbstractMoodleCiCheck
{
    use GetAllFile;

    protected function execute(): void
    {

            $files = $this->getAllFile(ext: ['php']);
            if (empty($files)) {
                return;
            }


            $rules = realpath(__DIR__ . '/../../vendor/moodlehq/moodle-plugin-ci/res/config/phpmd.xml');
            if ($rules === false || !file_exists($rules)) {
                $this->runtimeError('PHPMD ruleset not found. Expected at: ' . __DIR__ . '/../../vendor/moodlehq/moodle-plugin-ci/res/config/phpmd.xml');
                return;
            }

            $stream = fopen('php://memory', 'r+');
            if ($stream === false) {
                $this->runtimeError('Failed to open memory stream for PHPMD');
                return;
            }

            $renderer = new JSONRenderer();
            $renderer->setWriter(new StreamWriter($stream));

            $ruleSetFactory = new RuleSetFactory();
            $ruleSetFactory->setMinimumPriority(5); // Process all rules regardless of priority.

            try {
                $ruleSets = $ruleSetFactory->createRuleSets($rules);

                $messDetector = new PHPMD();
                $messDetector->processFiles(
                    implode(',', $files),
                    [], // Ignored paths are handled by GetAllFile trait.
                    [$renderer],
                    $ruleSets,
                    new PhpmdReport()
                );

                rewind($stream);
                $jsonOutput = stream_get_contents($stream);
                fclose($stream);
            } catch (Throwable $e) {
                if (is_resource($stream)) {
                    fclose($stream);
                }
                $this->runtimeError('Failed to run PHPMD: ' . $e->getMessage());
                return;
            }

            $result = json_decode($jsonOutput, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->runtimeError('Failed to decode PHPMD JSON output: ' . json_last_error_msg() . '. Output was: ' . $jsonOutput);
                return;
            }

            if (empty($result['files'])) {
                return;
            }

            foreach ($result['files'] as $fileReport) {
                $filePath = $fileReport['file'];
                foreach ($fileReport['violations'] as $violation) {
                    $message = $violation['description'];
                    $line = $violation['beginLine'];
                    $rule = $violation['rule'];
                    $priority = $violation['priority'];

                    // PHPMD priorities: 1 (high) to 5 (low).
                    if ($priority <= 2) {
                        $this->addError($rule, $message, $filePath, $line);
                    } elseif ($priority <= 4) {
                        $this->addWarning($rule, $message, $filePath, $line);
                    } else {
                        $this->addTip($rule, $message, $filePath, $line);
                    }
                }
            }
        }

}