<?php


namespace Tuchsoft\MoodleChecklist\Check\Subcheck;

use \Tuchsoft\MoodleChecklist\Action\TokenFinder;
use Tuchsoft\MoodleChecklist\Report\Report;


trait CheckStringInFile
{
    use BaseCheckTrait;

    protected ?TokenFinder $tokenFinder = null;

    protected function checkStringInFile($token, $file, $code, $msg, $severity = Report::SEVERITY_ERROR): void {
        if (!$this->tokenFinder) {
            $this->tokenFinder = new TokenFinder($this->plugin->fullpath);
        }

        if (!file_exists($file)) {
            $this->runtimeError("File not found ($file), cannot check for '{code}'!");
            return;
        }

        if (!is_array($token)) $regex = [$token];

        foreach ($token as $t) {
            $msg = str_replace("{token}", $t, $msg);
            if (empty($this->tokenFinder->searchInFile($t, 'README.md'))) {
                $this->addIssue($code, $severity, $msg);
            }
        }
    }

}