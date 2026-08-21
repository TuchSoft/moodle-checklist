<?php

namespace Tuchsoft\MoodleChecklist\Process;

class PrettierFixProcess extends AbstractProcess
{
    /**
     * @param string[] $files
     * @param string[] $extraArgs Extra arguments passed to prettier (e.g. --parser, --tab-width).
     */
    public function __construct(private array $files, private array $extraArgs = [])
    {
        parent::__construct();
    }

    public function isAvailable(): bool
    {
        return $this->findPrettier() !== null;
    }

    protected function getCommand(): array
    {
        $prettier = $this->findPrettier();
        if (!$prettier) {
            throw new \Exception('prettier not found. Install it via npm.');
        }

        return array_merge(
            [$prettier, '--write'],
            $this->extraArgs,
            $this->files
        );
    }

    private function findPrettier(): ?string
    {
        $candidates = [
            __DIR__ . '/../../node_modules/.bin/prettier',
            'node_modules/.bin/prettier',
            __DIR__ . '/../../node_modules/prettier/bin-prettier.js',
            'node_modules/prettier/bin-prettier.js',
        ];
        foreach ($candidates as $candidate) {
            $real = realpath($candidate);
            if ($real && is_file($real)) {
                return $real;
            }
        }
        if (trim(shell_exec('which npx 2>/dev/null') ?? '') !== '') {
            return 'npx';
        }
        return null;
    }
}
