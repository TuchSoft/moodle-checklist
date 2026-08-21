<?php

namespace Tuchsoft\MoodleChecklist\Process\Image;

use Tuchsoft\MoodleChecklist\Process\AbstractProcess;

abstract class AbstractImageOptimizerProcess extends AbstractProcess
{
    protected string $file;

    public function __construct(string $file)
    {
        parent::__construct();
        $this->file = $file;
    }

    abstract protected function getBinaryName(): string;

    /**
     * @return string[]
     */
    abstract protected function getArguments(string $binary): array;

    public function isAvailable(): bool
    {
        return $this->findBinary($this->getBinaryName()) !== null;
    }

    protected function getCommand(): array
    {
        $binary = $this->findBinary($this->getBinaryName());
        if (!$binary) {
            throw new \Exception($this->getBinaryName() . ' not found. Install it via npm.');
        }
        return array_merge([$binary], $this->getArguments($binary));
    }

    private function findBinary(string $name): ?string
    {
        $candidates = [
            __DIR__ . '/../../../node_modules/.bin/' . $name,
            'node_modules/.bin/' . $name,
        ];
        foreach ($candidates as $candidate) {
            $real = realpath($candidate);
            if ($real && is_executable($real)) {
                return $real;
            }
        }
        if (trim(shell_exec('which ' . escapeshellarg($name) . ' 2>/dev/null') ?? '') !== '') {
            return $name;
        }
        return null;
    }
}
