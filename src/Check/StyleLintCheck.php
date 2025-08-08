<?php

namespace Tuchsoft\MoodleChecklist\Check;




use Tuchsoft\MoodleChecklist\Process\MoodleCiGruntStylelintProcess;
use Tuchsoft\MoodleChecklist\Process\MoodleCISavepointProcess;

class StyleLintCheck extends AbstractMoodleCiCheck
{
    protected function execute(): void
    {

        $process = new MoodleCiGruntStylelintProcess($this->plugin->fullpath);
        $process->execute();
        if (!$process->isSuccessful()) {
            $this->runtimeError($process->getError());
        }
        $this->report->addIssues(...$process->getIssues($this->getName()));


    }

}