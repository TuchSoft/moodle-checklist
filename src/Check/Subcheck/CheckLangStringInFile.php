<?php


namespace Tuchsoft\MoodleChecklist\Check\Subcheck;

use Tuchsoft\IssueReporter\Issue;


trait CheckLangStringInFile
{
    use CheckStringInFile;
    use LoadLangString;


    protected function checkLangStringInFile($strname, $file, $code, $msg, $severity = Issue::SEVERITY_ERROR): void {
        $this->loadLangString();

        if (!isset($langStrings[$strname]) || empty(trim($langStrings[$strname]))) {
            $this->runtimeError("Missing '$strname' string in lang file, cannot check for $code!");
            return;
        }

        $msg = str_replace('{str}', $langStrings['$strname'], $msg);
        $token = "# {$langStrings['$strname']} (moodle-{$this->plugin->component})";
        $this->checkStringInFile($token, $file, $code, $msg, $severity);

    }

}