<?php

namespace Tuchsoft\MoodleChecklist\Process;

class GherkinFixProcess extends AbstractProcess
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
        return $this->findReformatGherkin() !== null;
    }

    protected function getCommand(): array
    {
        $reformat = $this->findReformatGherkin();
        if (!$reformat) {
            throw new \Exception('reformat-gherkin not found. Install it via Composer or pip: `pip install reformat-gherkin`.');
        }
        return array_merge([$reformat], $this->files);
    }

    private function findReformatGherkin(): ?string
    {
        $candidates = [
            __DIR__ . '/../../.venv/bin/reformat-gherkin',
            '.venv/bin/reformat-gherkin',
        ];
        foreach ($candidates as $candidate) {
            $real = realpath($candidate);
            if ($real && is_executable($real)) {
                return $real;
            }
        }
        if (trim(shell_exec('which reformat-gherkin 2>/dev/null') ?? '') !== '') {
            return 'reformat-gherkin';
        }
        return null;
    }
}
