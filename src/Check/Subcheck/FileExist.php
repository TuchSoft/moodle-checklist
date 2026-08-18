<?php


namespace Tuchsoft\MoodleChecklist\Check\Subcheck;

use Tuchsoft\IssueReporter\Issue;


trait FileExist
{
    use BaseCheckTrait;

    protected function fileExist($file, $code, ?string $msg = null, $severity = Issue::SEVERITY_ERROR ): bool {
        if (!$msg) $msg = "File not found ($file)";
        if (!is_file($file)) {
            $this->addIssue($code, $severity, $msg);
            return false;
        }
        return true;
    }


    protected function dirExist($dir, $code, ?string $msg = null, $severity = Issue::SEVERITY_ERROR ): bool {
        if (!$msg) $msg = "Directory not found ($dir)";
        if (!is_dir($dir)) {
            $this->addIssue($code, $severity, $msg);
            return false;
        }
        return true;
    }

    protected function fileNotExist($file, $code, ?string $msg = null, $severity = Issue::SEVERITY_ERROR ): bool {
        if (!$msg) $msg = "File should not exist ($file)";
        if (is_file($file)) {
            $this->addIssue($code, $severity, $msg);
            return false;
        }
        return true;
    }


    protected function dirNotExist($dir, $code, ?string $msg = null, $severity = Issue::SEVERITY_ERROR ): bool {
        if (!$msg) $msg = "Directory should not exist ($dir)";
        if (is_dir($dir)) {
            $this->addIssue($code, $severity, $msg);
            return false;
        }
        return true;
    }


}