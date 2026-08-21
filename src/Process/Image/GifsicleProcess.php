<?php

namespace Tuchsoft\MoodleChecklist\Process\Image;

class GifsicleProcess extends AbstractImageOptimizerProcess
{
    protected function getBinaryName(): string
    {
        return 'gifsicle';
    }

    protected function getArguments(string $binary): array
    {
        return [
            '--optimize=3',
            '--no-warnings',
            '-o', $this->file,
            $this->file,
        ];
    }
}
