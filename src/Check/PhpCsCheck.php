<?php

namespace Tuchsoft\MoodleChecklist\Check;

use Tuchsoft\MoodleChecklist\Process\MoodleCiPhpcbfProcess;
use Tuchsoft\MoodleChecklist\Process\MoodleCiPhpcsProcess;

class PhpCsCheck extends AbstractMoodleCiCheck implements FixableCheckInterface
{
    public function canFix(): bool
    {
        $phpcbf = realpath(__DIR__ . '/../../vendor/squizlabs/php_codesniffer/bin/phpcbf');
        return $phpcbf !== false;
    }

    protected function execute(): void
    {
        $process = new MoodleCiPhpcsProcess($this->plugin->fullpath, $this->plugin->moodleroot);
        $process->execute();
        $exit = $process->getExitCode();
        if ($exit !== 0 && $exit !== 1 && $exit !== 2) {
            $this->runtimeError($process->getError() ?? 'phpcs failed.');
            return;
        }
        $this->addIssueObjects(...$process->getIssues($this->getName()));
    }

    public function fix(bool $apply): bool
    {
        if (!$this->canFix()) {
            $this->io->warning('phpcbf is not available; skipping PHP fixer.');
            return false;
        }

        $process = new MoodleCiPhpcbfProcess($this->plugin->fullpath, $this->plugin->moodleroot);
        if ($apply) {
            $process->execute();
            $exit = $process->getExitCode();
            if ($exit !== 0 && $exit !== 1) {
                $this->io->warning('phpcbf finished with errors: ' . trim($process->getStderr() ?: 'unknown error'));
                return false;
            }
            $this->io->success('PHP files formatted with phpcbf.');
            return true;
        }

        $this->io->text('Would run phpcbf on ' . $this->plugin->fullpath);
        return true;
    }
}
