<?php

namespace Tuchsoft\MoodleChecklist\Report;


use PHP_CodeSniffer\Util\Common;
use Tuchsoft\MoodleChecklist\Settings;

class Report implements \JsonSerializable
{

    public const SEVERITY_ERROR = 5;
    public const SEVERITY_WARNING = 3;
    public const SEVERITY_TIP = 0;


    /**
     * @var Issue[]
     */
    private array $issues = []; // Stores issues grouped by filepath
    private array $hasIssue = [];
    private string $name;
    private array $subReports = [];

    private ?float $timeStart = 0;
    private ?float $timeEnd = 0;

    private Settings $setting;




    public function __construct(string $name, Settings $setting) {
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

        if (!$this->timeStart) {
            throw new \Exception('Report has not been started yet, use Report::start() before adding issues.');
        }

        if (!$issue->getCode()) {
            throw new \Exception('');
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
        $this->hasIssue[$this->name] = $this->name;
    }


    public function isIssueActive(string $code) {
        return $this->setting->definition->get($code)['active'];
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
        $x = array_intersect_key($this->subReports, $this->hasIssue);
        return $x;
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
    public function mergeIn(Report ...$reports): static
    {
        foreach ($reports as $report) {
            $this->subReports = array_merge($this->subReports, $report->getSubReports());
            $this->subReports[$report->name] = $report->getTotalTime();
            $this->hasIssue = array_merge($this->hasIssue, $report->hasIssue);
            array_push($this->issues, ...$report->issues);
        }
        return $this;
    }




    public static function fromJson(array $json, $settings): static {
        if (!isset($json['name'], $json['issues']) || empty($json['name'])) {
            throw new \Exception('Missing required fields (name, issue)');
        }
        $report = new Report($json['name'], $settings);
        foreach ($json['issues'] as $issues) {
            foreach ($issues as $path => $issue) {
                $report->issues[] = Issue::fromJson($issue);
                $report->hasIssue[$report->name] = $report->name;
            }
        }
        /**
        if (isset($json['subReports'])) {
            foreach ($json['subReports'] as $subReports) {
                $report->subReports[] = Report::fromJson($subReports, $settings);
            }
        }
         */

        if ($json['timeStart'] && $json['timeEnd']) {
            $report->timeStart = $json['timeStart'];
            $report->timeEnd = $json['timeEnd'];
        }

        return $report;
    }

    /**
     * Specify data which should be serialized to JSON.
     * @return array
     */
    public function jsonSerialize(): array
    {
        return [
            'name' => $this->name,
            'issues' => $this->getIssues(true), // Group issues by file path for clarity in JSON output
            'subReports' => $this->subReports,
            'timeStart' => $this->timeStart,
            'timeEnd' => $this->timeEnd
        ];
    }



}