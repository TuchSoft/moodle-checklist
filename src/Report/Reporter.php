<?php

namespace Tuchsoft\MoodleChecklist\Report;




use PHP_CodeSniffer\Autoload;
use PHP_CodeSniffer\Reports\Report as PhpcsReportInterface;
use PHP_CodeSniffer\Util\Common;
use Tuchsoft\MoodleChecklist\Settings;

class Reporter
{
    public const string RAW_REPORT = 'raw';

    private array $tmpFiles = [];
    private array $reports = [];
    public static float $startTime;
    private array $reportData = [];
    public int $totalFiles = 0;
    public int $totalErrors = 0;
    public int $totalWarnings = 0;

    public function __construct(private Settings $setting, private readonly Report $report)
    {

        static::$startTime = microtime(true);
        // Define PHP_CODESNIFFER_VERBOSITY and PHP_CODESNIFFER_CBF if they aren't already
        if (!defined("PHP_CODESNIFFER_VERBOSITY")) {
            define("PHP_CODESNIFFER_VERBOSITY", 0);
        }
        if (!defined("PHP_CODESNIFFER_CBF")) {
            define("PHP_CODESNIFFER_CBF", false);
        }
        foreach ($setting->reports as $type => $output) {
            if ($output === null) {
                $output = $setting->reportFile;
            }
            if ($type == static::RAW_REPORT) {
                $this->reports[$type] = [
                    "output" => $output,
                    "class" => null,
                ];
            } else {
                $reportClassName = "";
                if (strpos($type, ".") !== false) {
                    $filename = realpath($type);
                    if ($filename === false) {
                        throw new \Exception(
                            "ERROR: Custom report \"$type\" not found",
                        );
                    }
                    $reportClassName = Autoload::loadFile($filename);
                } elseif (
                    class_exists("PHP_CodeSniffer\Reports\\" . ucfirst($type)) ===
                    true
                ) {
                    $reportClassName = "PHP_CodeSniffer\Reports\\" . ucfirst($type);
                } elseif (class_exists($type) === true) {
                    $reportClassName = $type;
                } else {
                    $registeredNamespaces = Autoload::getSearchPaths();
                    $trimmedType = ltrim($type, "\\");
                    foreach ($registeredNamespaces as $nsPrefix) {
                        if ($nsPrefix === "") {
                            continue;
                        }
                        if (
                            class_exists($nsPrefix . "\\" . $trimmedType) === true
                        ) {
                            $reportClassName = $nsPrefix . "\\" . $trimmedType;
                            break;
                        }
                    }
                }
                if ($reportClassName === "") {
                    throw new \Exception(
                        "ERROR: Class file for report \"$type\" not found"
                    );
                }
                $reportClass = new $reportClassName();
                if ($reportClass instanceof PhpcsReportInterface === false) {
                    throw new \Exception(
                        "Class '$reportClassName' must implement the 'PHP_CodeSniffer\Reports\Report' interface."
                    );
                }
                $this->reports[$type] = [
                    "output" => $output,
                    "class" => $reportClass,
                ];
            }

            if ($output !== null) {
                file_put_contents($output, "");
            }
        }

        $this->reportData = $this->prepareReport($report);

    }






    protected function prepareReport(Report $report): array {
            $fileReports = [];

            foreach ($report->getIssues(true) as $path => $issues) {
                $errorsCount = 0;
                $warningsCount = 0;
                $messages = [];
                /** @var Issue $issue */
                foreach ($issues as $issue) {

                    $messageType = '';
                    if ($issue->getSeverity() === Report::SEVERITY_ERROR) {
                        $messageType = 'ERROR';
                        $errorsCount++;
                    } else {
                        // PHP_CodeSniffer typically categorizes anything not an error as a warning for reporting.
                        $messageType = Report::SEVERITY_WARNING ? 'WARNING' : 'TIP';
                        $warningsCount++;
                    }

                    // PHP_CodeSniffer's report structure expects messages to be keyed by line and then column.
                    // Since your addIssue only provides a line, we'll use column 1.
                    $line = $issue->getLine();
                    $column = 1; // Defaulting to column 1 as per the original PHPCS File::addMessage usage.


                    $messages[$line][$column][] = [
                        'message'  => $issue->getMessage(),
                        'source'   => $issue->getCode(),
                        'severity' => $issue->getSeverity(),
                        'fixable'  => false,
                        'type'     => $messageType,
                    ];

                }

                $fileReports[$path] = [
                    'filename' => $path,
                    'errors'   => $errorsCount,
                    'warnings' => $warningsCount,
                    'fixable'  => 0, // No fixable issues are tracked by this custom report
                    'messages' => $messages,
                ];

                $this->totalFiles++;
                $this->totalErrors += $errorsCount;
                $this->totalWarnings += $warningsCount;

            }

            return $fileReports;
        }



    /**
     * Generates and prints final versions of all reports.
     *
     * @return bool TRUE if any of the reports output content to the screen,
     * FALSE if all reports were silently printed to a file.
     */
    public function printReports(): bool
    {
        $toScreen = false;
        foreach ($this->reports as $type => $report) {
            if ($report["output"] === null) {
                $toScreen = true;
            }
            $this->printReport($type);
        }
        return $toScreen;
    }


    public function getPartial($reportClass, $path, $data ): string
    {
        ob_start();
        $reportClass->generateFileReport($data, (new File($path, $this->setting)), true, 110);
        $partialReport = ob_get_contents();
        ob_end_clean();
        return $partialReport;
    }


    /**
     * Generates and prints a single final report.
     *
     * @param string $reportType The report type to print (e.g., 'full', 'json').
     *
     * @return void
     */
    public function printReport(string $reportType): void
    {

        $reportFile = $this->reports[$reportType]["output"];

        $filename = null;
        $toScreen = true;

        if ($reportFile !== null) {
            $filename = $reportFile;
            $toScreen = false;
        } elseif (isset($this->tmpFiles[$reportType]) === true) {
            $filename = $this->tmpFiles[$reportType];
        }

        if ($reportType == static::RAW_REPORT) {
            $generatedReport = json_encode($this->report->getIssues(true), JSON_PRETTY_PRINT);
        } else {
            $reportClass = $this->reports[$reportType]["class"];
            $rawReport = "";
            foreach ($this->reportData as $path => $data) {
                $rawReport .= $this->getPartial($reportClass, $path, $data);
            }

            ob_start();
            $reportClass->generate(
                $rawReport,
                $this->totalFiles,
                $this->totalErrors,
                $this->totalWarnings,
                0,
                false,
                $this->setting->reportWidth,
                false,
                $toScreen
            );
            $generatedReport = ob_get_contents();
            ob_end_clean();
        }



        if ($reportFile !== null) {
            file_put_contents($reportFile, $generatedReport . PHP_EOL);
        } else {
            echo $generatedReport;
            if ($filename !== null && file_exists($filename) === true) {
                unlink($filename);
                unset($this->tmpFiles[$reportType]);
            }
        }
    }

}