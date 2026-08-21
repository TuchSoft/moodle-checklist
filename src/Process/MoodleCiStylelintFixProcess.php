<?php

namespace Tuchsoft\MoodleChecklist\Process;

class MoodleCiStylelintFixProcess extends AbstractProcess
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
            // Moodle root node_modules (preferred, avoids duplication in Docker)
            $this->moodleRoot . '/node_modules/.bin/stylelint',
            $this->moodleRoot . '/node_modules/stylelint/bin/stylelint.mjs',
            // Moodle 5.2+ nested docroot layout
            $this->moodleRoot . '/../node_modules/.bin/stylelint',
            $this->moodleRoot . '/../node_modules/stylelint/bin/stylelint.mjs',
            // Local fallback for standalone usage
            __DIR__ . '/../../node_modules/.bin/stylelint',
            __DIR__ . '/../../node_modules/stylelint/bin/stylelint.mjs',
            'node_modules/.bin/stylelint',
            'node_modules/stylelint/bin/stylelint.mjs',
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
