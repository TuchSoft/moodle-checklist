<?php

namespace Tuchsoft\MoodleChecklist\Check;




use Tuchsoft\MoodleChecklist\Process\MoodleCISavepointProcess;

class SavePointCheck extends AbstractMoodleCiCheck
{
    protected function execute(): void
    {

        $process = new MoodleCISavepointProcess($this->plugin->fullpath);
        $process->execute();
        if (!$process->isSuccessful()) {
            $this->runtimeError($process->getError());
        }
        $this->addIssueObjects(...$process->getIssues($this->getName()));


    }

}