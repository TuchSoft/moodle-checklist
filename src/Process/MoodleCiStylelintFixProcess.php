<?php

namespace Tuchsoft\MoodleChecklist\Process;

class MoodleCiStylelintFixProcess extends AbstractProcess
{
    /**
     * @param string[] $files
     */
    public function __construct(private array $files, private ?string $configPath = null)
    {
        parent::__construct();
    }

    public function isAvailable(): bool
    {
        return $this->findStylelint() !== null;
    }

    protected function getCommand(): array
    {
        $stylelint = $this->findStylelint();
        if (!$stylelint) {
            throw new \Exception('stylelint not found. Install it in the Docker image or run `npm install stylelint`.');
        }

        $cmd = [$stylelint, '--fix'];
        if ($this->configPath) {
            $cmd[] = '--config=' . $this->configPath;
        }
        foreach ($this->files as $file) {
            $cmd[] = $file;
        }

        return $cmd;
    }

    private function findStylelint(): ?string
    {
        $candidates = [
            __DIR__ . '/../../node_modules/.bin/stylelint',
            'node_modules/.bin/stylelint',
            __DIR__ . '/../../node_modules/stylelint/bin/stylelint.js',
            'node_modules/stylelint/bin/stylelint.js',
        ];
        foreach ($candidates as $candidate) {
            $real = realpath($candidate);
            if ($real && is_file($real)) {
                return $real;
            }
        }
        return null;
    }
}
