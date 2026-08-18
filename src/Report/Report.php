<?php

namespace Tuchsoft\MoodleChecklist\Report;

use PHP_CodeSniffer\Util\Common;
use Tuchsoft\MoodleChecklist\Settings;

/**
 * Class Report
 * A hierarchical report system that can contain issues and child reports.
 */
class Report implements \JsonSerializable
{
    public const SEVERITY_ERROR = 5;
    public const SEVERITY_WARNING = 3;
    public const SEVERITY_TIP = 0;

    /**
     * @var Issue[] Issues for the current report only.
     */
    private array $issues = [];

    private string $name;

    /**
     * @var Report[] Child reports stored as an array of Report objects.
     */
    private array $subReports = [];

    private ?float $timeStart = 0;
    private ?float $timeEnd = 0;
    private ?float $totalTime = 0;

    private ?Settings $setting;

    public function __construct(string $name, Settings $setting = null) {
        $this->name = $name;
        $this->setting = $setting;
    }

    public function start(): static {
        $this->timeStart = microtime(true);
        return $this;
    }

    public function complete(): static {
        $this->timeEnd = microtime(true);
        return $this;
    }

    public function getTotalTime(): ?float {
        if ($this->totalTime) return $this->totalTime;
        if (!$this->timeStart) return null;
        return round(($this->timeEnd - $this->timeStart) * 1000, 0);
    }

    /**
     * Adds a tip issue to the report.
     *
     * @param string $code The issue code.
     * @param string $message The issue message.
     * @param string $path The file path where the issue was found (relative to plugin root).
     * @param int    $line The line number where the issue was found.
     */
    public function addTip(string $code, string $message, ?string $path = null, ?int  $line = null): void
    {
        $this->addIssue($code, self::SEVERITY_TIP, $message, $path, $line);
    }

    /**
     * Adds a warning issue to the report.
     *
     * @param string $code The issue code.
     * @param string $message The issue message.
     * @param string $path The file path where the issue was found (relative to plugin root).
     * @param int    $line The line number where the issue was found.
     */
    public function addWarning(string $code, string $message, ?string $path = null, ?int  $line = null): void
    {
        $this->addIssue($code, self::SEVERITY_WARNING, $message, $path, $line);
    }

    /**
     * Adds an error issue to the report.
     *
     * @param string $code The issue code.
     * @param string $message The issue message.
     * @param string $path The file path where the issue was found (relative to plugin root).
     * @param int    $line The line number where the issue was found.
     */
    public function addError(string $code, string $message, ?string $path = null, ?int  $line = null): void
    {
        $this->addIssue($code, self::SEVERITY_ERROR, $message, $path, $line);
    }

    /**
     * Adds an issue to the report.
     * The issue is stored only in the current report, not in subreports.
     *
     * @param string $code The issue code.
     * @param int    $severity One of Report::SEVERITY_* constants.
     * @param string $message The issue message.
     * @param string $path The file path where the issue was found (relative to plugin root).
     * @param int    $line The line number where the issue was found.
     */
    public function addIssue(string $code, int $severity, string $message, ?string $path = null, ?int $line = null): void
    {
        $this->_addIssue(new Issue($code, $severity, $message, $path, $line));
    }

    public function addIssues(Issue ...$issues): void {
        foreach ($issues as $issue) {
            $this->_addIssue($issue);
        }
    }

    /**
     * Internal method to add a new Issue object to the report.
     *
     * @param Issue $issue
     * @throws \Exception
     */
    private function _addIssue(Issue $issue): void
    {
        if (!$this->timeStart) {
            throw new \Exception('Report has not been started yet, use Report::start() before adding issues.');
        }

        if (!$issue->getCode()) {
            throw new \Exception('Issue must have a code.');
        }

        $issue->addCode($this->name);

        $info = $this->setting->definition->get($issue->getCode());

        if (!$info['active']) {
            return;
        }

        $msg = $issue->getMessage();
        $data = $issue->getMessageData();
        $data['code'] = $issue->getCode();

        if (!$msg) {
            $msg = $info['msg'] ?? 'unknow-message';
        }

        $path = Common::stripBasepath($issue->getPath(), $this->setting->plugin->fullpath);
        if ($path == '.') {
            $path = "Plugin {$this->setting->plugin->component}";
        }
        $issue->setPath($path);

        $issue->setMessage(str_replace(array_map(fn ($key) => "\{$key\}", array_keys($data)), array_values($data), $msg));
        $issue->setRef($info['ref']);
        $issue->setHelp($info['help']);
        $issue->setReported();

        $this->issues[] = $issue;
    }

    public function isIssueActive(string $code): bool {
        return $this->setting->definition->get($code)['active'];
    }

    /**
     * Recursively checks if the current report or any of its subreports contain issues.
     *
     * @return bool
     */
    public function hasIssues(): bool
    {
        // Check current report's issues.
        if (!empty($this->issues)) {
            return true;
        }

        // Recursively check subreports.
        foreach ($this->subReports as $subReport) {
            if ($subReport->hasIssues()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Recursively gets all issues from the current report and all its subreports.
     *
     * @param bool $byFile If true, groups issues by file path.
     * @return Issue[]
     */
    public function getIssues(bool $byFile = false): array
    {
        $allIssues = $this->issues;

        foreach ($this->subReports as $subReport) {
            array_push($allIssues, ...$subReport->getIssues());
        }

        if ($byFile) {
            $allIssues = $this->organizeByFile($allIssues);
        }

        return $allIssues;
    }

    /**
     * Returns an array of all child Report objects.
     *
     * @return Report[]
     */
    public function getSubReports(): array
    {
        return $this->subReports;
    }


    /**
     * Gets a list of subreports that have at least one issue (recursively).
     *
     * @return Report[]
     */
    public function getReportWithIssue(): array
    {
        $reportsWithIssues = [];
        foreach ($this->subReports as $subReport) {
            if ($subReport->hasIssues()) {
                $reportsWithIssues[$subReport->name] = $subReport;
            }
        }
        return $reportsWithIssues;
    }

    /**
     * Gets a list of subreports that do not have any issues (recursively).
     *
     * @return Report[]
     */
    public function getReportWithoutIssue(): array
    {
        $reportsWithoutIssues = [];
        foreach ($this->subReports as $subReport) {
            if (!$subReport->hasIssues()) {
                $reportsWithoutIssues[$subReport->name] = $subReport;
            }
        }
        return $reportsWithoutIssues;
    }

    /**
     * Merges multiple Report objects into the current report as children.
     *
     * @param Report ...$reports
     * @return static
     */
    public function mergeIn(Report ...$reports): static
    {
        foreach ($reports as $report) {
            $this->subReports[$report->name] = $report;
        }
        return $this;
    }

    /**
     * Creates a hierarchical Report object from JSON data.
     *
     * @param array $json
     * @param Settings $settings
     * @return static
     * @throws \Exception
     */
    public static function fromJson(array $json, $settings = null): static {
        if (!isset($json['name'], $json['issues']) || empty($json['name'])) {
            throw new \Exception('Missing required fields (name, issue)');
        }

        $report = new Report($json['name'], $settings);

        // Populate issues for the current report
        foreach ($json['issues'] as $path => $issueArray) {
            if (!empty($issueArray['severity'])) {
                //Flat structure
                $report->issues[] = Issue::fromJson($issueArray);
            } else {
                //File based structure
                foreach ($issueArray as $issueData) {
                    $report->issues[] = Issue::fromJson($issueData);
                }
            }
        }

        // Recursively create subreports
        if (isset($json['subReports'])) {
            foreach ($json['subReports'] as $subReportData) {
                $subReport = self::fromJson($subReportData, $settings);
                $report->subReports[$subReport->name] = $subReport;
            }
        }

        if (isset($json['timeStart']) && isset($json['timeEnd'])) {
            $report->timeStart = $json['timeStart'];
            $report->timeEnd = $json['timeEnd'];
        }

        return $report;
    }

    /**
     * Specify data which should be serialized to JSON.
     *
     * @return array
     */
    public function jsonSerialize(): array
    {
        $serializedSubReports = [];
        foreach ($this->subReports as $subReport) {
            $serializedSubReports[] = $subReport->jsonSerialize();
        }

        $totals = $this->getTotalsRecursive();

        return [
            'name' => $this->name,
            'issues' => $this->organizeByFile($this->issues),
            'subReports' => $serializedSubReports,
            'timeStart' => $this->timeStart,
            'timeEnd' => $this->timeEnd,
            'totalErrors' => $totals['totalErrors'],
            'totalWarnings' => $totals['totalWarnings'],
            'totalTips' => $totals['totalTips'],
            'totalFiles' => $totals['totalFiles'],
        ];
    }

    /**
     * Prepares issues for JSON serialization, grouping them by file path.
     *
     * @return array
     */
    private function organizeByFile($issues): array {
        $output = [];
        foreach ($issues as $issue) {
            $output[$issue->getPath()][] = $issue;
        }
        return $output;
    }

    public function getTimeEnd(): ?float
    {
        return $this->timeEnd;
    }

    public function setTimeEnd(?float $timeEnd): void
    {
        $this->timeEnd = $timeEnd;
    }

    public function getTimeStart(): ?float
    {
        return $this->timeStart;
    }

    public function setTimeStart(?float $timeStart): void
    {
        $this->timeStart = $timeStart;
    }

    public function getName(string|bool $suffix = false): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    /**
     * Recursively calculates and returns the totals for the entire report hierarchy.
     *
     * @return array An associative array with totalErrors, totalWarnings, totalTips, and totalFiles.
     */
    public function getTotalsRecursive(): array
    {
        $totalErrors = 0;
        $totalWarnings = 0;
        $totalTips = 0;
        $allFilePaths = [];

        // Add issues and file paths from the current report
        foreach ($this->issues as $issue) {
            if ($issue->getSeverity() === self::SEVERITY_ERROR) {
                $totalErrors++;
            } elseif ($issue->getSeverity() === self::SEVERITY_WARNING) {
                $totalWarnings++;
            } elseif ($issue->getSeverity() === self::SEVERITY_TIP) {
                $totalTips++;
            }
            $allFilePaths[] = $issue->getPath();
        }

        // Recursively get totals from all subreports
        foreach ($this->subReports as $subReport) {
            $subTotals = $subReport->getTotalsRecursive();
            $totalErrors += $subTotals['totalErrors'];
            $totalWarnings += $subTotals['totalWarnings'];
            $totalTips += $subTotals['totalTips'];
            $allFilePaths = array_merge($allFilePaths, $subTotals['filePaths']);
        }

        return [
            'totalErrors' => $totalErrors,
            'totalWarnings' => $totalWarnings,
            'totalTips' => $totalTips,
            'totalFiles' => count(array_unique($allFilePaths)),
            'filePaths' => $allFilePaths // Also return file paths to merge in the parent recursion
        ];
    }

    public function getTotalErrors(): int
    {
        $totals = $this->getTotalsRecursive();
        return $totals['totalErrors'];
    }

    public function getTotalWarnings(): int
    {
        $totals = $this->getTotalsRecursive();
        return $totals['totalWarnings'];
    }

    public function getTotalTips(): int
    {
        $totals = $this->getTotalsRecursive();
        return $totals['totalTips'];
    }

    public function getTotalFiles(): int
    {
        $totals = $this->getTotalsRecursive();
        return $totals['totalFiles'];
    }

    public function setTotalTime(?float $totalTime): void
    {
        $this->totalTime = $totalTime;
    }



}
