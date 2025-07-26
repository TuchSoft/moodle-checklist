<?php


namespace Tuchsoft\MoodleChecklist\Check\Subcheck;

use Tuchsoft\MoodleChecklist\Action\AbstractAction;
use Tuchsoft\MoodleChecklist\Action\LangParser;
use \Tuchsoft\MoodleChecklist\Action\TokenFinder;
use Tuchsoft\MoodleChecklist\Plugin;
use Tuchsoft\MoodleChecklist\Process\RemarkProcess;
use Tuchsoft\MoodleChecklist\Report\Report;


trait LintMarkdown
{
    use BaseCheckTrait;

    public function lintMarkdown($file, $config, $severity_low = Report::SEVERITY_WARNING, $severity_high = Report::SEVERITY_ERROR): void
    {

        $process = new RemarkProcess($file, $config);
        if ($process->execute()) {
            $this->report->addIssues(...$process->getIssues($severity_low, $severity_high));
        } else {
            $this->runtimeError("Cannot process file: " . $process->getError(), $file);
        }

    }

}


