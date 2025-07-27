<?php

namespace Tuchsoft\MoodleChecklist\Report;


use PHP_CodeSniffer\Util\Common;
use Tuchsoft\MoodleChecklist\Settings;

class Report
{

    public const int SEVERITY_ERROR = 5;
    public const int SEVERITY_WARNING = 3;
    public const int SEVERITY_TIP = 0;


    /**
     * @var Issue[]
     */
    private array $issues = []; // Stores issues grouped by filepath
    private array $hasIssue = [];
    private string $name;
    private array $subReports = [];

    private float $timeStart;
    private float $timeEnd;

    private Settings $setting;




    public function __construct(string $name, Settings $setting) {
        $this->name = $name;
        $this->timeStart = microtime(true);
        $this->setting = $setting;
    }

    public function setCompleted(): void {
        $this->timeEnd = microtime(true);
    }

    public function getTotalTime(): float {
        return round($this->timeEnd - $this->timeStart, 2);
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
     * Adds an issue to the report, storing it as-is per filepath.
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


    private function _addIssue(Issue $issue): void
    {
        $info = $this->setting->definition->get("{$this->name}.{$issue->getCode()}");
        if (!$info['active']) {
            return;
        }

        $issue->addCode($this->name);

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

        $issue->setMessage(str_replace(array_keys($data), array_values($data), $msg));
        $issue->setRef($info['ref']);
        $issue->setHelp($info['help']);
        $issue->setReported();

        $this->issues[] = $issue;
        $this->hasIssue[$this->name] = $this->name;
    }


    public function isIssueActive($code) {
        return $this->setting->definition->get("$this->name.$code")['active'];
    }


    public function hasIssues($name = null): bool
    {
        $name = $name ?? $this->name;
        return in_array($name, $this->hasIssue);
    }


    /**
     * @return Issue[]
     */
    public function getIssues(bool $byFile = false): array
    {
        if ($byFile) {
            $output = [];
            foreach ($this->issues as $issue) {
                $output[$issue->getPath()][] = $issue;
            }
            return $output;
        }
        return $this->issues;
    }

    public function getSubReports(): array
    {
        return $this->subReports;
    }


    public function getReportWithIssue(): array
    {
        return array_intersect_key($this->subReports, $this->hasIssue);
    }

    public function getReportWithoutIssue(): array
    {
        return array_diff_key($this->subReports, $this->hasIssue);
    }



    /**
     * Merges multiple Report objects into a single new Report object.
     *
     * @param Report ...$reports
     * @return Report
     */
    public static function merge($name, Report ...$reports): Report
    {

        $mergedReport = new Report($name, $reports[0]->setting);
        foreach ($reports as $report) {
            $mergedReport->subReports = array_merge($mergedReport->subReports, $report->getSubReports());
            $mergedReport->subReports[$report->name] = $report->getTotalTime();
            $mergedReport->hasIssue = array_merge($mergedReport->hasIssue, array_values($report->hasIssue));
            array_push($mergedReport->issues, ...$report->issues);
        }
        return $mergedReport;
    }




    public function fromJson(array $json): array {
            $issues = [];
            foreach ($json as $path => $fileReport) {
                foreach ($fileReport['messages'] as $line => $columns) {
                    foreach ($columns as $column => $messages) {
                        foreach ($messages as $messageData) {
                            $severity = match(true) {
                                $messageData['type'] == 'ERROR' => Report::SEVERITY_ERROR,
                                $messageData['type'] == 'WARNING' && $messageData['severity'] > 1 => Report::SEVERITY_WARNING,
                                default => Report::SEVERITY_TIP
                            };
                            $issues[]  = new Issue($messageData['source'], $severity,  $messageData['message'], $path, $line);
                        }
                    }
                }
            }
            return $issues;
    }



}