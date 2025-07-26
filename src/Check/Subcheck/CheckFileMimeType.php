<?php

namespace Tuchsoft\MoodleChecklist\Check\Subcheck;

use Tuchsoft\MoodleChecklist\Report\Report;
use Tuchsoft\MoodleChecklist\Check\Subcheck\BaseCheckTrait;

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

        if (class_exists(\finfo::class)) {
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($filePath);
            if ($mimeType === false) {
                $this->addWarning(
                    $code,
                    "Could not determine MIME type for file '{$filename}'.",
                    $filePath
                );
            } elseif (!in_array($mimeType, $allowedMimeTypes, true)) {
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