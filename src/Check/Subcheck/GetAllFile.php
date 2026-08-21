<?php

namespace Tuchsoft\MoodleChecklist\Check\Subcheck;

use Automattic\IgnoreFile;
use Exception;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

trait GetAllFile
{

    use BaseCheckTrait;

    /**
     * Directories that should never be scanned or fixed, regardless of .gitignore.
     * Implemented as a static method because trait constants cannot be accessed
     * from static helper methods called on the trait itself.
     *
     * @return array<int,string>
     */
    private static function getAlwaysExcludedDirs(): array
    {
        return [
            'node_modules',
            '.git',
            '.moodleplugin',
            'vendor',
            '.venv',
            '.idea',
            '.phpunit.cache',
            '.complex_plans',
            '.agents',
        ];
    }

    /**
     * Files that should never be scanned or fixed, regardless of .gitignore.
     *
     * @return array<int,string>
     */
    private static function getAlwaysExcludedFiles(): array
    {
        return [
            'check_upgrade_savepoints.php',
            '.mcp-savepoint-*.php',
        ];
    }

    /** Cache of IgnoreFile instances keyed by base directory. */
    private static array $ignoreFileCache = [];

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
                if (!$item->isFile()) {
                    continue;
                }
                if ($ext && !in_array($item->getExtension(), $ext, true)) {
                    continue;
                }
                if (self::isPathIgnored($item->getPathname(), $directory)) {
                    continue;
                }
                $files[] = $item->getPathname();
            }
        } catch (Exception $e) {
            $this->runtimeError("Error scanning directory '{$directory}': " . $e->getMessage());
            return [];
        }
        return $files;
    }

    /**
     * Checks whether a path should be ignored by scanners/fixers.
     *
     * Uses a hardcoded safety list plus the nearest .gitignore file.
     *
     * @param string $path Absolute path to check.
     * @param string $baseDirectory Directory against which the path is evaluated.
     * @return bool True if the path should be skipped.
     */
    public static function isPathIgnored(string $path, string $baseDirectory): bool
    {
        $relativePath = self::getPathRelativeToBase($path, $baseDirectory);
        if ($relativePath === null) {
            return true;
        }

        $ignoreFile = self::getCachedIgnoreFile($baseDirectory);
        return $ignoreFile->ignores($relativePath);
    }

    /**
     * Filters an array of absolute paths using the same exclusion rules.
     *
     * @param string[] $paths Absolute paths.
     * @param string $baseDirectory Directory the paths are relative to.
     * @return string[] Paths that are not ignored.
     */
    public static function filterPaths(array $paths, string $baseDirectory): array
    {
        return array_values(array_filter(
            $paths,
            fn(string $path) => !self::isPathIgnored($path, $baseDirectory)
        ));
    }

    private static function getCachedIgnoreFile(string $baseDirectory): IgnoreFile
    {
        $baseDirectory = rtrim($baseDirectory, '/');
        if (isset(self::$ignoreFileCache[$baseDirectory])) {
            return self::$ignoreFileCache[$baseDirectory];
        }

        $ignoreFile = new IgnoreFile();

        foreach (self::getAlwaysExcludedDirs() as $dir) {
            $ignoreFile->add($dir . '/');
        }
        foreach (self::getAlwaysExcludedFiles() as $file) {
            $ignoreFile->add($file);
        }

        $gitignore = $baseDirectory . '/.gitignore';
        if (is_file($gitignore) && is_readable($gitignore)) {
            $ignoreFile->add(file_get_contents($gitignore));
        }

        self::$ignoreFileCache[$baseDirectory] = $ignoreFile;
        return $ignoreFile;
    }

    private static function getPathRelativeToBase(string $path, string $baseDirectory): ?string
    {
        $baseDirectory = rtrim($baseDirectory, '/');
        $prefix = $baseDirectory . '/';
        if (!str_starts_with($path, $prefix)) {
            return null;
        }
        return substr($path, strlen($prefix));
    }
}