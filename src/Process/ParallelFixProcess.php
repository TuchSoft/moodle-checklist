<?php

namespace Tuchsoft\MoodleChecklist\Process;

/**
 * Runs one wave of fixer groups in parallel by spawning one subprocess
 * per group.
 *
 * Each subprocess invokes `bin/console fix --fixer-group=<group>` and runs
 * the checks of that group sequentially. Output and a JSON summary are
 * captured for each group.
 */
class ParallelFixProcess extends AbstractParallelProcess
{
    /**
     * @param string $plugin Absolute plugin path.
     * @param string[] $groups Group names to run in this wave.
     * @param array<string, mixed> $options Options to forward to the subprocess (phase, moodle-root, include/exclude-check).
     * @param bool $apply Whether to actually write files.
     */
    public function __construct(
        private string $plugin,
        private array $groups,
        private array $options,
        private bool $apply
    ) {
        parent::__construct(realpath(__DIR__ . '/../../'));
    }

    /**
     * @return string[][]
     */
    protected function getCommand(): array
    {
        $commands = [];

        $baseOptions = [
            '--no-interaction',
            '--no-parallel',
        ];

        if (!empty($this->options['phase'])) {
            $baseOptions[] = '--phase';
            $baseOptions[] = $this->options['phase'];
        }

        if (!empty($this->options['moodle-root'])) {
            $baseOptions[] = '--moodle-root';
            $baseOptions[] = $this->options['moodle-root'];
        }

        foreach ($this->options['include-check'] ?? [] as $include) {
            $baseOptions[] = '--include-check';
            $baseOptions[] = $include;
        }

        foreach ($this->options['exclude-check'] ?? [] as $exclude) {
            $baseOptions[] = '--exclude-check';
            $baseOptions[] = $exclude;
        }

        if ($this->apply) {
            $baseOptions[] = '--apply';
        }

        foreach ($this->groups as $group) {
            $commands[] = [
                'php',
                './bin/console',
                'fix',
                ...$baseOptions,
                '--fixer-group',
                $group,
                $this->plugin,
            ];
        }

        return $commands;
    }

    /**
     * No special parsing required; raw output is consumed by the caller.
     */
    protected function parseOutput(): bool
    {
        return true;
    }
}
