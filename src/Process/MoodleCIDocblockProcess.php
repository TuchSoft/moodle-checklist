<?php

namespace Tuchsoft\MoodleChecklist\Process;

use Exception;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SimpleXMLElement;
use Tuchsoft\IssueReporter\Issue;

/**
 * Runs the moodle-local_moodlecheck tool to check for DocBlock issues.
 *
 * This process involves several steps:
 * 1. Temporarily install the 'local_moodlecheck' plugin.
 * 2. Run the checker script against the target plugin.
 * 3. Parse the XML output.
 * 4. Uninstall the 'local_moodlecheck' plugin to clean up.
 */
class MoodleCIDocblockProcess extends AbstractIssuesProcess
{
    private const PLUGIN_SOURCE_DIR = __DIR__ . '/../../vendor/moodlehq/moodle-local_moodlecheck';

    private string $moodleRoot;
    private string $moodleDirroot;
    private string $pluginRoot;

    /**
     * @param string $moodleRoot The absolute path to the Moodle project's root directory.
     * @param string $pluginRoot The absolute path to the plugin's root directory to be checked.
     */
    public function __construct(string $moodleRoot, string $pluginRoot)
    {
        $this->moodleRoot = rtrim($moodleRoot, '/');
        $this->moodleDirroot = $this->resolveMoodleDirroot($this->moodleRoot);
        $this->pluginRoot = rtrim($pluginRoot, '/');
        // The command will be executed in the Moodle dirroot directory (project root or public/).
        parent::__construct($this->moodleDirroot);
    }

    /**
     * Returns the actual Moodle dirroot.
     *
     * In Moodle 5.1+ the web docroot is nested inside a public/ directory, but
     * $CFG->dirroot points at that public directory. Local plugins and CLI scripts
     * must be resolved relative to the dirroot, not the project root.
     */
    private function resolveMoodleDirroot(string $moodleRoot): string
    {
        $publicDirroot = $moodleRoot . '/public';
        if (is_dir($publicDirroot) && is_file($publicDirroot . '/lib/setup.php')) {
            return $publicDirroot;
        }
        return $moodleRoot;
    }

    /**
     * Defines the command to run the moodlecheck script.
     *
     * @return array<string>
     */
    protected function getCommand(): array
    {
        return [
            'php',
            './local/moodlecheck/cli/moodlecheck.php',
            '-p=' . $this->pluginRoot,
        ];
    }

    /**
     * Executes the entire process of setting up, running, and cleaning up the check.
     *
     * @param float|null $timeout
     * @return bool
     */
    public function execute(?float $timeout = 300.0): bool
    {
        $pluginDestDir =  "{$this->moodleDirroot}/local/moodlecheck";

        if (!is_dir(self::PLUGIN_SOURCE_DIR)) {
            $this->error = 'Could not find moodlecheck plugin source at: ' . self::PLUGIN_SOURCE_DIR;
            return false;
        }
        if (!class_exists('SimpleXMLElement')) {
            $this->error = "The 'SimpleXML' PHP extension is required to parse the output.";
            return false;
        }

        try {
            if (!is_dir($pluginDestDir)) {
                // 1. Copy the plugin directory.
                if (!$this->recursiveCopy(self::PLUGIN_SOURCE_DIR, $pluginDestDir)) {
                    $this->error = "Failed to copy moodlecheck plugin to {$pluginDestDir}";
                    return false;
                }

                // 2. Install the plugin by running Moodle upgrade.
                $upgradeProcess = new MoodleUpgradeProcess($this->moodleRoot);
                if (!$upgradeProcess->execute()) {
                    $this->error = 'Failed to install moodlecheck plugin. ' . $upgradeProcess->getError();
                    return false;
                }
            }

            // 3. Execute the check script.
            if (!parent::execute($timeout)) {
                return false;
            }


        } finally {
            // 5. Cleanup: remove files and run upgrade to uninstall.
            //FIXME: add settings
            if (false) {
                if (is_dir($pluginDestDir)) {
                    $this->recursiveDelete($pluginDestDir);
                }
                $uninstallProcess = new MoodleUpgradeProcess($this->moodleRoot);
                $uninstallProcess->execute(); // Run upgrade to finalize uninstallation.
            }
        }

        return empty($this->error);
    }

    /**
     * Parses the XML output from the script into Issue objects.
     */
    protected function parseOutput(): bool
    {
        $output = $this->getStdout();
        if (empty(trim($output))) {
            $this->issues = [];
            return true;
        }

        $xmlStart = strpos($output, '<?xml');
        if ($xmlStart === false) {
            $this->error = 'No XML output found from moodlecheck script. Stderr: ' . $this->getStderr();
            return false;
        }
        $xmlString = substr($output, $xmlStart);

        try {
            $xml = new SimpleXMLElement($xmlString);
        } catch (Exception $e) {
            $this->error = 'Failed to parse XML output from moodlecheck: ' . $e->getMessage();
            return false;
        }

        foreach ($xml->file as $fileElement) {
            foreach ($fileElement->error as $errorElement) {
                $this->issues[] = new Issue(
                    (string) $errorElement['source'],
                    Issue::SEVERITY_ERROR,
                    (string) $errorElement['message'],
                    (string) $fileElement['name'],
                    (int) $errorElement['line']
                );
            }
        }
        return true;
    }

    /**
     * Gets the parsed issues from the process output.
     *
     * @param string $code A code to prepend to the issue's specific code.
     * @return Issue[]
     */
    public function getIssues(?string $code): array
    {
        if (!$code) {
            throw new \Exception("Parameter 'code' must be defined");
        }
        foreach ($this->issues as $issue) {
            $issue->addCode($code);
        }
        return $this->issues;
    }

    /**
     * Recursively copies a directory.
     * @noinspection PhpSameParameterValueInspection
     */
    private function recursiveCopy(string $source, string $dest): bool
    {
        if (!is_dir($dest)) {
            mkdir($dest, 0755, true);
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        $source = rtrim($source, '/');
        foreach ($iterator as $item) {
            $relativePath = substr($item->getPathname(), strlen($source) + 1);
            $destPath = $dest . '/' . $relativePath;
            if ($item->isDir()) {
                if (!is_dir($destPath) && !mkdir($destPath, 0755, true)) {
                    return false;
                }
            } elseif (!copy($item->getPathname(), $destPath)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Recursively deletes a directory.
     */
    private function recursiveDelete(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                rmdir($item->getRealPath());
            } else {
                unlink($item->getRealPath());
            }
        }

        rmdir($dir);
    }
}
