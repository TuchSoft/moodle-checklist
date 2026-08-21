<?php

namespace Tuchsoft\MoodleChecklist\Process;

class MoodleCiEslintFixProcess extends AbstractProcess
{
    /**
     * @param string[] $files
     */
    public function __construct(
        private array $files,
        private string $moodleRoot,
        private ?string $configPath = null,
    ) {
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
            // Moodle root node_modules (preferred, avoids duplication in Docker)
            $this->moodleRoot . '/node_modules/.bin/eslint',
            $this->moodleRoot . '/node_modules/eslint/bin/eslint.js',
            // Moodle 5.1+ public docroot layout
            $this->moodleRoot . '/public/node_modules/.bin/eslint',
            $this->moodleRoot . '/public/node_modules/eslint/bin/eslint.js',
            // Moodle 5.2+ nested docroot layout
            $this->moodleRoot . '/../node_modules/.bin/eslint',
            $this->moodleRoot . '/../node_modules/eslint/bin/eslint.js',
            // Local fallback for standalone usage
            __DIR__ . '/../../node_modules/.bin/eslint',
            __DIR__ . '/../../node_modules/eslint/bin/eslint.js',
            'node_modules/.bin/eslint',
            'node_modules/eslint/bin/eslint.js',
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
