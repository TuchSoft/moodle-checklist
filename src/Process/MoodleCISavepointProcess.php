<?php

namespace Tuchsoft\MoodleChecklist\Process;

use Tuchsoft\MoodleChecklist\Report\Issue;
use Tuchsoft\MoodleChecklist\Report\Report;

class MoodleCISavepointProcess extends AbstractProcess
{
    private const string SCRIPT_NAME = 'check_upgrade_savepoints.php';
    private const string SCRIPT_SOURCE_PATH = __DIR__.'/../../xvendor/moodle-local_ci/check_upgrade_savepoints/check_upgrade_savepoints.php';

    private string $pluginRoot;
    private array $messages = [];


    public function __construct(string $pluginRoot)
    {
        $this->pluginRoot = rtrim($pluginRoot, '/');
        // The command will be executed in the plugin's root directory.
        parent::__construct($this->pluginRoot);
    }


    protected function getCommand(): array
    {
        return [
            'php',
            self::SCRIPT_NAME,
        ];
    }


    public function execute(?float $timeout = 60.0): bool
    {
        // This tool is expected to be run from the Moodle project root.
        if (!file_exists(self::SCRIPT_SOURCE_PATH)) {
            $this->error = 'Could not find check_upgrade_savepoints.php script at: ' . self::SCRIPT_SOURCE_PATH;
            return false;
        }

        $destinationScriptPath = $this->pluginRoot . '/' . self::SCRIPT_NAME;

        try {
            // 1. Copy the script to the plugin root.
            if (!copy(self::SCRIPT_SOURCE_PATH, $destinationScriptPath)) {
                $this->error = "Failed to copy check_upgrade_savepoints.php to plugin root: {$this->pluginRoot}";
                return false;
            }

            // 2. Execute the script using the parent's method.
            // The CWD is set to pluginRoot via the constructor.
            if (!parent::execute($timeout)) {
                // Error is already set by the parent class.
                return false;
            }

            // 3. Parse the output from stdout.
            $this->parseOutput();

        } finally {
            // 4. Delete the copied script, regardless of success or failure.
            if (file_exists($destinationScriptPath)) {
                unlink($destinationScriptPath);
            }
        }

        return true;
    }

    /**
     * Parses the raw text output from the script into Issue objects.
     */
    private function parseOutput(): void
    {
        $output = $this->getStdout();
        if (empty($output)) {
            $this->messages = [];
            return;
        }

        $lines = explode("\n", $output);

        foreach ($lines as $line) {
            $line = trim(ltrim($line, " \n\r\t\v\0+-"));

            if (!empty($line) && preg_match('/^(ERROR|WARN|NOTE):\s(.*)/', $line, $matches)) {
                $this->messages[$matches[1]][] = $matches[2];
            }
        }



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
                   'ERROR' => Report::SEVERITY_ERROR,
                   'WARN' => Report::SEVERITY_WARNING,
                   'NOTE' => Report::SEVERITY_TIP
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