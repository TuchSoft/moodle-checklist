<?php

namespace Tuchsoft\MoodleChecklist\Check;




use Tuchsoft\MoodleChecklist\Process\MoodleCiGruntGherkinlintProcess;


class GherkinLintCheck extends AbstractMoodleCiCheck
{
    protected function execute(): void
    {

        $process = new MoodleCiGruntGherkinlintProcess($this->plugin->fullpath);
        $process->execute();
        if (!$process->isSuccessful()) {
            $this->runtimeError($process->getError());
        }
        $this->report->addIssues(...$process->getIssues($this->getName()));


    }

}