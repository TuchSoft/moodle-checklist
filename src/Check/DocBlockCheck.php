<?php

namespace Tuchsoft\MoodleChecklist\Check;




use Tuchsoft\MoodleChecklist\Process\MoodleCIDocblockProcess;


class DocBlockCheck extends AbstractMoodleCiCheck
{
    protected function execute(): void
    {
        $process = new MoodleCIDocblockProcess($this->plugin->moodleroot, $this->plugin->fullpath);
        $process->execute();
        if (!$process->isSuccessful()) {
            $this->runtimeError($process->getError());
        }
        $this->addIssueObjects(...$process->getIssues($this->getName()));
    }

}