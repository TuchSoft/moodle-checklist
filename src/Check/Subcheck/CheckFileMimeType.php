<?php

namespace Tuchsoft\MoodleChecklist\Check\Subcheck;

use finfo;

trait CheckFileMimeType
{
    use BaseCheckTrait;

    /**
     * Checks the MIME type of a file against a list of allowed types.
     *
     * @param string $filePath The full path to the file.
     * @param string $code The issue code.
     * @param array $allowedMimeTypes An array of allowed MIME types (e.g., ['text/markdown', 'text/html']).
     * @return void
     */
    public function checkFileMimeType(string $filePath, string $code, array $allowedMimeTypes): void
    {
        if (empty($allowedMimeTypes)) {
            return; // No allowed MIME types specified, so no check can be performed.
        }

        $filename = basename($filePath);

        if (class_exists(finfo::class)) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($filePath);
            if ($mimeType === false) {
                $this->addWarning(
                    $code,
                    "Could not determine MIME type for file '{$filename}'.",
                    $filePath
                );
            } elseif (!in_array($mimeType, $allowedMimeTypes, true)) {
                // Some systems report Markdown files as text/plain; accept that for .md files
                // when text/markdown is among the allowed types.
                $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                if ($mimeType === 'text/plain' && $extension === 'md' && in_array('text/markdown', $allowedMimeTypes, true)) {
                    return;
                }

                $this->addWarning(
                    $code,
                    "File '{$filename}' has unexpected MIME type: '{$mimeType}'. Expected one of: '" . implode("', '", $allowedMimeTypes) . "'.",
                    $filePath
                );
            }
        } else {
            $this->addTip(
                $code,
                "PHP 'fileinfo' extension is not enabled. Cannot check MIME type for '{$filename}'.",
                $filePath
            );
        }
    }
}