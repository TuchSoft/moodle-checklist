<?php


namespace Tuchsoft\MoodleChecklist\Check\Subcheck;


use Tuchsoft\MoodleChecklist\Plugin;
use Tuchsoft\MoodleChecklist\Report\Issue;
use Tuchsoft\MoodleChecklist\Report\Report;
use Tuchsoft\MoodleChecklist\Settings;

trait BaseCheckTrait
{

    protected Report $report;
    protected Plugin $plugin;
    protected string $path = '.';

    abstract public function getName(): string;
    abstract protected function isActive(string $code): bool;

    public function getReport(): Report
    {
        return $this->report;
    }

    public function getPlugin(): Plugin
    {
        return $this->plugin;
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
        $this->addIssue($code, Report::SEVERITY_TIP, $message, $path, $line);
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
        $this->addIssue($code, Report::SEVERITY_WARNING, $message, $path, $line);
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
        $this->addIssue($code, Report::SEVERITY_ERROR, $message, $path, $line);
    }


    protected function runtimeError($msg, $path = null): void {
        $this->addError(
            'runtime-error',
            $msg,
            $path == '.'
        );
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
        if (!$this->isActive($code)) {
            return;
        }
        if (!$path) {
            $path = $this->path;
        }
        $this->report->addIssue($code, $severity, $message, $path, $line);
    }


}