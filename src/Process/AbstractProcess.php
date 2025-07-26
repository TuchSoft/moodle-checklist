<?php


namespace Tuchsoft\MoodleChecklist\Process;

use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;

/**
 * AbstractProcess provides a base for executing external commands using Symfony Process.
 * It handles command execution, output collection, and basic error handling.
 */
abstract class AbstractProcess
{
    protected ?Process $process = null;
    protected ?string $stdout = null;
    protected ?string $stderr = null;
    protected ?int $exitCode = null;
    protected ?string $error = null;
    protected array $tmpFiles = [];



    public function __construct(private readonly ?string $cwd = null)
    {
    }

    /**
     * Returns the command array to be executed.
     * Each element in the array is a part of the command, preventing shell injection issues.
     *
     * @return array<string> The command as an array of strings.
     */
    abstract protected function getCommand(): array;

    /**
     * Executes the defined command.
     *
     * @param float|null $timeout The timeout in seconds for the process. Null for no timeout.
     * @return bool True if the process was successful (exit code 0), false otherwise.
     */
    public function execute(?float $timeout = 60.0): bool
    {

        $this->process = new Process($this->getCommand());
        $this->process->setTimeout($timeout);
        $this->process->setEnv([
            'NODE_PATH' => __DIR__."/../../node_modules",
            'XDEBUG_SESSION' => NULL
        ]);
        if ($this->cwd) {
            $this->process->setWorkingDirectory($this->cwd);
        }

        try {
            $this->process->run();

            $this->stdout = $this->process->getOutput();
            $this->stderr = $this->process->getErrorOutput();
            $this->exitCode = $this->process->getExitCode();

            if (!$this->process->isSuccessful()) {
                $this->error =  $this->stderr;
                return false;
            }

        } catch (ProcessFailedException $e) {
            $this->error = "Process execution failed: " . $e->getMessage();
            $this->stdout = $e->getProcess()->getOutput();
            $this->stderr = $e->getProcess()->getErrorOutput();
            $this->exitCode = $e->getProcess()->getExitCode();
            return false;
        } catch (ProcessTimedOutException $e) {
            $this->error = "Process timed out: " . $e->getMessage();
            $this->stdout = $e->getProcess()->getOutput();
            $this->stderr = $e->getProcess()->getErrorOutput();
            $this->exitCode = $e->getProcess()->getExitCode();
            return false;
        } catch (\Exception $e) {
            $this->error = "An unexpected error occurred during process execution: " . $e->getMessage();
            return false;
        } finally {
            $this->removeTmpFiles();
        }


        return true;
    }

    /**
     * Gets the standard output from the executed command.
     *
     * @return string|null
     */
    public function getStdout(): ?string
    {
        return $this->stdout;
    }

    /**
     * Gets the standard error output from the executed command.
     *
     * @return string|null
     */
    public function getStderr(): ?string
    {
        return $this->stderr;
    }

    /**
     * Gets the exit code of the executed command.
     *
     * @return int|null
     */
    public function getExitCode(): ?int
    {
        return $this->exitCode;
    }

    /**
     * Gets any error message generated during process execution.
     *
     * @return string|null
     */
    public function getError(): ?string
    {
        return $this->error;
    }

    /**
     * Checks if the process was successful.
     *
     * @return bool
     */
    public function isSuccessful(): bool
    {
        return empty($this->error) && $this->process !== null && $this->process->isSuccessful();
    }


    protected  function writeTmpFile(string $content, string $ext = ''): string {
        $file = tempnam(sys_get_temp_dir(), 'remark-config').$ext;
        file_put_contents($file, $content);
        $this->tmpFiles[] = $file;
        return $file;
    }


    protected function removeTmpFiles(): void {
        foreach ($this->tmpFiles as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
    }
}
