<?php

namespace Tuchsoft\MoodleChecklist;


use Exception;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use Throwable;
use Tuchsoft\MoodleChecklist\Check\AbstractCheck;
use Tuchsoft\MoodleChecklist\Report\Report;
use Tuchsoft\MoodleChecklist\Report\Reporter;

class Checker
{
    private Settings $settings;
    private array $checks = []; // Stores instances of AbstractCheck

    public function __construct(Settings $settings)
    {
        $this->settings = $settings;

        // Populate the Plugin object within the Settings
        if ($this->settings->plugin->hasError()) {
            // Handle error, e.g., throw an exception or log
            throw new Exception('Failed to parse plugin info: ' . $this->settings->plugin->getErrorMessage());
        }

        $this->discoverChecks();
    }

    private function discoverChecks(): void
    {
        $checkDirs = [__DIR__ . '/Check'];

        $customCheckPath = getenv('MOODLE_CHECKLIST_CUSTOM_CHECKS_PATH');
        if ($customCheckPath && is_dir($customCheckPath)) {
            $checkDirs[] = $customCheckPath;
        }

        // Set up the custom error handler before loading files
        $this->setupErrorAndExceptionHandler();

        foreach ($checkDirs as $dir) {
            $this->loadChecksFromDirectory($dir);
        }

        // Restore the default error handler after the file loading is complete
        $this->restoreErrorAndExceptionHandler();
    }

    private function setupErrorAndExceptionHandler(): void
    {
        // Set a custom error handler for non-fatal errors
        set_error_handler(function ($errno, $errstr, $errfile, $errline) {
            // Log the error and throw an exception to be caught
            // in case the error is not fatal and can be handled
            $message = "Error: [$errno] $errstr in $errfile on line $errline";
            error_log($message);
            // You can choose to throw an exception or just log and continue
            // For a class redeclaration, this will not be called, as it's a fatal error.
            // But this is good practice for other errors.
        });

        // Set an exception handler for fatal errors (like class redeclaration)
        register_shutdown_function(function () {
            $error = error_get_last();
            if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_COMPILE_ERROR, E_CORE_ERROR, E_RECOVERABLE_ERROR])) {
                // A fatal error occurred
                $message = 'FATAL ERROR: ' . $error['message'] . ' in ' . $error['file'] . ' on line ' . $error['line'];
                error_log($message);

                // You can add logic to send an email, display a user-friendly error page, etc.
                // For now, we'll just throw an exception to be caught by the calling code if possible,
                // and exit with a non-zero status code.
                throw new Exception($message);
            }
        });
    }

    private function restoreErrorAndExceptionHandler(): void
    {
        restore_error_handler();
        // There is no `restore_shutdown_function`, so it will persist until the end of the script.
    }

    private function loadChecksFromDirectory(string $directory): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
        );

        // The try-catch block here is now more effective because the shutdown function
        // will "catch" fatal errors and throw an exception for the try-catch block to handle.
        try {
            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    require_once $file->getPathname();
                }
            }
        } catch (Throwable $t) {
            // Now you can catch the exceptions thrown by the shutdown function
            throw new Exception("Cannot load file '{$file->getPathname()}': ".$t->getMessage());
        }

        foreach (get_declared_classes() as $class) {
            $reflection = new ReflectionClass($class);
            if ($reflection->isSubclassOf(AbstractCheck::class) && !$reflection->isAbstract() && !$reflection->isInterface() && $reflection->isInstantiable()) {
                if (str_starts_with($reflection->getFileName(), $directory)) {
                    if (!isset($this->checks[$reflection->getName()])) {
                        // Pass the plugin's full path to the check constructor
                        $this->checks[$reflection->getName()] = $reflection->newInstance($this->settings);
                    }
                }
            }

        }
    }

    /**
     * Executes all discovered checks and aggregates their reports into a single Reporter instance.
     *
     * @return Reporter A fully populated Reporter instance ready for printing.
     */
    public function runChecks(): Report
    {
        $reports = [];


        foreach ($this->checks as $checkInstance) {
            /** @var AbstractCheck $checkInstance */
            $checkInstance->execute(); // Execute populates its internal Report object
            $reports[] = $checkInstance->getReport(); // Retrieve the populated Report
        }

        return Report::merge($this->settings->plugin->component, ...$reports);



    }
}