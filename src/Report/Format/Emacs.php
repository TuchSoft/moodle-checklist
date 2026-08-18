<?php

namespace Tuchsoft\MoodleChecklist\Report\Format;

use Tuchsoft\MoodleChecklist\Report\Format\Base\AbstractFormat;
use Tuchsoft\MoodleChecklist\Report\Format\Base\ParsableFormatInterface;
use Tuchsoft\MoodleChecklist\Report\Issue;
use Tuchsoft\MoodleChecklist\Report\Report;

/**
 * An implementation of a report format that serializes and deserializes
 * a Report object to and from a simple Emacs-style text format.
 */
class Emacs extends AbstractFormat implements ParsableFormatInterface
{
    /**
     * Generates a multiline string in the Emacs-style format from a Report object.
     *
     * Format: /path/to/file.ext:line:column: severity - message (code)
     *
     * @param Report $report The report object to serialize.
     * @return string The formatted multiline string.
     */
    public function generate(Report $report): string
    {
        $outputLines = [];
        $issuesByPath = $report->getIssues(true);

        foreach ($issuesByPath as $path => $issues) {
            /** @var Issue $issue */
            foreach ($issues as $issue) {
                // Map internal severity to the required string
                $severityString = match ($issue->getSeverity()) {
                    Report::SEVERITY_ERROR => 'error',
                    Report::SEVERITY_WARNING, Report::SEVERITY_TIP => 'warning',
                    default => 'warning',
                };

                $outputLines[] = sprintf(
                    "%s:%d:%d: %s - %s (%s)",
                    $path,
                    $issue->getLine(),
                    $issue->getColumn(),
                    $severityString,
                    $issue->getMessage(),
                    $issue->getCode()
                );
            }
        }

        return implode("\n", $outputLines);
    }

    /**
     * Parses a multiline string in the Emacs-style format and returns a Report object.
     *
     * @param string $input The text string to parse.
     * @param string $name The name for the new Report object.
     * @return Report The parsed Report object.
     * @throws \InvalidArgumentException If the input format is invalid.
     */
    public function parse(string $input, string $name = 'Parsed report'): Report
    {
        $lines = explode("\n", $input);
        $flatIssues = [];

        foreach ($lines as $line) {
            if (empty(trim($line))) {
                continue;
            }

            // Regex to match the Emacs-style format
            // Group 1: path, Group 2: line, Group 3: column, Group 4: severity, Group 5: message, Group 6: code
            $pattern = '/^([^:]+):(\d+):(\d+): (error|warning) - (.+) \((.+)\)$/';
            if (preg_match($pattern, $line, $matches)) {
                $path = $matches[1];
                $line = (int)$matches[2];
                $column = (int)$matches[3];
                $severityString = $matches[4];
                $message = $matches[5];
                $code = $matches[6];

                // Map the severity string back to an internal constant
                $severity = match ($severityString) {
                    'error' => Report::SEVERITY_ERROR,
                    'warning' => Report::SEVERITY_WARNING,
                    default => Report::SEVERITY_WARNING,
                };

                $flatIssues[] = [
                    'message' => $message,
                    'line' => $line,
                    'column' => $column,
                    'path' => $path,
                    'code' => $code,
                    'severity' => $severity,
                ];
            }
        }

        $reportData = [
            'name' => $name,
            'issues' => $flatIssues,
            'subReports' => [],
            'timeStart' => 0,
            'timeEnd' => 0,
        ];

        return Report::fromJson($reportData);
    }

    /**
     * @return string The description of the format.
     */
    public static function getDesc(): string
    {
        return 'Emacs-style text representation for static analysis reports';
    }

    public static function getFormat(): string
    {
        return self::FORMAT_TXT;
    }
}
