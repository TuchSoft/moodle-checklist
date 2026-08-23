<?php

namespace Tuchsoft\MoodleChecklist\Cli;

use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Tuchsoft\MoodleChecklist\Check\FixableCheckInterface;
use Tuchsoft\MoodleChecklist\Checker;
use Tuchsoft\MoodleChecklist\Process\ParallelFixProcess;

class FixPluginCommand extends AbstractCommand
{
    protected static $defaultName = 'fix';

    private bool $apply = false;

    private bool $refreshGitignoreCache = false;

    private bool $parallel = true;

    private int $jobs = 4;

    private ?string $fixerGroup = null;

    private string $phase = 'none';

    private ?string $moodleRoot = null;

    private array $includeCheck = [];

    private array $excludeCheck = [];

    public const SUMMARY_MARKER = 'MOODLE_CHECKLIST_FIXER_SUMMARY';

    protected function configure(): void
    {
        $this
            ->setDescription('Auto-format files in a Moodle plugin. Dry-run by default; use --apply to write changes.')
            ->addArgument(
                'plugin',
                InputArgument::REQUIRED,
                'Full path to the Moodle plugin directory (e.g., /var/www/html/moodle/local/myplugin).'
            )
            ->addOption(
                'apply',
                null,
                InputOption::VALUE_NONE,
                'Actually write the formatted files. Without this flag the command only prints what would be done.'
            )
            ->addOption(
                'phase',
                null,
                InputOption::VALUE_REQUIRED,
                'Validation phase: none (default), pre-build, or post-build',
                'none'
            )
            ->addOption(
                'include-check',
                'i',
                InputOption::VALUE_IS_ARRAY | InputOption::VALUE_OPTIONAL,
                'Check to include in the fix run (e.g. phpcs, stylelint)',
                []
            )
            ->addOption(
                'exclude-check',
                'x',
                InputOption::VALUE_IS_ARRAY | InputOption::VALUE_OPTIONAL,
                'Check to exclude from the fix run (e.g. mustache)',
                []
            )
            ->addOption(
                'moodle-root',
                null,
                InputOption::VALUE_OPTIONAL,
                'Absolute path to the Moodle project root (not the web docroot).',
                null
            )
            ->addOption(
                'refresh-gitignore-cache',
                null,
                InputOption::VALUE_NONE,
                'Force a network refresh of the gitignore.io template cache before fixing.'
            )
            ->addOption(
                'parallel',
                'p',
                InputOption::VALUE_NEGATABLE,
                'Execute fixer groups in parallel when safe',
                true
            )
            ->addOption(
                'jobs',
                'j',
                InputOption::VALUE_REQUIRED,
                'Maximum number of fixer groups to run in parallel',
                4
            )
            ->addOption(
                'fixer-group',
                null,
                InputOption::VALUE_REQUIRED,
                'Run only a single fixer group (internal use for parallel execution).',
                null
            );
    }

    protected function validateInput(InputInterface $input): array|false
    {
        $options = $input->getOptions();
        $options['plugin'] = $input->getArgument('plugin');

        if (!is_dir($options['plugin'])) {
            $this->validationError = "Plugin directory not found or invalid: '{$options['plugin']}'";
            return false;
        }
        $options['plugin'] = realpath($options['plugin']);
        $this->apply = (bool) $options['apply'];
        $this->refreshGitignoreCache = (bool) $options['refresh-gitignore-cache'];
        $this->parallel = (bool) $options['parallel'];
        $this->jobs = (int) $options['jobs'];
        $this->fixerGroup = $options['fixer-group'];
        $this->phase = $options['phase'] ?? 'none';
        $this->moodleRoot = $options['moodle-root'] ?? null;
        $this->includeCheck = (array) ($options['include-check'] ?? []);
        $this->excludeCheck = (array) ($options['exclude-check'] ?? []);

        if ($this->jobs < 1) {
            $this->validationError = '--jobs must be a positive integer.';
            return false;
        }

        return $options;
    }

    protected function main(): int
    {
        $mode = $this->apply ? 'APPLY' : 'DRY-RUN';
        if ($this->fixerGroup === null) {
            $this->io->text("Fixing Moodle plugin in {$this->settings->plugin->fullpath} [{$mode}]");
        }

        $_SERVER['_argv'] = $_SERVER['argv'] ?? [];
        $_SERVER['argv'] = [];

        try {
            if ($this->refreshGitignoreCache) {
                try {
                    (new \Tuchsoft\MoodleChecklist\GitIgnore\GitIgnoreTemplateCache())->refresh();
                    $this->io->success('Gitignore template cache refreshed.');
                } catch (\RuntimeException $e) {
                    $this->io->error($e->getMessage());
                    return Command::FAILURE;
                }
            }

            $checker = new Checker($this->settings, $this->io);
            $fixables = $this->collectFixableChecks($checker);

            if (empty($fixables)) {
                $this->io->warning('No fixable checks are active for this plugin.');
                return Command::SUCCESS;
            }

            if ($this->fixerGroup !== null) {
                return $this->runSingleGroup($fixables);
            }

            if (!$this->parallel) {
                return $this->runSequential($fixables);
            }

            return $this->runParallel($fixables);
        } catch (Exception $e) {
            throw $e;
        } finally {
            $_SERVER['argv'] = $_SERVER['_argv'];
        }
    }

    /**
     * @param FixableCheckInterface[] $fixables
     */
    private function runSingleGroup(array $fixables): int
    {
        $group = $this->fixerGroup;
        $groupFixables = array_values(array_filter(
            $fixables,
            fn (FixableCheckInterface $check) => $check->getFixerGroup() === $group
        ));

        if (empty($groupFixables)) {
            $this->io->warning("No active fixable checks found for group '{$group}'.");
            return Command::SUCCESS;
        }

        [$ran, $failed, $skipped] = $this->runFixers($groupFixables);

        $summary = json_encode([
            'group' => $group,
            'ran' => $ran,
            'failed' => $failed,
            'skipped' => $skipped,
        ]);
        $this->io->text(self::SUMMARY_MARKER . ' ' . $summary);

        return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * @param FixableCheckInterface[] $fixables
     */
    private function runSequential(array $fixables): int
    {
        [$ran, $failed, $skipped] = $this->runFixers($fixables);

        if ($this->apply) {
            $summary = "{$ran} formatter(s) ran";
            if ($failed > 0) {
                $summary .= ", {$failed} failed";
                $this->io->warning("Formatting applied with failures. {$summary}, {$skipped} skipped.");
            } else {
                $this->io->success("Formatting applied. {$summary}, {$skipped} skipped.");
            }
            $this->io->text('Run `bin/console check <plugin>` to verify the remaining issues.');
        } else {
            $summary = "{$ran} formatter(s) would run";
            if ($failed > 0) {
                $summary .= ", {$failed} would fail";
                $this->io->warning("Dry-run complete. {$summary}, {$skipped} skipped. Use --apply to write changes.");
            } else {
                $this->io->success("Dry-run complete. {$summary}, {$skipped} skipped. Use --apply to write changes.");
            }
        }

        return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * @param FixableCheckInterface[] $fixables
     */
    private function runParallel(array $fixables): int
    {
        $scheduler = new FixerGroupScheduler();
        $waves = $scheduler->schedule($fixables);

        if (count($waves) === 1 && count($waves[0]) === 1) {
            $this->io->note('Only one fixer group is active; running sequentially.');
            return $this->runSequential($fixables);
        }

        $totalRan = 0;
        $totalFailed = 0;
        $totalSkipped = 0;

        $subprocessOptions = [
            'phase' => $this->getOptionValue('phase'),
            'moodle-root' => $this->getOptionValue('moodle-root'),
            'include-check' => $this->getOptionValue('include-check') ?? [],
            'exclude-check' => $this->getOptionValue('exclude-check') ?? [],
        ];

        foreach ($waves as $waveIndex => $wave) {
            $groupNames = array_keys($wave);
            $this->io->section('Wave ' . ($waveIndex + 1) . ': ' . implode(', ', $groupNames));

            $chunks = array_chunk($groupNames, $this->jobs);
            foreach ($chunks as $chunk) {
                $process = new ParallelFixProcess(
                    $this->settings->plugin->fullpath,
                    $chunk,
                    $subprocessOptions,
                    $this->apply
                );
                $process->execute();

                foreach ($chunk as $index => $group) {
                    $stdout = $process->getAllStdout()[$index] ?? '';
                    $stderr = $process->getAllStderr()[$index] ?? '';

                    $this->io->section("Group: {$group}");
                    $this->printGroupOutput($stdout, $stderr);

                    $summary = $this->parseSummary($stdout);
                    $totalRan += $summary['ran'];
                    $totalFailed += $summary['failed'];
                    $totalSkipped += $summary['skipped'];
                }

                if ($process->getExitCode() !== 0 && $process->getExitCode() !== 1) {
                    $this->io->warning('A group subprocess failed unexpectedly.');
                }
            }
        }

        if ($this->apply) {
            $summary = "{$totalRan} formatter(s) ran";
            if ($totalFailed > 0) {
                $summary .= ", {$totalFailed} failed";
                $this->io->warning("Formatting applied with failures. {$summary}, {$totalSkipped} skipped.");
            } else {
                $this->io->success("Formatting applied. {$summary}, {$totalSkipped} skipped.");
            }
            $this->io->text('Run `bin/console check <plugin>` to verify the remaining issues.');
        } else {
            $summary = "{$totalRan} formatter(s) would run";
            if ($totalFailed > 0) {
                $summary .= ", {$totalFailed} would fail";
                $this->io->warning("Dry-run complete. {$summary}, {$totalSkipped} skipped. Use --apply to write changes.");
            } else {
                $this->io->success("Dry-run complete. {$summary}, {$totalSkipped} skipped. Use --apply to write changes.");
            }
        }

        return $totalFailed > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * @param FixableCheckInterface[] $fixables
     * @return array{ran: int, failed: int, skipped: int}
     */
    private function runFixers(array $fixables): array
    {
        $ran = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($fixables as $check) {
            $name = get_class($check)::getName();
            if (!$check->canFix()) {
                $this->io->note("Check '{$name}' is fixable but the required formatter is not available in this environment.");
                $skipped++;
                continue;
            }

            $this->io->section("Running formatter for '{$name}'");
            $success = $check->fix($this->apply);
            if ($success) {
                $ran++;
            } else {
                $failed++;
            }
        }

        return [$ran, $failed, $skipped];
    }

    private function printGroupOutput(string $stdout, string $stderr): void
    {
        $stdout = trim($stdout);
        $stderr = trim($stderr);

        // Strip the summary marker line from visible output.
        $lines = explode("\n", $stdout);
        $filtered = [];
        foreach ($lines as $line) {
            $trimmed = ltrim($line);
            if (str_starts_with($trimmed, self::SUMMARY_MARKER)) {
                continue;
            }
            $filtered[] = $line;
        }
        $stdout = trim(implode("\n", $filtered));

        if ($stdout !== '') {
            $this->io->text($stdout);
        }

        if ($stderr !== '') {
            $this->io->warning($stderr);
        }
    }

    /**
     * @return array{ran: int, failed: int, skipped: int}
     */
    private function parseSummary(string $stdout): array
    {
        $lines = explode("\n", trim($stdout));
        foreach (array_reverse($lines) as $line) {
            $trimmed = ltrim($line);
            if (str_starts_with($trimmed, self::SUMMARY_MARKER)) {
                $json = trim(substr($trimmed, strlen(self::SUMMARY_MARKER)));
                $decoded = json_decode($json, true);
                if (is_array($decoded)) {
                    return [
                        'ran' => (int) ($decoded['ran'] ?? 0),
                        'failed' => (int) ($decoded['failed'] ?? 0),
                        'skipped' => (int) ($decoded['skipped'] ?? 0),
                    ];
                }
            }
        }

        return ['ran' => 0, 'failed' => 0, 'skipped' => 0];
    }

    private function getOptionValue(string $name): mixed
    {
        return match ($name) {
            'phase' => $this->phase,
            'moodle-root' => $this->moodleRoot,
            'include-check' => $this->includeCheck,
            'exclude-check' => $this->excludeCheck,
            default => null,
        };
    }

    /**
     * @return FixableCheckInterface[]
     */
    private function collectFixableChecks(Checker $checker): array
    {
        $fixables = [];
        $checkClasses = $checker->getChecks();

        foreach ($checkClasses as $className) {
            if (!is_subclass_of($className, FixableCheckInterface::class)) {
                continue;
            }

            $instance = new $className($this->settings);
            $instance->setIo($this->io);
            if (!$instance->isActive()) {
                continue;
            }
            $fixables[] = $instance;
        }

        return $fixables;
    }
}
