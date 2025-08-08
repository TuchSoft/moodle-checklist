<?php

namespace Tuchsoft\MoodleChecklist;

use Composer\Autoload\ClassLoader;
use Exception;
use ReflectionClass;

use Tuchsoft\MoodleChecklist\Check\AbstractCheck;
use Tuchsoft\MoodleChecklist\Process\ParallelCheckProcess;
use Tuchsoft\MoodleChecklist\Report\Report;
use Tuchsoft\MoodleChecklist\Utils\InputOutput;

class Checker
{
    private Settings $settings;
    private array $checks = [];
    private ClassLoader $autoloader;

    public function __construct(Settings $settings, private InputOutput $io)
    {
        $this->settings = $settings;
        $this->io->debug('Checker initialized with provided settings.');

        if ($this->settings->plugin->hasError()) {
            throw new Exception('Failed to parse plugin info: ' . $this->settings->plugin->getErrorMessage());
        }

        $this->autoloader = $this->getComposerAutoloader();
        $this->io->debug('Composer autoloader found and stored.');

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
                $this->io->debug('Found Composer autoloader.');
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
        $checkSources = [
            'Tuchsoft\MoodleChecklist\Check' => __DIR__ . '/Check',
        ];

        $customCheckPath = $this->settings->customChecks;
        $this->io->debug('Checking for custom check paths...');
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

        $this->io->debug('Searching for all concrete checks...');
        $foundChecks = $this->findConcreteChecks($checkSources);
        $this->checks = array_unique($foundChecks);
        $this->io->debug('Found ' . count($this->checks) . ' unique concrete checks.');
    }

    private function registerNamespaces(array $namespaces): void
    {
        foreach ($namespaces as $namespace => $directory) {
            if (!$this->isNamespaceRegistered($namespace)) {
                $this->io->debug("Registering namespace '{$namespace}' for directory '{$directory}'.");
                $this->autoloader->addPsr4($namespace . '\\', $directory);
            } else {
                $this->io->debug("Namespace '{$namespace}' is already registered, skipping.");
            }
        }
    }

    private function isNamespaceRegistered(string $namespace): bool
    {
        $psr4Prefixes = array_keys($this->autoloader->getPrefixesPsr4());
        $namespaceWithSlash = $namespace . '\\';
        return in_array($namespaceWithSlash, $psr4Prefixes, true);
    }

    private function findConcreteChecks(array $prefixes): array
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
                        $this->io->debug("Candidate class name '{$className}'.");
                        if ($this->isConcreteCheck($className)) {
                            $checks[] = $className;
                            $this->io->debug("Found concrete check: '{$className}'.");
                        } else {
                            $this->io->debug("Class '{$className}' is not a valid concrete check.");
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
        $this->io->debug("Generating class name for '{$filepath}'.");
        $className = "$namespace\\".str_replace( '.php', '', basename($filepath));

        if (str_ends_with($className, 'Check') && class_exists($className, true)) {
            $this->io->debug("Resolved class name: '{$className}'.");
            return $className;
        }
        $this->io->debug("Class '{$className}' is not a valid check class or doesn't exist.");
        return null;
    }

    private function isConcreteCheck(string $className): bool
    {
        try {
            $this->io->debug("Checking if '{$className}' is a concrete check.");
            $reflection = new ReflectionClass($className);
            $isSubclass = $reflection->isSubclassOf(AbstractCheck::class);
            $isNotAbstract = !$reflection->isAbstract();
            $isInstantiable = $reflection->isInstantiable();

            $this->io->debug("Reflection for '{$className}': isSubclassOf AbstractCheck: " . ($isSubclass ? 'true' : 'false') . ", isAbstract: " . (!$isNotAbstract ? 'true' : 'false') . ", isInstantiable: " . ($isInstantiable ? 'true' : 'false') . ".");
            return $isSubclass && $isNotAbstract && $isInstantiable;
        } catch (Exception $e) {
            $this->io->debug("Exception caught while reflecting '{$className}': " . $e->getMessage());
            return false;
        }
    }

    private function loadSingleCheck(string $className): void
    {
        $this->io->debug("Attempting to load single check: '{$className}'.");
        if (!$this->isConcreteCheck($className)) {
            throw new Exception("Class '{$className}' is not a valid concrete check.");
        }

        $this->checks = [$className];
        $this->io->debug("Single check '{$className}' loaded successfully.");
    }

    /**
     * Executes all discovered checks and aggregates their reports into a single Reporter instance.
     *
     * @return Report A fully populated Report instance ready for printing.
     */
    public function runChecks(): Report
    {
        $reports = [];
        $report = new Report($this->settings->plugin->component, $this->settings);
        $report->start();

        $this->io->debug('The following checks have been discovered:');
        $this->io->printList(array_map(fn($c) => $c::getName(), $this->checks), level: Settings::VERBOSITY_DEBUG);

        $initialCheckCount = count($this->checks);
        $this->checks = array_filter($this->checks, fn($c) => $report->isIssueActive($c::getName()));
        $this->io->debug('Filtered ' . ($initialCheckCount - count($this->checks)) . ' inactive checks.');

        $this->io->verbose('The following checks are active and will be executed:');
        $this->io->printList(array_map(fn($c) => $c::getName(), $this->checks), level: Settings::VERBOSITY_VERBOSE);

        if (empty($this->checks)) {
            $this->io->verbose('No active checks to run.');
            $report->complete();
            return $report;
        }

        if ($this->settings->execute == Settings::PARALLEL_EXECUTION) {
            $this->io->verbose('Executing checks in parallel mode.');
            $options = [];
            foreach ($this->settings->exclude as $str) {
                $options[] = '--exclude';
                $options[] = $str;
                $this->io->debug("Adding exclude option: '{$str}'.");
            }
            foreach ($this->settings->include as $str) {
                $options[] = '--include';
                $options[] = $str;
                $this->io->debug("Adding include option: '{$str}'.");
            }
            foreach ($this->settings->customChecks as $namespace => $path) {
                $options[] = '--additional-check';
                $options[] = "$namespace:$path";
                $this->io->debug("Adding custom check option: '{$namespace}:{$path}'.");
            }

            $this->io->debug('Starting parallel check processes.');
            $process = new ParallelCheckProcess($this->settings->plugin->fullpath, $this->checks, $options);
            $process->execute();

            foreach ($process->getAllStdout() as $i => $stdout) {
                $this->io->debug("Processing output from process #{$i}.");
                if (!$stdout) {
                    $this->io->debug("Process #{$i} returned no stdout, skipping.");
                    continue;
                }
                $stderr = trim($process->getAllStderr()[$i]);
                if ($stderr != '') {
                    throw new Exception("An error occurred in an underlying process: {$stderr}");
                }
                if (!($data = json_decode($stdout, true))) {
                    throw new Exception("Unable to parse stdout, probably an error occurred: $stdout");
                }
                $reports[] = Report::fromJson($data, $this->settings);
                $this->io->debug("Report from process #{$i} parsed successfully.");
            }
        }  else {
            $this->io->verbose('Executing checks in sequential mode.');
            foreach ($this->checks as $check) {
                try {
                    $this->io->debug("Instantiating check '{$check}'.");
                    $checkInstance = new $check($this->settings);
                    $this->io->verbose('Running check: ' . $checkInstance->getName() . '...');
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
        $report->complete();
        $report->mergeIn(...$reports);
        $this->io->debug('Final report generated.');
        return $report;
    }
}