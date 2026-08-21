<?php

namespace Tuchsoft\MoodleChecklist;

use Composer\Autoload\ClassLoader;
use Exception;
use ReflectionClass;

use Tuchsoft\IssueReporter\Issue;
use Tuchsoft\IssueReporter\Report;
use Tuchsoft\MoodleChecklist\Check\AbstractCheck;
use Tuchsoft\MoodleChecklist\Process\ParallelCheckProcess;
use Tuchsoft\MoodleChecklist\Utils\InputOutput;

class Checker
{
    private Settings $settings;
    private array $checks = [];
    private ClassLoader $autoloader;

    /**
     * Creates a synthetic report containing a runtime-error issue for a check.
     *
     * @param string $check The check class name.
     * @param string $message The error message.
     * @return Report
     */
    private function createRuntimeErrorReport(string $check, string $message): Report
    {
        $report = new Report($check::getName(), $this->settings->plugin->fullpath);
        $report->start();
        $report->addIssue(new Issue('runtime-error', Issue::SEVERITY_ERROR, $message, '.'));
        $report->complete();
        return $report;
    }

    public function __construct(Settings $settings, private InputOutput $io)
    {
        $this->settings = $settings;


        if ($this->settings->plugin->hasError()) {
            throw new Exception('Failed to parse plugin info: ' . $this->settings->plugin->getErrorMessage());
        }

        $this->autoloader = $this->getComposerAutoloader();

        $this->discoverChecks();
        $this->io->debug('Check discovery process completed.');
    }

    private function getComposerAutoloader(): ClassLoader
    {
        $autoloader = null;
        $this->io->debug('Searching for Composer autoloader...');
        foreach (spl_autoload_functions() as $function) {
            if (is_array($function) && $function[0] instanceof ClassLoader) {
                $autoloader = $function[0];
                break;
            }
        }

        if (!$autoloader) {
            throw new Exception('Composer autoloader not found.');
        }

        return $autoloader;
    }

    private function discoverChecks(): void
    {
        $this->io->debug('Discovering checks...');
        $checkSources = [
            'Tuchsoft\MoodleChecklist\Check' => __DIR__ . '/Check',
        ];

        $customCheckPath = $this->settings->customChecks;
        foreach ($customCheckPath as $namespace => $path) {
            if ($namespace && is_string($namespace) && $path && is_dir($path)) {
                $checkSources[$namespace] = $path;
                $this->io->debug("Added custom check source: {$namespace} => {$path}");
            } else {
                $this->io->debug("Skipped invalid custom check source for namespace '{$namespace}'.");
            }
        }

        $this->io->debug('Registering namespaces...');
        $this->registerNamespaces($checkSources);

        if ($this->settings->isSingle()) {
            $this->io->debug("Single check mode activated for '{$this->settings->execute}'.");
            $this->loadSingleCheck($this->settings->execute);
            return;
        }

        $this->io->debug('Searching for all checks...');
        $foundChecks = $this->findChecks($checkSources);
        $this->checks = array_unique($foundChecks);
        $this->io->debug('Found ' . count($this->checks) . ' checks.');
    }

    /**
     * @param array<string, string> $namespaces
     */
    private function registerNamespaces(array $namespaces): void
    {
        foreach ($namespaces as $namespace => $directory) {
            if (!$this->isNamespaceRegistered($namespace)) {
                $this->io->debug("Registering namespace '{$namespace}' for directory '{$directory}'.");
                $this->autoloader->addPsr4($namespace . '\\', $directory);
            }
        }
    }

    private function isNamespaceRegistered(string $namespace): bool
    {
        $psr4Prefixes = array_keys($this->autoloader->getPrefixesPsr4());
        $namespaceWithSlash = $namespace . '\\';
        return in_array($namespaceWithSlash, $psr4Prefixes, true);
    }

    /**
     * @param array<string, string> $prefixes
     * @return string[]
     */
    private function findChecks(array $prefixes): array
    {
        $checks = [];
        foreach ($prefixes as $namespace => $path) {
            $this->io->debug("Scanning path '{$path}' for checks in namespace '{$namespace}'.");
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)
            );

            /** @var \SplFileInfo $file */
            foreach ($files as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $this->io->debug("Processing file: '{$file->getFilename()}'.");
                    $className = $this->getCheckClassName($namespace, $file->getPathname());
                    if ($className) {
                        if ($this->isCheck($className)) {
                            $checks[] = $className;
                            $this->io->debug("Found  check: '{$className}'.");
                        } else {
                            $this->io->debug("Class '{$className}' is not a valid  check.");
                        }
                    } else {
                        $this->io->debug("File '{$file->getFilename()}' does not appear to be a check class.");
                    }
                }
            }
        }
        $this->io->debug('Finished scanning all prefixes.');
        return $checks;
    }

    private function getCheckClassName(string $namespace, string $filepath): ?string
    {
        $className = "$namespace\\".str_replace( '.php', '', basename($filepath));

        if (str_ends_with($className, 'Check') && class_exists($className, true)) {
            return $className;
        }

        return null;
    }

    private function isCheck(string $className): bool
    {
        try {
            $reflection = new ReflectionClass($className);
            return $reflection->isSubclassOf(AbstractCheck::class) &&
                !$reflection->isAbstract() &&
                $reflection->isInstantiable();
        } catch (Exception $e) {
            $this->io->debug("Exception caught while reflecting '{$className}': " . $e->getMessage());
            return false;
        }
    }

    private function loadSingleCheck(string $className): void
    {
        $this->io->debug("Attempting to load single check: '{$className}'.");
        if (!$this->isCheck($className)) {
            throw new Exception("Class '{$className}' is not a valid  check.");
        }

        $this->checks = [$className];
        $this->io->debug("Single check '{$className}' loaded successfully.");
    }

    /**
     * Executes all discovered checks and aggregates their reports into a single Reporter instance.
     *
     * @return Report A fully populated Report instance ready for printing.
     */
    /**
     * @return string[] Discovered check class names.
     */
    public function getChecks(): array
    {
        return $this->checks;
    }

    public function runChecks(): Report
    {
        $reports = [];
        $finalReport = new Report($this->settings->plugin->component, $this->settings->plugin->fullpath);
        $finalReport->start();

        $this->io->debug('The following checks have been discovered:');
        $this->io->printList(array_map(fn($c) => $c::getName(), $this->checks), level: Settings::VERBOSITY_DEBUG);


        $this->checks = array_filter($this->checks, fn($c) => $this->settings->definition->get($c::getName())['active'] ?? true);
        $this->io->debug('Filtered ' . count($this->checks) . ' active checks.');

        $this->io->verbose('The following checks are active and will be executed:');
        $this->io->printList(array_map(fn($c) => $c::getName(), $this->checks), level: Settings::VERBOSITY_VERBOSE);

        if (empty($this->checks)) {
            $this->io->verbose('No active checks to run.');
            $finalReport->complete();
            return $finalReport;
        }

        if ($this->settings->execute == Settings::PARALLEL_EXECUTION) {
            $this->io->verbose('Executing checks in parallel mode.');
            $options = [];
            foreach ($this->settings->checkExclude as $str) {
                $options[] = '--exclude-check';
                $options[] = $str;
                $this->io->debug("Adding exclude-check option: '{$str}'.");
            }
            foreach ($this->settings->checkInclude as $str) {
                $options[] = '--include-check';
                $options[] = $str;
                $this->io->debug("Adding include-check option: '{$str}'.");
            }
            foreach ($this->settings->customChecks as $namespace => $path) {
                $options[] = '--additional-check';
                $options[] = "$namespace:$path";
                $this->io->debug("Adding custom check option: '{$namespace}:{$path}'.");
            }

            $this->io->debug('Starting parallel check processes.');
            $process = new ParallelCheckProcess($this->settings->plugin->fullpath, $this->checks, $options);
            $process->execute();

            foreach ($this->checks as $i => $check) {
                $this->io->debug("Processing output from process #{$i} ({$check}).");
                $stdout = $process->getAllStdout()[$i] ?? null;
                $stderr = trim($process->getAllStderr()[$i] ?? '');

                try {
                    if ($stderr !== '') {
                        throw new Exception("An error occurred in an underlying process: {$stderr}");
                    }
                    if (!$stdout) {
                        $this->io->debug("Process #{$i} returned no stdout, skipping.");
                        continue;
                    }
                    if (!($data = json_decode($stdout, true))) {
                        throw new Exception("Unable to parse stdout, probably an error occurred: $stdout");
                    }
                    $reports[] = Report::fromJson($data);
                    $this->io->debug("Report from process #{$i} parsed successfully.");
                } catch (Exception $e) {
                    $this->io->debug("Process #{$i} failed: " . $e->getMessage());
                    $reports[] = $this->createRuntimeErrorReport($check, $e->getMessage());
                }
            }
        }  else {
            $this->io->verbose('Executing checks in sequential mode.');
            foreach ($this->checks as $check) {
                try {
                    $this->io->debug("Instantiating check '{$check}'.");
                    $checkInstance = new $check($this->settings);
                    $checkInstance->setIo($this->io);
                    $this->io->verbose('Running check: ' . $checkInstance->getName());
                    $checkInstance->run();
                    $report = $checkInstance->getReport();
                    $this->io->debug('Check ' . $checkInstance->getName() . ' finished. Report generated.');
                    if ($this->settings->isSingle()) {
                        $this->io->verbose('Single check mode complete.');
                        return $report;
                    }
                    $reports[] = $report;
                } catch (Exception $e) {
                    // Log or handle the exception for a specific check.
                    error_log("Failed to run check '{$check}': " . $e->getMessage());
                    $this->io->debug("Exception during check '{$check}': " . $e->getMessage());
                }
            }
        }
        $this->io->debug('All checks finished. Merging reports.');
        $finalReport->complete();
        $finalReport->mergeIn(...$reports);
        $this->io->debug('Final report generated.');
        return $finalReport;
    }
}