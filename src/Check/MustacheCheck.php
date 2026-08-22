<?php

namespace Tuchsoft\MoodleChecklist\Check;




use Tuchsoft\MoodleChecklist\Check\Subcheck\GetAllFile;
use Tuchsoft\MoodleChecklist\Process\DjlintFixProcess;
use Tuchsoft\MoodleChecklist\Process\IssuesMustacheProcess;
use Tuchsoft\MoodleChecklist\Process\MoodleCISavepointProcess;

class MustacheCheck extends AbstractCheck implements FixableCheckInterface
{

    use GetAllFile;

    public function canFix(): bool
    {
        return (new DjlintFixProcess([]))->isAvailable();
    }

    protected function execute(): void
    {

        $process = new IssuesMustacheProcess($this->getAllFile(ext: ['mustache']), $this->plugin->moodleroot);
        $process->execute();
        if (!$process->isSuccessful()) {
            $this->runtimeError($process->getError());
        }
        $this->addIssueObjects(...$process->getIssues($this->getName()));



    }

    public function fix(bool $apply): bool
    {
        $files = $this->getAllFile(ext: ['mustache']);
        if (empty($files)) {
            return true;
        }

        $process = new DjlintFixProcess($files);
        if (!$process->isAvailable()) {
            $this->io->warning('djlint is not available; skipping Mustache fixer.');
            return false;
        }

        if (!$apply) {
            $this->io->text('Would run djlint --reformat on ' . count($files) . ' Mustache file(s).');
            return true;
        }

        $process->execute();
        $exit = $process->getExitCode();
        if ($exit !== 0 && $exit !== 1) {
            $this->io->warning('Mustache formatting finished with errors: ' . trim($process->getStderr() ?: 'unknown error'));
            return false;
        }
        $this->io->success('Mustache files formatted with djlint.');
        return true;
    }

}
