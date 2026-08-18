<?php


namespace Tuchsoft\MoodleChecklist\Report\Format;

use Tuchsoft\MoodleChecklist\Report\Format\Base\AbstractFormat;
use Tuchsoft\MoodleChecklist\Report\Format\Base\JsonFormatTrait;
use Tuchsoft\MoodleChecklist\Report\Format\Base\ParsableFormatInterface;
use Tuchsoft\MoodleChecklist\Report\Issue;
use Tuchsoft\MoodleChecklist\Report\Report;

class PhpCs extends AbstractFormat implements ParsableFormatInterface
{

    use JsonFormatTrait;
    public function generate(Report $report): string
    {
        $output = [
            'totals' => [
                'errors' => $report->getTotalErrors(),
                'warnings' => $report->getTotalWarnings() + $report->getTotalTips(),
                'fixable' => 0,
            ],
            'files' => []
        ];

        foreach ($report->getIssues(true) as $path => $issues) {
            $errorsCount = 0;
            $warningsCount = 0;
            $messages = [];
            /** @var Issue $issue */
            foreach ($issues as $issue) {
                if ($issue->getSeverity() === Report::SEVERITY_ERROR) {
                    $messageType = 'ERROR';
                    $errorsCount++;
                } else {
                    $messageType = 'WARNING';
                    $warningsCount++;
                }

                $messages[] = [
                    'message'  => $issue->getMessage(),
                    'source'   => $issue->getCode(),
                    'severity' => $issue->getSeverity(),
                    'fixable'  => false, // FIXME: add support for autofixer
                    'type'     => $messageType,
                    'line'     => $issue->getLine(),
                    'column'    => $issue->getColumn(),
                ];
            }

            $output['files'][$path] = [
                'errors'   => $errorsCount,
                'warnings' => $warningsCount,
                'messages' => $messages,
            ];
        }
        return $this->jsonEncode($output);
    }


    public function parse(string $input, string $name = 'Parsed report'): Report
    {
        $data = json_decode($input, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException('Invalid JSON input: ' . json_last_error_msg());
        }

        if (!is_array($data)) {
            throw new \InvalidArgumentException('Decoded JSON is not an array.');
        }


        if (!is_array($data['files'])) {
            throw new \InvalidArgumentException('Decoded JSON is not valid.');
        }

        $flatIssues = [];
        foreach ($data['files'] as $filePath => $fileReport) {
            if (!is_array($fileReport) || !isset($fileReport['messages'])) {
                continue;
            }

            foreach ($fileReport['messages'] as $issueData) {
                $flatIssues[] = [
                    'message' => $issueData['message'],
                    'line' => $issueData['line'] ?? 1,
                    'column' => $issueData['column'] ?? 1,
                    'path' => $filePath,
                    'code' => $issueData['source'],
                    'severity' => match ($issueData['type']) {
                        'ERROR' => Report::SEVERITY_ERROR,
                        'WARNING' => Report::SEVERITY_WARNING
                    }
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

    static function getDesc(): string
    {
        return "Php Code Sniffer JSON rappresetation";
    }
}