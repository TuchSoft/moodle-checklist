<?php

namespace Tuchsoft\MoodleChecklist\Process;

class DjlintFixProcess extends AbstractProcess
{
    /**
     * @param string[] $files
     */
    public function __construct(private array $files)
    {
        parent::__construct();
    }

    public function isAvailable(): bool
    {
        return $this->findDjlint() !== null;
    }

    protected function getCommand(): array
    {
        $djlint = $this->findDjlint();
        if (!$djlint) {
            throw new \Exception('djlint not found. Install it via Composer or pip: `pip install djlint`.');
        }
        return array_merge([$djlint, '--reformat'], $this->files);
    }

    private function findDjlint(): ?string
    {
        $candidates = [
            __DIR__ . '/../../.venv/bin/djlint',
            '.venv/bin/djlint',
        ];
        foreach ($candidates as $candidate) {
            $real = realpath($candidate);
            if ($real && is_executable($real)) {
                return $real;
            }
        }
        if (trim(shell_exec('which djlint 2>/dev/null') ?? '') !== '') {
            return 'djlint';
        }
        return null;
    }
}
