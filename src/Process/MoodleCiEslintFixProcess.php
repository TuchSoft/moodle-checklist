<?php

namespace Tuchsoft\MoodleChecklist\Process;

class MoodleCiEslintFixProcess extends AbstractProcess
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
        return $this->findEslint() !== null;
    }

    protected function getCommand(): array
    {
        $eslint = $this->findEslint();
        if (!$eslint) {
            throw new \Exception('eslint not found. Install it in the Docker image or run `npm install eslint`.');
        }

        $cmd = [$eslint, '--fix'];
        if ($this->configPath) {
            $cmd[] = '--config=' . $this->configPath;
        }
        foreach ($this->files as $file) {
            $cmd[] = $file;
        }

        return $cmd;
    }

    private function findEslint(): ?string
    {
        $candidates = [
            __DIR__ . '/../../node_modules/.bin/eslint',
            'node_modules/.bin/eslint',
        ];
        foreach ($candidates as $candidate) {
            $real = realpath($candidate);
            if ($real && is_executable($real)) {
                return $real;
            }
        }
        return null;
    }
}
