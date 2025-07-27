<?php

namespace Tuchsoft\MoodleChecklist\Check;




use Tuchsoft\MoodleChecklist\Process\MoodleCISavepointProcess;

class SavePointCheck extends AbstractMoodleCiCheck
{
    protected function _execute(): void
    {
        if ($this->isActive(($code = 'savepoint'))) {
            $process = new MoodleCISavepointProcess($this->plugin->fullpath);
            $process->execute();
            if (!$process->isSuccessful()) {
                $this->runtimeError($process->getError());
            }
            $this->report->addIssues(...$process->getIssues($code));
        }

    }

}