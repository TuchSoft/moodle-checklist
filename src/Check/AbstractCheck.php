<?php

namespace Tuchsoft\MoodleChecklist\Check;

use Tuchsoft\IssueReporter\Report;
use Tuchsoft\MoodleChecklist\Check\Subcheck\BaseCheckTrait;
use Tuchsoft\MoodleChecklist\Settings;
use Tuchsoft\MoodleChecklist\Utils\InputOutput;

// Renamed from CheckReport

abstract class AbstractCheck
{

    use BaseCheckTrait;

    protected InputOutput $io;

    abstract protected function execute(): void;

    public function __construct(protected Settings $settings)
    {
        $this->report = new Report($this->getName(), $this->settings->plugin->fullpath);
        $this->plugin = $this->settings->plugin;
        // A default no-op IO to avoid null checks in fixers; use setIo() to inject the real one.
        $this->io = new InputOutput(
            new \Symfony\Component\Console\Input\ArrayInput([]),
            new \Symfony\Component\Console\Output\NullOutput(),
            $settings
        );
    }

    public function setIo(InputOutput $io): void
    {
        $this->io = $io;
    }


    public function run(): void {
        if (!$this->isActive()) return;
        $this->report->start();
        $this->execute();
        $this->report->complete();
    }

    public static function getName(): string
    {
        $classname = explode('\\', static::class);
        return strtolower(str_replace('Check', '', array_pop($classname)));
    }

    public function isActive(?string $code = null): bool {
        $code = $code ? ($this->getName().'.'.$code) : $this->getName();
        return $this->isIssueActive($code);
    }

}