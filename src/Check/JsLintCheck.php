<?php

namespace Tuchsoft\MoodleChecklist\Check;

use Tuchsoft\MoodleChecklist\Process\MoodleCiEslintFixProcess;
use Tuchsoft\MoodleChecklist\Process\MoodleCiEslintProcess;

class JsLintCheck extends AbstractMoodleCiCheck
{
    public function canFix(): bool
    {
        return (new MoodleCiEslintFixProcess([], $this->findConfig()))->isAvailable();
    }

    protected function execute(): void
    {
        $files = $this->collectJsFiles();
        if (empty($files)) {
            return;
        }

        $config = $this->findConfig();
        $process = new MoodleCiEslintProcess($files, $config);
        $process->execute();
        if (!$process->isSuccessful() && $process->getExitCode() !== 1) {
            $this->runtimeError($process->getError() ?? 'eslint failed.');
            return;
        }
        $this->addIssueObjects(...$process->getIssues($this->getName()));
    }

    public function fix(bool $apply): void
    {
        if (!$this->canFix()) {
            $this->io->warning('eslint is not available; skipping JavaScript fixer.');
            return;
        }

        $files = $this->collectJsFiles();
        if (empty($files)) {
            $this->io->text('No JavaScript files to fix.');
            return;
        }

        if (!$apply) {
            $this->io->text('Would run eslint --fix on ' . count($files) . ' JavaScript file(s).');
            return;
        }

        $process = new MoodleCiEslintFixProcess($files, $this->findConfig());
        $process->execute();
        $exit = $process->getExitCode();
        if ($exit !== 0 && $exit !== 1) {
            $this->io->warning('eslint --fix finished with errors: ' . trim($process->getStderr() ?: 'unknown error'));
        } else {
            $this->io->success('JavaScript files formatted with eslint.');
        }

        // AMD modules need rebuilding. If grunt is available, rebuild them.
        $this->rebuildAmd();
    }

    /**
     * @return string[]
     */
    private function collectJsFiles(): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->plugin->fullpath, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );
        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'js') {
                $files[] = $file->getPathname();
            }
        }
        return $files;
    }

    private function findConfig(): ?string
    {
        $root = $this->plugin->moodleroot;
        $candidates = [
            '.eslintrc',
            '.eslintrc.json',
            '.eslintrc.js',
            '.eslintrc.yml',
            '.eslintrc.yaml',
        ];
        foreach ($candidates as $candidate) {
            $path = $root . '/' . $candidate;
            if (is_file($path)) {
                return realpath($path);
            }
        }
        return null;
    }

    private function rebuildAmd(): void
    {
        $amdDir = $this->plugin->fullpath . '/amd/src';
        if (!is_dir($amdDir)) {
            return;
        }
        if (!is_file($this->plugin->moodleroot . '/Gruntfile.js')) {
            $this->io->warning('Cannot rebuild AMD modules: Moodle Gruntfile.js not found.');
            return;
        }
        $process = new \Symfony\Component\Process\Process(
            ['npx', 'grunt', 'amd', '--force', '--no-color'],
            $this->plugin->moodleroot,
            null,
            null,
            120
        );
        $process->run();
        if (!$process->isSuccessful()) {
            $this->io->warning('AMD rebuild (`grunt amd`) failed: ' . trim($process->getErrorOutput() ?: 'unknown error'));
        } else {
            $this->io->success('AMD modules rebuilt.');
        }
    }
}
