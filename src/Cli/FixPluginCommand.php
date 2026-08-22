<?php

namespace Tuchsoft\MoodleChecklist\Cli;

use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Tuchsoft\MoodleChecklist\Check\FixableCheckInterface;
use Tuchsoft\MoodleChecklist\Checker;

class FixPluginCommand extends AbstractCommand
{
    protected static $defaultName = 'fix';

    private bool $apply = false;

    private bool $refreshGitignoreCache = false;

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

        return $options;
    }

    protected function main(): int
    {
        $mode = $this->apply ? 'APPLY' : 'DRY-RUN';
        $this->io->text("Fixing Moodle plugin in {$this->settings->plugin->fullpath} [{$mode}]");

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
        } catch (Exception $e) {
            throw $e;
        } finally {
            $_SERVER['argv'] = $_SERVER['_argv'];
        }
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
