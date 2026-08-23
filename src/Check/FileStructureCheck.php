<?php

namespace Tuchsoft\MoodleChecklist\Check;

use MoodlePluginCI\PluginValidate\PluginValidate;
use MoodlePluginCI\PluginValidate\Requirements\RequirementsResolver;
use Tuchsoft\MoodleChecklist\Check\Subcheck\CheckFileEncoding;
use Tuchsoft\MoodleChecklist\Check\Subcheck\CheckFileMimeType;
use Tuchsoft\MoodleChecklist\Check\Subcheck\CheckFileSize;
use Tuchsoft\MoodleChecklist\Check\Subcheck\FileExist;
use Tuchsoft\MoodleChecklist\Check\Subcheck\GetAllFile;
use Tuchsoft\IssueReporter\Issue;
use Tuchsoft\MoodleChecklist\Check\FixableCheckInterface;

class FileStructureCheck extends AbstractCheck implements FixableCheckInterface
{
    use FileExist;
    use CheckFileSize;
    use CheckFileEncoding;
    use CheckFileMimeType;
    use GetAllFile;

    private const FILE_TEMPLATES = [
        'README.md'       => "# {component}\n\n<!-- TODO: Replace this placeholder with a real plugin description. -->\n",
        'CHANGELOG.md'    => "# Changelog\n\n<!-- TODO: Replace this placeholder with actual changelog entries. -->\n\n## [Unreleased]\n\n- Initial release.\n",
        'LICENSE.md'      => "<!-- TODO: Replace this placeholder with the actual license text. -->\n",
        'CONTRIBUTING.md' => "# Contributing\n\n<!-- TODO: Replace this placeholder with actual contribution guidelines. -->\n",
        '.gitignore'      => "# TODO: Add project-specific ignore patterns below; run the gitignore fixer to populate standard patterns.\n",
    ];

    private const ENCODED_FILES = [
        'README.md',
        'CHANGELOG.md',
        'CONTRIBUTING.md',
        'LICENSE.md',
        '.gitignore',
    ];

    public function canFix(): bool
    {
        return true;
    }

    public function getFixerGroup(): string
    {
        return 'bootstrap';
    }

    public function getFixerDependencies(): array
    {
        return [];
    }

    public function fix(bool $apply): bool
    {
        $pluginRoot = $this->plugin->fullpath;
        $success = true;

        if ($this->isActive('required-dir-moodleplugin')) {
            $success = $this->ensureDirectory($pluginRoot . '/.moodleplugin', $apply) && $success;
        }

        $requiredFiles = [
            'required-file-readme'       => 'README.md',
            'required-file-changelog'    => 'CHANGELOG.md',
            'required-file-license'      => 'LICENSE.md',
            'required-file-contributing' => 'CONTRIBUTING.md',
            'required-file-gitignore'    => '.gitignore',
        ];

        foreach ($requiredFiles as $code => $relativePath) {
            if (!$this->isActive($code)) {
                continue;
            }

            $template = self::FILE_TEMPLATES[$relativePath] ?? '';
            $template = str_replace('{component}', $this->plugin->component, $template);
            $success = $this->ensureFile($pluginRoot . '/' . $relativePath, $template, $apply) && $success;
        }

        if ($this->isActive('file-encoding')) {
            foreach (self::ENCODED_FILES as $relativePath) {
                $filePath = $pluginRoot . '/' . $relativePath;
                if (is_file($filePath)) {
                    $success = $this->reencodeFileToUtf8($filePath, $apply) && $success;
                }
            }
        }

        return $success;
    }

    private function ensureDirectory(string $path, bool $apply): bool
    {
        if (is_dir($path)) {
            return true;
        }

        if (!$apply) {
            $this->io->text("Would create directory {$path}");
            return true;
        }

        if (!mkdir($path, 0755, true) && !is_dir($path)) {
            $this->io->error("Failed to create directory {$path}");
            return false;
        }

        $this->io->text("Created directory {$path}");
        return true;
    }

    private function ensureFile(string $path, string $content, bool $apply): bool
    {
        if (is_file($path)) {
            return true;
        }

        if (!$apply) {
            $this->io->text("Would create file {$path}");
            return true;
        }

        if (file_put_contents($path, $content) === false) {
            $this->io->error("Failed to create file {$path}");
            return false;
        }

        $this->io->text("Created file {$path}");
        return true;
    }

    private function reencodeFileToUtf8(string $path, bool $apply): bool
    {
        if (!function_exists('mb_detect_encoding') || !function_exists('iconv')) {
            $this->io->warning('PHP mbstring/iconv extension is missing; cannot re-encode files to UTF-8.');
            return false;
        }

        $content = file_get_contents($path);
        if ($content === false) {
            $this->io->error("Failed to read file {$path}");
            return false;
        }

        $detected = mb_detect_encoding($content, 'UTF-8, ISO-8859-1, ASCII', true);
        if ($detected === false || in_array(strtoupper($detected), ['UTF-8', 'ASCII'], true)) {
            return true;
        }

        if (!$apply) {
            $this->io->text("Would re-encode {$path} from {$detected} to UTF-8");
            return true;
        }

        $converted = @iconv($detected, 'UTF-8//TRANSLIT//IGNORE', $content);
        if ($converted === false) {
            $this->io->error("Failed to convert {$path} from {$detected} to UTF-8");
            return false;
        }

        if (file_put_contents($path, $converted) === false) {
            $this->io->error("Failed to write file {$path}");
            return false;
        }

        $this->io->text("Re-encoded {$path} from {$detected} to UTF-8");
        return true;
    }


    /**
     * Executes all file structure and content checks.
     *
     * @return void
     */
    protected function execute(): void
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
                        $this->dirNotExist($fullPath, $code, $message, Issue::SEVERITY_WARNING);
                    } else {
                        $this->fileNotExist($fullPath, $code, $message, Issue::SEVERITY_WARNING);
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

        if ($this->isActive(($code = 'moodle-plugin-ci.validate'))) {
            $plugin = $this->settings->plugin;
            $resolver     = new RequirementsResolver();
            $requirements = $resolver->resolveRequirements($plugin, $this->settings->moodle->getBranch());

            $validate = new PluginValidate($plugin, $requirements);
            $validate->verifyRequirements();
            foreach($validate->messages as $message) {
                if (str_starts_with($message, ($start = '<fg=red>X '))) {
                    $this->addError($code, substr($message, strlen($start), -3));
                }
            }
        }

    }
}