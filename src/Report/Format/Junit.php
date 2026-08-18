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
 * a Report object to and from the JUnit XML format.
 */
class Junit extends AbstractFormat implements ParsableFormatInterface
{
    use XmlFormatTrait;

    /**
     * Generates a JUnit XML string from a Report object.
     *
     * @param Report $report The report object to serialize.
     * @return string The JUnit XML string.
     * @throws DOMException If there is an error creating the XML document.
     */
    public function generate(Report $report): string
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $issuesByPath = $report->getIssues(true);
        $totalTestCount = count($issuesByPath);
        $totalErrorCount = $report->getTotalErrors();
        $totalWarningCount = $report->getTotalWarnings() + $report->getTotalTips();

        // Create the root <testsuites> element
        $testSuites = $dom->createElement('testsuites');
        $testSuites->setAttribute('tests', (string)$totalTestCount);
        $testSuites->setAttribute('failures', (string)($totalErrorCount + $totalWarningCount));
        $testSuites->setAttribute('errors', (string)$totalErrorCount); // In JUnit, "errors" often refer to fatal issues.
        $testSuites->setAttribute('name', $report->getName());
        $dom->appendChild($testSuites);

        foreach ($issuesByPath as $path => $issues) {
            $testSuite = $dom->createElement('testsuite');
            $testSuite->setAttribute('name', $path);
            $testSuite->setAttribute('tests', (string)count($issues));
            $testSuite->setAttribute('failures', (string)count($issues)); // Simple model: all issues are considered failures for JUnit
            $testSuites->appendChild($testSuite);

            /** @var Issue $issue */
            foreach ($issues as $issue) {
                $testCase = $dom->createElement('testcase');
                $testCase->setAttribute('name', "{$issue->getCode()}: {$issue->getMessage()}");
                $testCase->setAttribute('classname', $path);

                // Add failure or error element based on severity
                if ($issue->getSeverity() === Report::SEVERITY_ERROR) {
                    $failure = $dom->createElement('error');
                    $failure->setAttribute('type', $issue->getCode());
                    $failure->appendChild($dom->createTextNode(
                        "Error in file '{$path}' at line {$issue->getLine()}, column {$issue->getColumn()}: {$issue->getMessage()}"
                    ));
                    $testCase->appendChild($failure);
                } else {
                    $failure = $dom->createElement('failure');
                    $failure->setAttribute('type', $issue->getCode());
                    $failure->appendChild($dom->createTextNode(
                        "Warning/Tip in file '{$path}' at line {$issue->getLine()}, column {$issue->getColumn()}: {$issue->getMessage()}"
                    ));
                    $testCase->appendChild($failure);
                }
                $testSuite->appendChild($testCase);
            }
        }

        return $this->saveXML($dom);
    }

    /**
     * Parses a JUnit XML string and returns a Report object.
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
        $reportName = (string)($xml['name'] ?? $name);

        foreach ($xml->testsuite as $testsuite) {
            $path = (string)$testsuite['name'];

            foreach ($testsuite->testcase as $testcase) {
                // Check for failure/error elements
                $failure = $testcase->failure;
                $error = $testcase->error;
                $issueElement = $failure ?: $error;

                if ($issueElement) {
                    $severity = $error ? Report::SEVERITY_ERROR : Report::SEVERITY_WARNING;
                    $code = (string)$issueElement['type'];
                    $message = trim((string)$issueElement);

                    // Extract line and column from the message if possible. This is a heuristic.
                    if (preg_match("/at line (\d+), column (\d+)/", $message, $matches)) {
                        $line = (int)$matches[1];
                        $column = (int)$matches[2];
                    } else {
                        $line = 1;
                        $column = 1;
                    }

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
        return "JUnit XML representation for static analysis reports";
    }
}
