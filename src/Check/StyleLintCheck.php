<?php

namespace Tuchsoft\MoodleChecklist\Check;




use Tuchsoft\MoodleChecklist\Process\MoodleCiGruntStylelintProcess;


class StyleLintCheck extends AbstractMoodleCiCheck
{
    protected function execute(): void
    {

        $process = new MoodleCiGruntStylelintProcess($this->plugin->fullpath);
        $process->execute();
        if (!$process->isSuccessful()) {
            $this->runtimeError($process->getError());
        }
        $this->addIssueObjects(...$process->getIssues($this->getName()));


    }

}