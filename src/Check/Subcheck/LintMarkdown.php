<?php


namespace Tuchsoft\MoodleChecklist\Check\Subcheck;

use Tuchsoft\IssueReporter\Issue;
use Tuchsoft\MoodleChecklist\Process\RemarkProcess;


trait LintMarkdown
{
    use BaseCheckTrait;

    public function lintMarkdown($file, $config, $severity_low = Issue::SEVERITY_WARNING, $severity_high = Issue::SEVERITY_ERROR): void
    {

        $process = new RemarkProcess($file, $config, $severity_low, $severity_high);
        if ($process->execute()) {
            $this->addIssueObjects(...$process->getIssues($this->getName()));
        } else {
            $this->runtimeError('Cannot process file: ' . $process->getError(), $file);
        }

    }

}


