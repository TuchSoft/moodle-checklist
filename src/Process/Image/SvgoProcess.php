<?php

namespace Tuchsoft\MoodleChecklist\Process\Image;

class SvgoProcess extends AbstractImageOptimizerProcess
{
    protected function getBinaryName(): string
    {
        return 'svgo';
    }

    protected function getArguments(string $binary): array
    {
        return [
            '-i', $this->file,
            '-o', $this->file,
        ];
    }
}
