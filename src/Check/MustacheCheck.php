<?php

namespace Tuchsoft\MoodleChecklist\Check;




use Tuchsoft\MoodleChecklist\Check\Subcheck\GetAllFile;
use Tuchsoft\MoodleChecklist\Process\IssuesMustacheProcess;
use Tuchsoft\MoodleChecklist\Process\MoodleCISavepointProcess;

class MustacheCheck extends AbstractMoodleCiCheck
{

    use GetAllFile;
    protected function execute(): void
    {

        $process = new IssuesMustacheProcess($this->getAllFile(ext: ['mustache']), $this->plugin->moodleroot);
        $process->execute();
        if (!$process->isSuccessful()) {
            $this->runtimeError($process->getError());
        }
        $this->addIssueObjects(...$process->getIssues($this->getName()));




    }

}