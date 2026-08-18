<?php

namespace Tuchsoft\MoodleChecklist\Process;

use Tuchsoft\IssueReporter\Issue;
use Tuchsoft\IssueReporter\Report;

/**
 * RemarkProcess executes the 'npx remark' command and parses its JSON output.
 * It expects the AST tree on stdout and issues on stderr, both in JSON format.
 */
class RemarkProcess extends AbstractIssuesProcess
{
    private string $file;
    private ?array $tree = null;


    private int $severityLow;
    private int $severityHigh;

    /**
     * @param string $file The path to the Markdown file to process.
     */
    public function __construct(string $file, protected array $options = [], int $severityLow = Issue::SEVERITY_WARNING, int $severityHigh = Issue::SEVERITY_ERROR)
    {
        $this->file = $file;
        $this->severityLow = $severityLow;
        $this->severityHigh = $severityHigh;
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




    protected function parseOutput(): bool
    {
        // Attempt to parse stderr (the issues array)
        $stderr = $this->getStderr();
        if ($stderr !== null && $stderr !== '') {

            // vfile-reporter-json outputs an array of issues
            $this->issues = json_decode($stderr, true, 512, JSON_THROW_ON_ERROR);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->error = ($this->error ? $this->error . "\n" : '') . 'Unknown error: ' . json_last_error_msg();
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
                $this->error = ($this->error ? $this->error . "\n" : '') . 'Unknown error: ' . json_last_error_msg();
                return false;
            }

        } else {
            // It's possible for stdout to be empty if the file is empty or remark has no tree to output
            $this->tree = null;
        }
        return true;
    }

    /**
     * Gets the parsed issues array as a PHP array.
     *
     * @return array|null The parsed issues, or null if not available or parsing failed.
     */
    public function getIssues(?string $code = null): array
    {
        if (!isset($this->issues[0]) || !isset($this->issues[0]['messages'])) {
            return [];
        }

        return array_map(function ($issue) use ($code) {
            $newIssue = new Issue(
                "{$issue['source']}.{$issue['ruleId']}",
                ($issue['fatal'] ? $this->severityHigh : $this->severityLow),
                $issue['reason'],
                $this->file,
                $issue['line'] ?? null
            );
            if ($code) {
                $newIssue->addCode($code);
            }
            return $newIssue;
        }, $this->issues[0]['messages']);
    }



}
