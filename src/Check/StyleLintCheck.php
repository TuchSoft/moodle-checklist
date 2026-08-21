<?php

namespace Tuchsoft\MoodleChecklist\Check;




use Tuchsoft\MoodleChecklist\Process\MoodleCiGruntStylelintProcess;
use Tuchsoft\MoodleChecklist\Process\MoodleCiStylelintFixProcess;


class StyleLintCheck extends AbstractMoodleCiCheck
{
    public function canFix(): bool
    {
        return (new MoodleCiStylelintFixProcess([], $this->findConfig()))->isAvailable();
    }

    protected function execute(): void
    {

        $process = new MoodleCiGruntStylelintProcess($this->plugin->fullpath);
        $process->execute();
        if (!$process->isSuccessful()) {
            $this->runtimeError($process->getError());
        }
        $this->addIssueObjects(...$process->getIssues($this->getName()));


    }

    public function fix(bool $apply): void
    {
        $files = $this->collectCssFiles();
        if (empty($files)) {
            $this->io->text('No CSS/SCSS files to fix.');
            return;
        }

        if (!$apply) {
            $this->io->text('Would run stylelint --fix on ' . count($files) . ' file(s).');
            return;
        }

        $process = new MoodleCiStylelintFixProcess($files, $this->findConfig());
        $process->execute();
        $exit = $process->getExitCode();
        if ($exit !== 0 && $exit !== 1) {
            $this->io->warning('stylelint --fix finished with errors: ' . trim($process->getStderr() ?: 'unknown error'));
        } else {
            $this->io->success('CSS/SCSS files formatted with stylelint.');
        }
    }

    /**
     * @return string[]
     */
    private function collectCssFiles(): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->plugin->fullpath, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );
        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->isFile() && in_array($file->getExtension(), ['css', 'scss'], true)) {
                $files[] = $file->getPathname();
            }
        }
        return $files;
    }

    private function findConfig(): ?string
    {
        $root = $this->plugin->moodleroot;
        $candidates = [
            '.stylelintrc',
            '.stylelintrc.json',
            '.stylelintrc.js',
            '.stylelintrc.yml',
            '.stylelintrc.yaml',
        ];
        foreach ($candidates as $candidate) {
            $path = $root . '/' . $candidate;
            if (is_file($path)) {
                return realpath($path);
            }
        }
        return null;
    }
}