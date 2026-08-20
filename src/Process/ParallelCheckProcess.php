<?php

namespace Tuchsoft\MoodleChecklist\Process;

use Tuchsoft\IssueReporter\Issue;

/**
 * IssuesMustacheProcess extends AbstractParallelProcess to validate Mustache templates using Moodle CI tools.
 *
 * This class handles the parallel execution of the `mustache_lint.php` script for multiple Mustache files.
 * It parses the script's output to identify and categorize issues (errors, warnings, info)
 * related to Mustache syntax and HTML validation, converting them into `Issue` objects.
 * It also includes logic to determine the appropriate HTML validator (local VNU.jar or online).
 */
class ParallelCheckProcess extends AbstractParallelProcess
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
    public function __construct(private string $plugin, private array $checks, private array $options,)
    {
        parent::__construct(realpath(__DIR__.'/../../'));
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
        $commands = [];

        $options = [
            ...$this->options,
            '--quiet',
            '--no-interaction',
            '--format', 'raw'
        ];

        foreach ($this->checks as $check) {
            $commands[] = [
                'php',
                "./bin/console",
                "check",
                ...$options,
                '--only', "$check",
                $this->plugin
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
    {return true;}

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