<?php

namespace Tuchsoft\MoodleChecklist\Check\Subcheck;

use Tuchsoft\IssueReporter\Issue;
use Tuchsoft\IssueReporter\Report;
use Tuchsoft\MoodleChecklist\Plugin;
use Tuchsoft\MoodleChecklist\Settings;

trait BaseCheckTrait
{

    protected Report $report;
    protected Plugin $plugin;
    protected string $path = '.';

    /**
     * @var Settings The settings object is provided by the composing check class.
     */
    // protected Settings $settings; // intentionally not declared here to avoid conflicts with promoted constructor properties in AbstractCheck subclasses.

    abstract public static function getName(): string;
    abstract protected function isActive(?string $code = null): bool;

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
     * @param string|null $path The file path where the issue was found (relative to plugin root).
     * @param int|null $line The line number where the issue was found.
     */
    public function addTip(string $code, string $message, ?string $path = null, ?int $line = null): void
    {
        $this->addIssue($code, Issue::SEVERITY_TIP, $message, $path, $line);
    }

    /**
     * Adds a warning issue to the report.
     *
     * @param string $code The issue code.
     * @param string $message The issue message.
     * @param string|null $path The file path where the issue was found (relative to plugin root).
     * @param int|null $line The line number where the issue was found.
     */
    public function addWarning(string $code, string $message, ?string $path = null, ?int $line = null): void
    {
        $this->addIssue($code, Issue::SEVERITY_WARNING, $message, $path, $line);
    }

    /**
     * Adds an error issue to the report.
     *
     * @param string $code The issue code.
     * @param string $message The issue message.
     * @param string|null $path The file path where the issue was found (relative to plugin root).
     * @param int|null $line The line number where the issue was found.
     */
    public function addError(string $code, string $message, ?string $path = null, ?int $line = null): void
    {
        $this->addIssue($code, Issue::SEVERITY_ERROR, $message, $path, $line);
    }


    /**
     * @param string|null $msg
     * @param string|null $path
     */
    protected function runtimeError($msg, $path = null): void
    {
        $message = $msg ?? 'An unexpected error occurred';
        $this->addError('runtime-error', $message, $path === '.' ? null : $path);
    }

    /**
     * Adds an issue to the report, enriching it with the issue definition.
     *
     * @param string $code The issue code.
     * @param int $severity One of Issue::SEVERITY_* constants.
     * @param string $message The issue message.
     * @param string|null $path The file path where the issue was found (relative to plugin root).
     * @param int|null $line The line number where the issue was found.
     */
    public function addIssue(string $code, int $severity, string $message, ?string $path = null, ?int $line = null): void
    {
        if (!$path) {
            $path = $this->path;
        }
        $issue = new Issue($code, $severity, $message, $path, $line);
        $this->enrichAndAddIssue($issue);
    }

    /**
     * Adds a pre-built Issue object to the report after enriching it.
     *
     * @param Issue $issue
     */
    public function addIssueObject(Issue $issue): void
    {
        $this->enrichAndAddIssue($issue);
    }

    /**
     * Adds multiple pre-built Issue objects to the report after enriching them.
     *
     * @param Issue ...$issues
     */
    public function addIssueObjects(Issue ...$issues): void
    {
        foreach ($issues as $issue) {
            $this->addIssueObject($issue);
        }
    }

    /**
     * Enriches an issue with definition data and adds it to the report.
     *
     * @param Issue $issue
     */
    protected function enrichAndAddIssue(Issue $issue): void
    {
        $codePrefix = static::getName();
        if ($issue->getCode() !== '' && !str_starts_with($issue->getCode(), $codePrefix . '.')) {
            $issue->addCode($codePrefix);
        } elseif ($issue->getCode() === '') {
            $issue->setCode($codePrefix);
        }

        if (!$this->isIssueActive($issue->getCode())) {
            return;
        }

        $info = $this->settings->definition->get($issue->getCode());

        $message = $issue->getMessage();
        $extra = $issue->getextra();
        $extra['code'] = $issue->getCode();

        if (!$message && !empty($info['msg'])) {
            $message = $info['msg'];
        }

        $message = str_replace(
            array_map(fn($key) => '{' . $key . '}', array_keys($extra)),
            array_values($extra),
            $message
        );
        $issue->setMessage($message);

        if (!empty($info['ref'])) {
            $issue->setRef($info['ref']);
        }
        if (!empty($info['help'])) {
            $issue->setHelp($info['help']);
        }

        $this->report->addIssue($issue);
    }

    /**
     * Checks whether a given issue code is active according to the definition.
     *
     * @param string $code
     * @return bool
     */
    protected function isIssueActive(string $code): bool
    {
        return $this->settings->definition->get($code)['active'] ?? true;
    }
}
