<?php


namespace Tuchsoft\MoodleChecklist\Check\Subcheck;

use Tuchsoft\IssueReporter\Issue;


trait CheckLangStringInFile
{
    use CheckStringInFile;
    use LoadLangString;


    /**
     * @param string $strname Name of the language string to look for.
     * @param string $file File to search in (informational; README.md is always used).
     * @param string $code Issue code to report if the string is missing.
     * @param string $msg Message template for the issue.
     * @param int $severity One of the Issue::SEVERITY_* constants.
     */
    protected function checkLangStringInFile($strname, $file, $code, $msg, $severity = Issue::SEVERITY_ERROR): void {
        $this->loadLangString();

        if (!isset($this->langStrings[$strname]) || empty(trim($this->langStrings[$strname]))) {
            $this->runtimeError("Missing '$strname' string in lang file, cannot check for $code!");
            return;
        }

        $msg = str_replace('{str}', $this->langStrings[$strname], $msg);
        $token = "# {$this->langStrings[$strname]} (moodle-{$this->plugin->component})";
        $this->checkStringInFile($token, $file, $code, $msg, $severity);

    }

}