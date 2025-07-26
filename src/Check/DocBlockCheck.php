<?php

namespace Tuchsoft\MoodleChecklist\Check;




use Tuchsoft\MoodleChecklist\Process\MoodleCIDocblockProcess;


class DocBlockCheck extends AbstractMoodleCiCheck
{
    protected function _execute(): void
    {
        if ($this->isActive(($code = 'docblock'))) {
            $process = new MoodleCIDocblockProcess($this->plugin->moodleroot, $this->plugin->fullpath);
            $process->execute();
            if (!$process->isSuccessful()) {
                $this->runtimeError($process->getError());
            }
            $this->report->addIssues(...$process->getIssues($code));
        }

    }

}