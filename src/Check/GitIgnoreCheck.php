<?php

namespace Tuchsoft\MoodleChecklist\Check;

use Tuchsoft\MoodleChecklist\Check\Subcheck\GetAllFile;
use SplFileInfo;
use Tuchsoft\MoodleChecklist\Settings;
use Automattic\IgnoreFile;

/**
 * Checks .gitignore files for the presence of required patterns using a dedicated library.
 *
 * This check verifies that common editor-specific files, junk files, and
 * build artifacts are being ignored. It also checks for conditional
 * patterns like `vendor/` and `node_modules/` only if those directories exist.
 */
class GitIgnoreCheck extends AbstractSingleFileCheck
{
    /**
     * List of paths that MUST be ignored by a .gitignore file.
     * We use specific, concrete file names to test if the patterns are working.
     */
    private const REQUIRED_PATHS = [
        // IDE and editor-specific files
        '.idea/workspace.xml', // IntelliJ user-specific settings
        '.idea/tasks.xml',     // IntelliJ task list
        '.idea/shelf/file',    // IntelliJ local changes shelf
        '.vscode/example', // VS Code workspace stuff (not settings.json )

        // OS and filesystem junk files
        '.DS_Store',           // macOS file system junk
        'Thumbs.db',           // Windows thumbnail cache
        'ehthumbs.db',         // Windows thumbnail cache
        '.Trash-example',       // Linux trash folder

        // Test reports and build artifacts
        'build/report.xml',    // PHP build artifacts
        'coverage/index.html', // PHPUnit code coverage reports
        'example.log', // Generic test report directory
    ];

    /**
     * List of directories that MUST be ignored if they exist in the project.
     */
    private const CONDITIONAL_DIRS = [
        'vendor',      // Composer dependencies
        'node_modules',// Node.js dependencies
    ];

    public function __construct(Settings $settings)
    {
        parent::__construct($settings);
        $this->path = "{$this->plugin->fullpath}/.gitignore";
        $this->mimeType = ['text/plain'];
    }

    protected function executeSingleFile(): void
    {
        $baseDir = dirname($this->path);

        $fileContents = file_get_contents($this->path);
        // The file_get_contents is used only for the comment check, not for the core logic.
        if ($fileContents !== false) {
            // Check for "toptal.com" or "gitignore.io" comments
            if (!(
                str_contains($fileContents, 'toptal.com') ||
                str_contains($fileContents, 'gitignore.io')
            )) {
                $this->addWarning(
                    'not_gitignoreio_toptal',
                    "The file seems not to be generated with 'https://gitignore.io', (@see https://www.toptal.com/developers/gitignore?templates=windows,macos,linux,visualstudiocode,intellij,netbeans,phpunit)"
                );
            }
        }

        $checker = new IgnoreFile();
        $checker->add($fileContents);

        // Check if required files are actually ignored
        foreach (self::REQUIRED_PATHS as $path) {
            if (!$checker->ignores($path)) {
                $path = str_replace('example', '*', $path);
                $this->addWarning(
                    'path_not_ignored',
                    "Path `$path` is not ignored by .gitignore.",
                    $this->path
                );
            }
        }

        // Check for conditional directories
        foreach (self::CONDITIONAL_DIRS as $dir) {
            $targetPath = rtrim($baseDir, '/') . '/' . $dir;
            if (is_dir($targetPath)) {
                // The library correctly handles patterns for directories (e.g., `vendor/`).
                // We check if the directory itself is ignored.
                if (!$checker->ignores($dir)) {
                    $this->addError(
                        'path_not_ignored',
                        "Directory `$dir` exists but is not ignored.",
                        $this->path
                    );
                }
            }
        }
    }
}