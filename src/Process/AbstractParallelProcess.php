<?php

namespace Tuchsoft\MoodleChecklist\Process;

use Symfony\Component\Process\Process;

/**
 * AbstractParallelProcess extends AbstractIssuesProcess to support the parallel execution of multiple commands.
 *
 * This class overrides the `run` method to execute a collection of commands concurrently,
 * aggregating their standard output, standard error, and exit codes.
 *
 * Child classes must implement the `getCommand()` method to return an array of
 * command arrays, where each inner array represents a command to be executed in parallel.
 */
abstract class AbstractParallelProcess extends AbstractIssuesProcess
{

    protected array $allStderr = [];


    protected array $allStdout = [];
    protected array $allExitCode = [];

    /**
     * Creates and runs multiple Symfony Processes in parallel.
     *
     * This method iterates through an array of commands, starting each as a
     * separate process. It then waits for all processes to complete and
     * aggregates their outputs and exit codes.
     *
     * @param float|null $timeout The timeout for each individual process execution in seconds.
     * @return bool True if all processes completed without critical errors (aggregated exit code is 0), false otherwise.
     */
    protected function run(?float $timeout): bool
    {
        $allProcess = [];

        foreach ($this->getCommand() as $command) {
            $process = $this->getProcess($command, $timeout);
            $process->start();
            $allProcess[] = $process;
        }

        /** @var Process $process */
        foreach ($allProcess as $process) {
            $process->wait();
            $this->allStdout[] = $process->getOutput();
            $this->allStderr[] = $process->getErrorOutput();
            $this->allExitCode[] = $process->getExitCode();
        }

        // Treat only unexpected exit codes (not 0 or 1) as a process failure.
        // Exit code 1 from a check subprocess usually means "issues were found", which is normal output.
        $failed = array_filter($this->allExitCode, fn(?int $code) => $code !== 0 && $code !== 1);
        $this->exitCode = !empty($failed) ? 1 : 0;

        return $this->exitCode === 0;
    }

    /**
     * For parallel execution it MUST return an array of array of string
     * {@inheritDoc}
     * @return string[][]
     */
    abstract protected function getCommand():array;


    public function getAllStderr(): array
    {
        return $this->allStderr;
    }

    public function setAllStderr(array $allStderr): static
    {
        $this->allStderr = $allStderr;
         return $this;
    }

    public function getAllStdout(): array
    {
        return $this->allStdout;
    }

    public function setAllStdout(array $allStdout): static
    {
        $this->allStdout = $allStdout;
         return $this;
    }

    public function getAllExitCode(): array
    {
        return $this->allExitCode;
    }

    public function setAllExitCode(array $allExitCode): static
    {
        $this->allExitCode = $allExitCode;
        return $this;
    }
}