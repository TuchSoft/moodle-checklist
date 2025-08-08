<?php

namespace Tuchsoft\MoodleChecklist\Process;

use Tuchsoft\MoodleChecklist\Report\Issue;
use Tuchsoft\MoodleChecklist\Report\Report;

/**
 * IssuesMustacheProcess extends AbstractParallelProcess to validate Mustache templates using Moodle CI tools.
 *
 * This class handles the parallel execution of the `mustache_lint.php` script for multiple Mustache files.
 * It parses the script's output to identify and categorize issues (errors, warnings, info)
 * related to Mustache syntax and HTML validation, converting them into `Issue` objects.
 * It also includes logic to determine the appropriate HTML validator (local VNU.jar or online).
 */
class IssuesMustacheProcess extends AbstractParallelProcess
{
    /**
     * @var string|null Stores the determined validator path or URL.
     */
    private ?string $validatorPath = null;

    /**
     * Constructs the IssuesMustacheProcess.
     *
     * @param array<string> $files An array of absolute paths to Mustache template files to be validated.
     * @param string $moodleRoot The absolute path to the Moodle installation root directory.
     */
    public function __construct(private array $files, private string $moodleRoot)
    {
        parent::__construct($moodleRoot);
    }

    /**
     * Returns an array of commands for validating each Mustache file.
     *
     * Each command invokes the `mustache_lint.php` script with parameters
     * for the validator, basename, and the specific file to be checked.
     *
     * {@inheritDoc}
     */
    protected function getCommand(): array
    {
        $moodleCi = __DIR__.'/../../vendor/moodlehq/moodle-local_ci/';
        $commands = [];
        $validator = $this->getValidator();

        if ($validator === null) {
            // If no validator could be determined, return an empty set of commands.
            return [];
        }

        foreach ($this->files as $file) {
            $commands[] = [
                'php',
                "$moodleCi/mustache_lint/mustache_lint.php",
                "--validator={$validator}",
                "--basename={$this->moodleRoot}",
                "--filename={$file}"
            ];
        }

        return $commands;
    }

    /**
     * Parses the raw text output from the `mustache_lint.php` script into `Issue` objects.
     *
     * This method processes each line of standard output, attempting to match
     * patterns for Mustache syntax exceptions (ERROR) and HTML validation
     * issues (WARNING/INFO). Identified issues are stored in `$this->issues`.
     *
     * @return bool True if the parsing completes, even if issues are found.
     */
    protected function parseOutput(): bool
    {
        $output = join("\n", $this->allStdout);
        if (empty($output)) {
            // If there's no output, there are no issues found by the linter.
            $this->issues = [];
            return true;
        }

        $lines = explode("\n", $output);

        foreach ($lines as $line) {
            $info = null;
            if (str_contains($line, ' - ERROR: ')) {
                $regex = "/^(?<path>.+)?\s-\s(?<severity>ERROR):\sMustache\ssyntax\sexception:\s(?<message>.*)/";
                preg_match($regex, $line, $info);
            } elseif (str_contains($line, ' - WARNING: ') || str_contains($line, ' - INFO: ')) {
                $regex = "/^(?<path>.+)?\s-\s(?<severity>WARNING|INFO):\sHTML Validation (?:error|info), line (?<line>\d+):\s(?<message>.+)?\.\s\(.+?\)/";
                preg_match($regex, $line, $info);
            }

            if (isset($info['severity'])) {
                $severity = match($info['severity']) {
                    'ERROR' => Report::SEVERITY_ERROR,
                    'WARNING' => Report::SEVERITY_WARNING,
                    'INFO' => Report::SEVERITY_TIP
                };
                // Ensure path is relative to moodleRoot for consistency, if provided.
                $filePath = $info['path'] ?? '';
                if (!empty($filePath) && str_starts_with($filePath, $this->moodleRoot)) {
                    $filePath = substr($filePath, strlen($this->moodleRoot) + 1); // +1 for the trailing slash
                }

                $this->issues[] = new Issue('', $severity, $info['message'], $filePath, $info['line'] ?? null);
            }
        }
        return true;
    }

    /**
     * Gets the parsed issues from the process output.
     *
     * Applies a given code to each issue before returning them.
     *
     * @param string|null $code An optional code to apply to the issues.
     * @return array<Issue> An array of `Issue` objects.
     */
    public function getIssues(?string $code = null): array
    {
        // Use array_map to apply the code to each issue, if a code is provided.
        return $code ? array_map(fn (Issue $i) => $i->addCode($code), $this->issues) : $this->issues;
    }

    /**
     * Checks if Java is installed on the system by running 'java -version'.
     *
     * @return bool True if Java is installed, false otherwise.
     */
    private function isJavaInstalled(): bool
    {
        exec('java -version 2>&1', $output, $returnVar);
        return $returnVar === 0;
    }

    /**
     * Checks for an active internet connection by attempting to open a socket to a well-known host.
     *
     * @return bool True if an internet connection is detected, false otherwise.
     */
    private function hasInternetConnection(): bool
    {
        $connected = @fsockopen("www.google.com", 80, $errno, $errstr, 5);
        if ($connected) {
            fclose($connected);
            return true;
        }
        return false;
    }

    /**
     * Determines and returns the appropriate HTML validator path or URL.
     *
     * This method prioritizes a local Java-based VNU.jar validator.
     * If not available, it falls back to an online validator if an internet connection exists.
     * If neither is available, it adds an error to the internal issues collection and returns null.
     *
     * @return string|null The validator path/URL (string) or null if no validator could be found.
     */
    private function getValidator(): ?string
    {
        if ($this->validatorPath !== null) {
            return $this->validatorPath;
        }

        $moodleCi = __DIR__.'/../../vendor/moodlehq/moodle-local_ci/';
        $vnuJarPath = "$moodleCi/node_modules/vnu-jar/build/dist/vnu.jar";
        $onlineValidatorUrl = "https://html5.validator.nu";

        // 1. Try to use the local VNU.jar validator if Java is installed and the jar file exists.
        if ($this->isJavaInstalled()) {
            if (($vnuJarRealPath = realpath($vnuJarPath))) {
                $this->validatorPath = 'java -jar ' . escapeshellarg($vnuJarRealPath); // Prepend 'java -jar ' for command execution
                return $this->validatorPath;
            } else {
                // Java is installed, but the local vnu.jar was not found.
                $this->issues[] = new Issue(
                    '',
                    Report::SEVERITY_WARNING,
                    'Java is installed, but the local VNU.jar validator was not found at: ' . $vnuJarPath . '. Attempting to use online validator.',
                    'MoodleCIMustacheProcess',
                    null
                );
            }
        }

        // 2. If local VNU.jar couldn't be used, try the online validator if an internet connection exists.
        if ($this->hasInternetConnection()) {
            $this->validatorPath = $onlineValidatorUrl;
            return $this->validatorPath;
        }

        // 3. If neither local Java/VNU.jar nor an internet connection is available, add a critical error.
        $this->issues[] = new Issue(
            '',
            Report::SEVERITY_ERROR,
            'Neither Java (for local VNU.jar) nor an active internet connection (for online validator) could be detected. HTML validation cannot proceed.',
            'MoodleCIMustacheProcess',
            null
        );

        $this->validatorPath = null;
        return null;
    }

    /**
     * Checks if the overall process execution was successful.
     *
     * This method defines success based on the absence of fundamental execution errors
     * (e.g., issues preventing the linter from running or parsing its output).
     * The presence of parsable issues (errors, warnings, or infos) within the linter's
     * output does not indicate a failure of the process execution itself, but rather
     * a successful identification of issues in the Mustache templates.
     *
     * @return bool True if no unrecoverable errors were encountered during process execution or output parsing, false otherwise.
     */
    public function isSuccessful(): bool
    {
        return empty($this->error);
    }
}