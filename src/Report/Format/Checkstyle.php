<?php

namespace Tuchsoft\MoodleChecklist\Report\Format;

use Tuchsoft\MoodleChecklist\Report\Format\Base\AbstractFormat;
use Tuchsoft\MoodleChecklist\Report\Format\Base\ParsableFormatInterface;
use Tuchsoft\MoodleChecklist\Report\Format\Base\XmlFormatTrait;
use Tuchsoft\MoodleChecklist\Report\Issue;
use Tuchsoft\MoodleChecklist\Report\Report;
use DOMDocument;
use DOMElement;
use DOMException;
use SimpleXMLElement;

/**
 * An implementation of a report format that serializes and deserializes
 * a Report object to and from the Checkstyle XML format.
 */
class Checkstyle extends AbstractFormat implements ParsableFormatInterface
{

    use XmlFormatTrait;

    /**
     * Generates a Checkstyle XML string from a Report object.
     *
     * @param Report $report The report object to serialize.
     * @return string The Checkstyle XML string.
     * @throws DOMException If there is an error creating the XML document.
     */
    public function generate(Report $report): string
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $checkstyle = $dom->createElement('checkstyle');
        $checkstyle->setAttribute('version', '3.13.3');
        $dom->appendChild($checkstyle);

        $issuesByPath = $report->getIssues(true);

        foreach ($issuesByPath as $path => $issues) {
            $fileElement = $dom->createElement('file');
            $fileElement->setAttribute('name', $path);
            $checkstyle->appendChild($fileElement);

            /** @var Issue $issue */
            foreach ($issues as $issue) {
                $errorElement = $dom->createElement('error');
                $errorElement->setAttribute('line', (string)$issue->getLine());
                $errorElement->setAttribute('column', (string)$issue->getColumn());

                $severityString = match ($issue->getSeverity()) {
                    Report::SEVERITY_ERROR => 'error',
                    Report::SEVERITY_WARNING, Report::SEVERITY_TIP => 'warning',
                    default => 'warning',
                };
                $errorElement->setAttribute('severity', $severityString);
                $errorElement->setAttribute('message', $issue->getMessage());
                $errorElement->setAttribute('source', $issue->getCode());

                $fileElement->appendChild($errorElement);
            }
        }

        return $this->saveXML($dom);
    }

    /**
     * Parses a Checkstyle XML string and returns a Report object.
     *
     * @param string $input The XML string to parse.
     * @param string $name The name for the new Report object.
     * @return Report The parsed Report object.
     * @throws \InvalidArgumentException If the XML is invalid or the structure is incorrect.
     */
    public function parse(string $input, string $name = 'Parsed report'): Report
    {
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($input);

        if ($xml === false) {
            $errors = libxml_get_errors();
            $errorMessage = "Failed to parse XML: ";
            foreach ($errors as $error) {
                $errorMessage .= "{$error->message} ";
            }
            libxml_clear_errors();
            throw new \InvalidArgumentException($errorMessage);
        }

        $flatIssues = [];
        $reportName = $name; // Checkstyle format doesn't have a report name attribute

        foreach ($xml->file as $fileElement) {
            $path = (string)$fileElement['name'];

            foreach ($fileElement->error as $errorElement) {
                $severityString = (string)$errorElement['severity'];
                $severity = match ($severityString) {
                    'error' => Report::SEVERITY_ERROR,
                    'warning' => Report::SEVERITY_WARNING,
                    'info' => Report::SEVERITY_TIP,
                    default => Report::SEVERITY_WARNING,
                };

                $flatIssues[] = [
                    'message' => (string)$errorElement['message'],
                    'line' => (int)$errorElement['line'],
                    'column' => (int)$errorElement['column'],
                    'path' => $path,
                    'code' => (string)$errorElement['source'],
                    'severity' => $severity,
                ];
            }
        }

        $reportData = [
            'name' => $reportName,
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
        return "Checkstyle XML representation";
    }
}
