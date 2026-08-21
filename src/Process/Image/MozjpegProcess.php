<?php

namespace Tuchsoft\MoodleChecklist\Process\Image;

class MozjpegProcess extends AbstractImageOptimizerProcess
{
    protected function getBinaryName(): string
    {
        return 'mozjpeg';
    }

    protected function getArguments(string $binary): array
    {
        return [
            '-quality', '80',
            '-outfile', $this->file,
            $this->file,
        ];
    }
}
