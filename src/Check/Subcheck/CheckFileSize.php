<?php

namespace Tuchsoft\MoodleChecklist\Check\Subcheck;

use Tuchsoft\MoodleChecklist\Report\Report;
use Tuchsoft\MoodleChecklist\Check\Subcheck\BaseCheckTrait;

trait CheckFileSize
{
    use BaseCheckTrait;

    /**
     * Checks if a file's size is within specified minimum and maximum limits.
     *
     * @param string $filePath The full path to the file.
     * @param string $code The issue code.
     * @param int $minSize The minimum allowed file size in bytes.
     * @param int $maxSize The maximum allowed file size in bytes.
     * @return void
     */
    public function checkFileSize(string $filePath, string $code, int $minSize = 0, int $maxSize = PHP_INT_MAX): void
    {
        $filename = basename($filePath);
        $fileSize = @filesize($filePath);

        if ($fileSize === false) {
            $this->addWarning(
                $code,
                "Could not determine size for file '{$filename}' (permission issue or empty file).",
                $filePath
            );
            return;
        }

        if ($fileSize < $minSize) {
            $this->addError(
                $code,
                "File '{$filename}' is too small ({$fileSize} bytes). Minimum required: {$minSize} bytes.",
                $filePath
            );
        }

        if ($fileSize > $maxSize) {
            $this->addError(
                $code,
                "File '{$filename}' is too large ({$fileSize} bytes). Maximum allowed: {$maxSize} bytes.",
                $filePath
            );
        }
    }
}