<?php

namespace Tuchsoft\MoodleChecklist\Check;

use Tuchsoft\MoodleChecklist\Check\Subcheck\BaseCheckTrait;
use Tuchsoft\MoodleChecklist\Report\Report;
use Tuchsoft\MoodleChecklist\Settings;

// Renamed from CheckReport

abstract class AbstractCheck
{

    use BaseCheckTrait;

    abstract protected function execute(): void;

    public function __construct(protected Settings $settings)
    {
        $this->report = new Report($this->getName(), $settings);
        $this->plugin = $this->settings->plugin;
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
        return $this->report->isIssueActive($code);
    }


}