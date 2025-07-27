<?php

namespace Tuchsoft\MoodleChecklist\Check;

use Tuchsoft\MoodleChecklist\Check\Subcheck\BaseCheckTrait;
use Tuchsoft\MoodleChecklist\Report\Report;
use Tuchsoft\MoodleChecklist\Settings;

// Renamed from CheckReport

abstract class AbstractCheck
{

    use BaseCheckTrait;

    abstract protected function _execute(): void;

    public function __construct(protected Settings $settings)
    {
        $this->report = new Report($this->getName(), $settings);
        $this->plugin = $this->settings->plugin;
    }


    public function execute(): void {
        $this->_execute();
        $this->report->setCompleted();
    }
    public function getName(): string
    {
        $classname = explode('\\', get_class($this));
        return strtolower(str_replace('Check', '', array_pop($classname)));
    }

    protected function isActive(string $code): bool {
        return $this->report->isIssueActive($code);
    }


}