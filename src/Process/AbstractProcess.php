<?php

namespace Tuchsoft\MoodleChecklist\Process;

use Exception;
use Symfony\Component\Process\Process;

/**
 * AbstractProcess provides a base for executing external commands using Symfony Process.
 *
 * This abstract class handles command execution, including:
 * - Defining the command to be run.
 * - Executing the command with an optional timeout.
 * - Capturing standard output (stdout) and standard error (stderr).
 * - Retrieving the exit code of the executed command.
 * - Providing error handling and reporting.
 * - Managing temporary files created during execution.
 *
 * Child classes must implement the `getCommand()` method to define the specific
 * command array for execution.
 */
abstract class AbstractProcess
{
    protected ?Process $process = null;
    protected ?string $stdout = null;
    protected ?string $stderr = null;
    protected ?int $exitCode = null;
    protected ?string $error = null;
    protected array $tmpFiles = [];

    public function __construct(private ?string $cwd = null)
    {
    }

    /**
     * Executes the defined command.
     *
     * This method orchestrates command execution, handles exceptions,
     * and ensures temporary files are cleaned up.
     *
     * You can override this method to add custom logic before or after command
     * execution. If overriding, always call `parent::execute()`.
     *
     * @param float|null $timeout The maximum time in seconds the process is allowed to run.
     * Set to `null` for no timeout. Defaults to 300.0 seconds.
     * @return bool True if the process completed successfully (exit code 0), false otherwise.
     */
    public function execute(?float $timeout = 300.0): bool
    {
        try {
            if (!$this->run($timeout)) {
                return false;
            }
        } catch (Exception $e) {
            $this->error = 'An unexpected error occurred during process execution: ' . $e->getMessage();
            return false;
        } finally {
            $this->removeTmpFiles();
        }

        return true;
    }

    /**
     * Creates and runs the Symfony Process.
     *
     * This method manages the lifecycle of the `Symfony\Component\Process\Process`
     * object, including setting up the command, timeout, and working directory,
     * then executing it and capturing outputs.
     *
     * This method should generally not be overridden.
     *
     * @param float|null $timeout The timeout for the execution in seconds.
     * @return bool True if the command ran without immediate process errors, false otherwise.
     */
    protected function run(?float $timeout): bool
    {
        $this->process = $this->getProcess($this->getCommand(), $timeout);
        $this->process->run();

        $this->stdout = $this->process->getOutput();
        $this->stderr = $this->process->getErrorOutput();
        $this->exitCode = $this->process->getExitCode();

        return true;
    }

    /**
     * Creates and configures a Symfony Process instance.
     *
     * This method sets the command, timeout, and working directory for the
     * `Symfony\Component\Process\Process` object.
     *
     * This method should generally not be overridden.
     *
     * @param array<string> $command The command as an array of strings.
     * @param float|null $timeout The timeout for the process in seconds.
     * @return Process The configured Symfony Process instance.
     */
    protected function getProcess(array $command, ?float $timeout): Process
    {
        $process = new Process($command);
        $process->setTimeout($timeout);
        if ($this->cwd) {
            $process->setWorkingDirectory($this->cwd);
        }
        return $process;
    }

    /**
     * Returns the command as an array of strings to be executed.
     *
     * Each element in the array represents a part of the command, which helps
     * prevent shell injection vulnerabilities.
     *
     * @return array<string> The command as an array, e.g., ['ls', '-l', '/tmp'].
     */
    abstract protected function getCommand(): array;

    /**
     * Gets the exit code returned by the executed command.
     *
     * A zero exit code typically indicates success.
     *
     * @return int|null The exit code, or null if the command has not been run.
     */
    public function getExitCode(): ?int
    {
        return $this->exitCode;
    }

    /**
     * Checks if the overall process execution was successful.
     *
     * Success is determined by the absence of internal errors (`getError()`) and
     * the Symfony Process indicating a successful completion (`isSuccessful()`).
     *
     * @return bool True if the process was successful, false otherwise.
     */
    public function isSuccessful(): bool
    {
        return empty($this->error) && $this->process !== null && $this->process->isSuccessful();
    }

    /**
     * Removes all temporary files created by `writeTmpFile()`.
     *
     * This method is automatically called after `execute()` completes
     * (in the `finally` block).
     */
    protected function removeTmpFiles(): void
    {
        foreach ($this->tmpFiles as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
    }

    /**
     * Gets the standard output generated by the executed command.
     *
     * @return string|null The standard output, or null if the command has not been run or produced no output.
     */
    public function getStdout(): ?string
    {
        return $this->stdout;
    }

    /**
     * Gets the standard error output generated by the executed command.
     *
     * @return string|null The standard error output, or null if the command has not been run or produced no error output.
     */
    public function getStderr(): ?string
    {
        return $this->stderr;
    }

    /**
     * Gets any internal error message generated during the process execution within this class.
     *
     * This includes errors caught by the `execute` method's try-catch block
     * or errors determined from the process's standard error output.
     *
     * @return string|null An error message, or null if no error occurred.
     */
    public function getError(): ?string
    {
        return $this->error;
    }

    /**
     * Writes content to a temporary file and registers it for cleanup.
     *
     * This is useful for commands that require input from files.
     *
     * @param string $content The content to write to the temporary file.
     * @param string $ext An optional file extension (e.g., '.json', '.txt').
     * @return string The full path to the created temporary file.
     */
    protected function writeTmpFile(string $content, string $ext = ''): string
    {
        $file = tempnam(sys_get_temp_dir(), 'process_tmp') . $ext;
        file_put_contents($file, $content);
        $this->tmpFiles[] = $file;
        return $file;
    }
}