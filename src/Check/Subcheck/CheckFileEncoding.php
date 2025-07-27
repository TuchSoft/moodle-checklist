<?php

namespace Tuchsoft\MoodleChecklist\Check\Subcheck;

trait CheckFileEncoding
{
    use BaseCheckTrait;

    /**
     * Checks the encoding of a file.
     *
     * @param string $filePath The full path to the file.
     * @param string $code The issue code.
     * @param string $expectedEncoding The expected encoding (e.g., 'UTF-8').
     * @return void
     */
    public function checkFileEncoding(string $filePath, string $code, string $expectedEncoding = 'UTF-8'): void
    {
        $filename = basename($filePath);
        $content = @file_get_contents($filePath);

        if ($content === false) {
            $this->addWarning(
                $code,
                "Could not read content for encoding check of file '{$filename}'.",
                $filePath
            );
            return;
        }

        if (function_exists('mb_detect_encoding')) {
            $detectedEncoding = mb_detect_encoding($content, 'UTF-8, ISO-8859-1, ASCII', true);
            if ($detectedEncoding === false || strtoupper($detectedEncoding) !== strtoupper($expectedEncoding)) {
                $this->addWarning(
                    $code,
                    "File '{$filename}' has unexpected encoding: '{$detectedEncoding}'. Expected: '{$expectedEncoding}'.",
                    $filePath
                );
            }
        } else {
            $this->addTip(
                $code,
                "PHP 'mbstring' extension is not enabled. Cannot check encoding for '{$filename}'.",
                $filePath
            );
        }
    }
}