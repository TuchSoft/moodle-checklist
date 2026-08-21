<?php

namespace Tuchsoft\MoodleChecklist\Check;




use Tuchsoft\MoodleChecklist\Check\Subcheck\GetAllFile;
use Tuchsoft\MoodleChecklist\Process\GherkinFixProcess;
use Tuchsoft\MoodleChecklist\Process\MoodleCiGruntGherkinlintProcess;


class GherkinLintCheck extends AbstractMoodleCiCheck
{
    use GetAllFile;

    public function canFix(): bool
    {
        return (new GherkinFixProcess([]))->isAvailable();
    }

    protected function execute(): void
    {

        $process = new MoodleCiGruntGherkinlintProcess($this->plugin->fullpath, $this->plugin->moodleroot);
        $process->execute();
        if (!$process->isSuccessful()) {
            $this->runtimeError($process->getError());
        }
        $this->addIssueObjects(...$process->getIssues($this->getName()));


    }

    public function fix(bool $apply): bool
    {
        $files = $this->getAllFile(ext: ['feature']);
        if (empty($files)) {
            return true;
        }

        $process = new GherkinFixProcess($files);
        if (!$process->isAvailable()) {
            $this->io->warning('reformat-gherkin is not available; skipping Gherkin fixer.');
            return false;
        }

        if (!$apply) {
            $this->io->text('Would run reformat-gherkin on ' . count($files) . ' feature file(s).');
            return true;
        }

        $process->execute();
        $exit = $process->getExitCode();
        if ($exit !== 0 && $exit !== 1) {
            $this->io->warning('Gherkin formatting finished with errors: ' . trim($process->getStderr() ?: 'unknown error'));
            return false;
        }
        $this->io->success('Feature files formatted with reformat-gherkin.');
        return true;
    }

}