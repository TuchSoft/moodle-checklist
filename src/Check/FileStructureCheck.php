<?php

namespace Tuchsoft\MoodleChecklist\Check;

use Tuchsoft\MoodleChecklist\Check\Subcheck\CheckFileEncoding;
use Tuchsoft\MoodleChecklist\Check\Subcheck\CheckFileMimeType;
use Tuchsoft\MoodleChecklist\Check\Subcheck\CheckFileSize;
use Tuchsoft\MoodleChecklist\Check\Subcheck\FileExist;
use Tuchsoft\MoodleChecklist\Check\Subcheck\GetAllFile;

class FileStructureCheck extends AbstractCheck
{
    use FileExist;
    use CheckFileSize;
    use CheckFileEncoding;
    use CheckFileMimeType;
    use GetAllFile;



    /**
     * Executes all file structure and content checks.
     *
     * @return void
     */
    protected function _execute(): void
    {
        $pluginRoot = $this->plugin->fullpath;

        // Define common files to check for encoding and MIME type
        $mime = [
            [
                'types' => ['text/markdown', 'text/html', 'text/plain'],
                'files' => [
                    'README.md',
                    'CHANGELOG.md',
                    'CONTRIBUTING.md',
                    'LICENSE.md'
                ]],
            [
                'types' => ['text/plain'],
                'files' => [
                    '.gitignore'
                ]]
        ];

        // --- Existence checks (Error if missing) ---
        // Directory checks
        if ($this->isActive(($code = 'required-dir-moodleplugin'))) {
            $this->dirExist($pluginRoot . '/.moodleplugin', $code);
        }
        if ($this->isActive(($code = 'required-dir-git'))) {
            $this->dirExist($pluginRoot . '/.git', $code);
        }

        // File checks
        if ($this->isActive(($code = 'required-file-readme'))) {
            $this->fileExist($pluginRoot . '/README.md', $code);
        }
        if ($this->isActive(($code = 'required-file-changelog'))) {
            $this->fileExist($pluginRoot . '/CHANGELOG.md', $code);
        }
        if ($this->isActive(($code = 'required-file-license'))) {
            $this->fileExist($pluginRoot . '/LICENSE.md', $code);
        }
        if ($this->isActive(($code = 'required-file-contributing'))) {
            $this->fileExist($pluginRoot . '/CONTRIBUTING.md', $code);
        }
        if ($this->isActive(($code = 'required-file-gitignore'))) {
            $this->fileExist($pluginRoot . '/.gitignore', $code);
        }

        // --- MIME type and Encoding checks for known files ---
        $codeMime = 'file-mimetype';
        $codeEnc = 'file-encoding';
        if ($this->isActive($codeMime) || $this->isActive($codeEnc)) {
            foreach ($mime as $item) {
                foreach ($item['files'] as $relativePath) {
                    $filePath = $pluginRoot . '/' . $relativePath;
                    if (file_exists($filePath)) { // Only check if the file actually exists
                        if ($this->isActive($codeMime)) {
                            $this->checkFileMimeType($filePath, $codeMime, $item['types']);
                        }
                        if ($this->isActive($codeEnc)) {
                            $this->checkFileEncoding($filePath, $codeEnc);
                        }
                    }
                }
            }
        }


        // --- Forbidden presence checks (Error if present) ---
        $forbiddenItems = [
            'dir' => [
                'node_modules' => 'Node dependencies',
                'vendor' => 'Composer dependencies',
                '.venv' => 'Python virtual environment',
                'env' => 'Python virtual environment',
                'bundle' => 'Ruby gems',
                'pkg/mod' => 'Go module cache',
                'target' => 'Build artifacts',
                'build' => 'Gradle build artifacts',
                'bin' => '.NET build output',
                'obj' => '.NET intermediate build output',
            ],
            'file' => [
                'package.json' => 'Frontend package manager files',
                'package-lock.json' => 'NPM lock file',
                'yarn.lock' => 'Yarn lock file',
                'composer.json' => 'Composer project files',
                'composer.lock' => 'Composer lock file',
                'Pipfile' => 'Pipenv project file',
                'Pipfile.lock' => 'Pipenv lock file',
                'poetry.lock' => 'Poetry lock file',
                'pyproject.toml' => 'Python project configuration',
                'Gemfile.lock' => 'Bundler lock file',
                'go.mod' => 'Go module file',
                'go.sum' => 'Go checksum file',
                'Cargo.toml' => 'Cargo project file',
                'Cargo.lock' => 'Cargo lock file',
                'pom.xml' => 'Maven project file',
                'build.gradle' => 'Gradle build file',
                'gradle.lockfile' => 'Gradle lock file',
                'packages.config' => 'NuGet package references',
                'obj/project.assets.json' => 'NuGet dependency graph file',
                'packages.lock.json' => 'NuGet lock file',
            ],
        ];

        foreach ($forbiddenItems as $type => $items) {
            foreach ($items as $itemPath => $customMessage) {
                $code = 'forbidden-' . $type;
                $message = "Forbidden {$type} '{$itemPath}' found. " . $customMessage . '  should not be part of the plugin (@see https://moodledev.io/general/community/plugincontribution/checklist#dependencies)';

                $fullPath = $pluginRoot . '/' . $itemPath;

                if ($this->isActive($code)) {
                    if ($type === 'dir') {
                        $this->dirNotExist($fullPath, $code, $message);
                    } else {
                        $this->fileNotExist($fullPath, $code, $message);
                    }
                }
            }
        }


        // --- Get all files for subsequent checks ---
        $allFiles = $this->getAllFile();

        // --- Check for files larger than 20MB (Warning) ---
        if ($this->isActive(($code = 'file-size-too-large'))) {
            $maxAllowedBytes = 20 * 1024 * 1024; // 20MB
            foreach ($allFiles as $filePath) {
                // Check if file exists before checking size, though getAllFilesRecursive should ensure this.
                // checkFileSize internally handles file_exists/filesize failure.
                $this->checkFileSize($filePath, $code, 0, $maxAllowedBytes);
            }
        }

        // --- Check for OS-dependent executables (Error) ---
        if ($this->isActive(($code = 'os-dependent-executable'))) {
            $executableExtensions = [
                'pkg', 'exe', 'bat', 'app', 'sh', 'cmd', 'dll', 'so', 'dylib', 'bin', 'out' // Added 'out' for compiled binaries
            ];
            foreach ($allFiles as $filePath) {
                $extension = pathinfo($filePath, PATHINFO_EXTENSION);
                if (in_array(strtolower($extension), $executableExtensions, true)) {
                    $this->addError(
                        $code,
                        "OS-dependent executable file found: '{$filePath}'. These should not be part of the plugin distribution."
                    );
                }
            }
        }

        // --- YUI folder check (Tip) ---
        if ($this->isActive(($code = 'yui-folder-deprecated'))) {
            if (is_dir($pluginRoot . '/yui')) {
                $this->addTip(
                    $code,
                    "The 'yui' folder is present. Consider updating to AMD (Asynchronous Module Definition) for JavaScript."
                );
            }
        }
    }
}