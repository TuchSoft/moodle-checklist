<?php

namespace Tuchsoft\MoodleChecklist\Process;

use Symfony\Component\Process\Process;
use Tuchsoft\IssueReporter\Issue;

class MoodleCISavepointProcess extends AbstractIssuesProcess
{
    private const SCRIPT_SOURCE_PATH = __DIR__.'/../../vendor/moodlehq/moodle-local_ci/check_upgrade_savepoints/check_upgrade_savepoints.php';

    public function __construct(string $pluginRoot)
    {
        // The command will be executed in the plugin's root directory.
        parent::__construct(rtrim($pluginRoot, '/'));
    }

    protected function getCommand(): array
    {
        return ['php'];
    }

    protected function getProcess(array $command, ?float $timeout): Process
    {
        $process = parent::getProcess($command, $timeout);
        $process->setInput(file_get_contents(self::SCRIPT_SOURCE_PATH));

        return $process;
    }

    public function execute(?float $timeout = 180.0): bool
    {
        if (!file_exists(self::SCRIPT_SOURCE_PATH)) {
            $this->error = 'Could not find check_upgrade_savepoints.php script at: ' . self::SCRIPT_SOURCE_PATH . '. Ensure moodlehq/moodle-local_ci is installed.';
            return false;
        }

        return parent::execute($timeout);
    }

    /**
     * Parses the raw text output from the script into Issue objects.
     */
    protected function parseOutput(): bool
    {
        $output = $this->getStdout();
        if (empty($output)) {
            $this->messages = [];
            return true;
        }

        $lines = explode("\n", $output);

        foreach ($lines as $line) {
            $line = trim(ltrim($line, " \n\r\t\v\0+-"));

            if (!empty($line) && preg_match('/^(ERROR|WARN|NOTE):\s(.*)/', $line, $matches)) {
                $this->messages[$matches[1]][] = $matches[2];
            }
        }

        return true;
    }

    /**
     * Gets the parsed issues from the process output.
     *
     * @return Issue[]
     */
    public function getIssues($code): array
    {
        $issues = [];
       foreach ($this->messages as $severity => $messages) {
           foreach ($messages as $message) {
               $severity = match($severity) {
                   'ERROR' => Issue::SEVERITY_ERROR,
                   'WARN' => Issue::SEVERITY_WARNING,
                   'NOTE' => Issue::SEVERITY_TIP
               };
               $issues[] = new Issue(
                   $code,
                   $severity,
                   $message,
                   'db/upgrade.php',
               );
           }

       }

       return $issues;
    }
}
