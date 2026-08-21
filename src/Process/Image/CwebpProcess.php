<?php

namespace Tuchsoft\MoodleChecklist\Process\Image;

class CwebpProcess extends AbstractImageOptimizerProcess
{
    protected function getBinaryName(): string
    {
        return 'cwebp';
    }

    protected function getArguments(string $binary): array
    {
        return [
            '-q', '80',
            $this->file,
            '-o', $this->file,
        ];
    }
}
