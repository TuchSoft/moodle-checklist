<?php

namespace Tuchsoft\MoodleChecklist\Report\Format\Base;

use DavidBadura\MarkdownBuilder\MarkdownBuilder;
use Symfony\Component\Console\Output\BufferedOutput;
use Tuchsoft\MoodleChecklist\Report\Report;

trait InfoFormatTrait
{

    use RichFormatTrait;

    protected function getSummary(Report $report) {
        $headers = ['Report', 'Files', 'Errors', 'Warnings', 'Tips', 'Time'];
        $rows = [];
        foreach ([...$report->getSubReports(), $report] as $subReport) {
            $totals = $subReport->getTotalsRecursive();
            $rows[] = [$report->getName(), $totals['totalFiles'], $totals['totalErrors'], $totals['totalWarnings'], $totals['totalTips'], $report->getTotalTime() . ' ms'];
        }

        return [
            $headers,
            $rows,
        ];
    }

    protected function getDetails(Report $report)
    {
        $tables = [];
        foreach ($report->getIssues(true) as $path => $issues) {
            $headers = array_filter([
                'Line',
                ' ',
                $this->options['show-code'] ? 'Code' : null,
                'Message',
                $this->options['show-help'] ? 'Help' : null,
                $this->options['show-ref'] ? 'Ref' : null,
            ]);

            $rows = [];
            foreach ($issues as $issue) {
                $rows[] = array_filter([
                    $issue->getLine() . ($issue->getColumn() ? ":{$issue->getColumn()}" : ''),
                    $this->getSeverityIcon($issue->getSeverity()),
                    $this->options['show-code'] ? $issue->getCode() : null,
                    $issue->getMessage(),
                    $this->options['show-help'] ? $issue->getHelp() : null,
                    $this->options['show-ref'] ? $issue->getRef() : null
                ], fn($value) => $value !== null);
            }
            $tables[$path] = [
                $headers,
                $rows,
            ];
        }
        return $tables;
    }



}