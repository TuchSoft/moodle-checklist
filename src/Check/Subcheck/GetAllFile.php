<?php

namespace Tuchsoft\MoodleChecklist\Check\Subcheck;

use Exception;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

trait GetAllFile
{

    use BaseCheckTrait;
    /**
     * Recursively gets all file paths within the plugin's full path.
     *
     * @param string $directory The directory to scan.
     * @return array Absolute paths of all files found.
     */
    protected function getAllFile(?string $directory = null, ?array $ext = null): array
    {
        if (!$directory) {
            $directory = $this->plugin->fullpath;
        }

        $files = [];
        if (!is_dir($directory)) {
            $this->runtimeError("Directory not found or not readable: '{$directory}'. Cannot perform full file scan.");
            return [];
        }

        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::LEAVES_ONLY
            );

            /** @var SplFileInfo $item */
            foreach ($iterator as $item) {
                if ($item->isFile() && (!$ext || in_array($item->getExtension(), $ext))) {
                    $files[] = $item->getPathname();
                }
            }
        } catch (Exception $e) {
            $this->runtimeError("Error scanning directory '{$directory}': " . $e->getMessage());
            return [];
        }
        return $files;
    }
}