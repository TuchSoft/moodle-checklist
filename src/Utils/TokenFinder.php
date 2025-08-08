<?php

namespace Tuchsoft\MoodleChecklist\Utils;

use Exception;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class TokenFinder extends AbstractUtils
{

    /**
     * Constructor for FileTokenSearcher.
     *
     * @param string $fullPath The plugin fullPath, expecting 'fullpath'.
     * @throws Exception If the plugin object is invalid or missing 'fullpath'.
     */
    public function __construct(private readonly string $fullPath)
    {
        parent::__construct();
    }

    /**
     * Searches for a regex token in files with specified extensions within the plugin's root directory.
     *
     * @param string $regex The regular expression token to search for.
     * @param array $extensions An array of file extensions to search within (e.g., ['php', 'js', 'css']).
     * @return array|null An array where keys are file paths and values are arrays of found matches,
     * or null if an error occurred.
     */
    public function search(array|string $regex, array $extensions = ['php']): ?array
    {
        $this->checkForError(); // Call base class error checker if needed

        if (empty($regex)) {
            $this->lastError = 'No regex token provided for search.';
            return null;
        }

        $fullPath = rtrim($this->fullPath, '/\\');
        if (!is_dir($fullPath)) {
            $this->lastError = "Plugin root directory not found: {$fullPath}";
            return null;
        }

        $foundMatches = [];

        try {
            $directory = new RecursiveDirectoryIterator($fullPath, FilesystemIterator::SKIP_DOTS);
            $iterator = new RecursiveIteratorIterator($directory, RecursiveIteratorIterator::LEAVES_ONLY);

            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $fileExtension = strtolower($file->getExtension());
                    if (empty($extensions) || in_array($fileExtension, $extensions)) {
                        // $filePath here is already the absolute path
                        $filePath = $file->getRealPath();

                        // To get the relative path from the plugin root:
                        $relativePath = substr($filePath, strlen($fullPath) + 1);

                        // Now pass the relative path to searchInFile (if you really want this)
                        // searchInFile would then need to prepend $this->plugin->fullpath
                        $matchesInFile = $this->searchInFile($regex, $relativePath);

                        if (!empty($matchesInFile)) {
                            $foundMatches[$filePath] = $matchesInFile; // Still store with full path for clarity
                        }
                    }
                }
            }
        } catch (Exception $e) {
            $this->lastError = 'Error during file system iteration: ' . $e->getMessage();
            return null;
        }

        return $foundMatches;
    }

    /**
     * Searches for a regex token within a single file.
     * This version accepts a path relative to the plugin's fullpath.
     *
     * @param string $regex The regular expression token to search for.
     * @param string $relativePath The path to the file relative to the plugin's fullpath.
     * @return array|null An array of found matches, or null if an error occurred reading the file.
     */
    public function searchInFile(array|string $regex, string $relativePath): ?array
    {
        $fullPath = rtrim($this->fullPath, '/\\');
        $filePath = $fullPath . DIRECTORY_SEPARATOR . $relativePath;

        if (!is_readable($filePath)) {
            $this->lastError = "Could not read file (relative path resolution failed or file unreadable): {$filePath}";
            return null;
        }

        $fileContents = file_get_contents($filePath);

        if ($fileContents === false) {
            $this->lastError = "Could not read file contents: {$filePath}";
            return null;
        }

        $matches = [];
        if (!is_array($regex)) $regex = [$regex];
        $regex = join('|',array_map(fn($r) => preg_quote($r, '/'), $regex));

        if (preg_match_all("/$regex/m", $fileContents, $matches)) {
            return $matches[0] ?? [];
        }

        return []; // No matches found
    }
}