<?php


namespace Tuchsoft\MoodleChecklist\Check\Subcheck;

use Tuchsoft\IssueReporter\Issue;
use Tuchsoft\MoodleChecklist\Utils\TokenFinder;


trait CheckStringInFile
{
    use BaseCheckTrait;

    protected ?TokenFinder $tokenFinder = null;

    /**
     * @param string|string[] $token
     * @param string $file
     * @param string $code
     * @param string $msg
     * @param int $severity
     */
    protected function checkStringInFile($token, $file, $code, $msg, $severity = Issue::SEVERITY_ERROR): void {
        if (!$this->tokenFinder) {
            $this->tokenFinder = new TokenFinder($this->plugin->fullpath);
        }

        if (!file_exists($file)) {
            $this->runtimeError("File not found ($file), cannot check for '{code}'!");
            return;
        }

        if (!is_array($token)) $token = [$token];

        foreach ($token as $t) {
            if (empty($this->tokenFinder->searchInFile($t, 'README.md'))) {
                $this->addIssue($code, $severity, str_replace('{token}', $t, $msg));
            }
        }
    }

}