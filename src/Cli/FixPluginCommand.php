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

        return $options;
    }

    protected function main(): int
    {
        $mode = $this->apply ? 'APPLY' : 'DRY-RUN';
        $this->io->text("Fixing Moodle plugin in {$this->settings->plugin->fullpath} [{$mode}]");

        $_SERVER['_argv'] = $_SERVER['argv'] ?? [];
        $_SERVER['argv'] = [];

        try {
            $checker = new Checker($this->settings, $this->io);
            $fixables = $this->collectFixableChecks($checker);

            if (empty($fixables)) {
                $this->io->warning('No fixable checks are active for this plugin.');
                return Command::SUCCESS;
            }

            $ran = 0;
            $skipped = 0;
            foreach ($fixables as $check) {
                $name = get_class($check)::getName();
                if (!$check->canFix()) {
                    $this->io->note("Check '{$name}' is fixable but the required formatter is not available in this environment.");
                    $skipped++;
                    continue;
                }

                $this->io->section("Running formatter for '{$name}'");
                $check->fix($this->apply);
                $ran++;
            }

            if ($this->apply) {
                $this->io->success("Formatting applied. {$ran} formatter(s) ran, {$skipped} skipped.");
                $this->io->text('Run `bin/console check <plugin>` to verify the remaining issues.');
            } else {
                $this->io->success("Dry-run complete. {$ran} formatter(s) would run, {$skipped} skipped. Use --apply to write changes.");
            }

            return Command::SUCCESS;
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
