<?php

namespace Tuchsoft\MoodleChecklist\Process;

use Tuchsoft\MoodleChecklist\Report\Issue;
use Tuchsoft\MoodleChecklist\Report\Report;

/**
 * RemarkProcess executes the 'npx remark' command and parses its JSON output.
 * It expects the AST tree on stdout and issues on stderr, both in JSON format.
 */
class RemarkProcess extends AbstractProcess
{
    private string $file;
    private ?array $tree = null;
    private ?array $issues = null;


    /**
     * @param string $file The path to the Markdown file to process.
     */
    public function __construct(string $file, protected array $options = [])
    {
        $this->file = $file;
        parent::__construct();
    }

    /**
     * Defines the specific 'npx remark' command and its arguments.
     *
     * @return array<string> The command as an array of strings.
     */
    protected function getCommand(): array
    {

        $cmd = [
            './node_modules/.bin/remark',
            '--report=vfile-reporter-json',
            '--tree-out',
        ];

        if (!empty($this->options)) {
            array_push($cmd, ...$this->options);
        }

        $cmd[] =  $this->file;
        return $cmd;
    }

    /**
     * Executes the remark process and attempts to parse the JSON outputs.
     *
     * @param float|null $timeout The timeout in seconds for the process. Null for no timeout.
     * @return bool True if the process was successful and JSON outputs were parsed, false otherwise.
     */
    public function execute(?float $timeout = 60.0): bool
    {
        // Execute the command using the parent's method
        if (!parent::execute($timeout)) {
            // If the command itself failed, no need to parse JSON
            return false;
        }




        // Attempt to parse stderr (the issues array)
        $stderr = $this->getStderr();
        if ($stderr !== null && $stderr !== '') {

            // vfile-reporter-json outputs an array of issues
            $this->issues = json_decode($stderr, true, 512, JSON_THROW_ON_ERROR);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->error = ($this->error ? $this->error . "\n" : '') . "Unknown error: " . json_last_error_msg();
                return false;
            }
        } else {
            // If there are no issues, stderr might be empty
            $this->issues = [];
        }



        // Attempt to parse stdout (the AST tree)
        $stdout = $this->getStdout();
        if ($stdout !== null && $stdout !== '') {

                $this->tree = json_decode($stdout, true, 512, JSON_THROW_ON_ERROR);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $this->error = ($this->error ? $this->error . "\n" : '') . "Unknown error: " . json_last_error_msg();
                    return false;
                }
                file_put_contents('/var/www/html/ast.json', $stdout);

        } else {
            // It's possible for stdout to be empty if the file is empty or remark has no tree to output
            $this->tree = null;
        }



        return true; // Process successful and JSON parsed
    }

    /**
     * Gets the parsed AST (Abstract Syntax Tree) as a PHP array.
     *
     * @return array|null The parsed AST, or null if not available or parsing failed.
     */
    public function getTree(): ?array
    {
        return $this->tree;
    }

    /**
     * Gets the parsed issues array as a PHP array.
     *
     * @return array|null The parsed issues, or null if not available or parsing failed.
     */
    public function getIssues($severity_low = Report::SEVERITY_WARNING, $severity_high = Report::SEVERITY_ERROR): ?array
    {
        if (!isset($this->issues[0]) || !isset($this->issues[0]['messages'])) {
            return [];
        }

        return array_map(fn ($issue) => new Issue(
            "{$issue['source']}.{$issue['ruleId']}",
            ($issue['fatal'] ? $severity_high : $severity_low),
            $issue['reason'],
            $this->file,
            isset($issue['line']) ? $issue['line'] : null
        ), $this->issues[0]['messages']  );
    }



}
