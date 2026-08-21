<?php

namespace Tuchsoft\MoodleChecklist\Process;

class XmllintFixProcess extends AbstractProcess
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
        return trim(shell_exec('which xmllint 2>/dev/null') ?? '') !== '';
    }

    protected function getCommand(): array
    {
        if (!$this->isAvailable()) {
            throw new \Exception('xmllint not found. Install libxml2-utils in the Docker image.');
        }

        $cmd = ['xmllint', '--format', '--output'];
        if (count($this->files) === 1) {
            $cmd[] = $this->files[0];
            $cmd[] = $this->files[0];
            return $cmd;
        }

        // xmllint only supports one output at a time; loop will be handled by the caller.
        $cmd[] = $this->files[0];
        $cmd[] = $this->files[0];
        return $cmd;
    }

    /**
     * @return string[]
     */
    public function getFiles(): array
    {
        return $this->files;
    }
}
