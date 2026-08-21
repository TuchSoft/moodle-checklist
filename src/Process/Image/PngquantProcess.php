<?php

namespace Tuchsoft\MoodleChecklist\Process\Image;

class PngquantProcess extends AbstractImageOptimizerProcess
{
    protected function getBinaryName(): string
    {
        return 'pngquant';
    }

    protected function getArguments(string $binary): array
    {
        return [
            '--force',
            '--skip-if-larger',
            '--quality=65-80',
            '--output', $this->file,
            $this->file,
        ];
    }
}
