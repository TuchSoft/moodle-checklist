<?php

namespace Tuchsoft\MoodleChecklist\Cli;

use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Tuchsoft\MoodleChecklist\Checker;
use Tuchsoft\MoodleChecklist\Report\Reporter;
use Tuchsoft\MoodleChecklist\Settings;

// Explicitly import Command for constants

class CheckPluginCommand extends AbstractCommand
{
    protected static $defaultName = 'check'; // Define the command name for Symfony Console

    protected function configure(): void
    {
        $this
            ->setDescription('Runs Moodle plugin checks and generates a report.')
            ->addArgument(
                'plugin',
                InputArgument::REQUIRED,
                'Full path to the Moodle plugin directory (e.g., /var/www/html/moodle/local/myplugin).'
            )
            ->addOption(
                'format',
                'f',
                InputOption::VALUE_IS_ARRAY | InputOption::VALUE_OPTIONAL,
                'The report type to generate (e.g., full, json, checkstyle, summary).',
                ['summary', 'full' ]
            )
            ->addOption(
                'config',
                'c',
                InputOption::VALUE_OPTIONAL,
                'Path to a config file',
            )
            ->addOption(
                'include',
                'i',
                InputOption::VALUE_IS_ARRAY | InputOption::VALUE_OPTIONAL,
                'Issue to include in the run',
            )
            ->addOption(
                'exclude',
                'x',
                InputOption::VALUE_IS_ARRAY | InputOption::VALUE_OPTIONAL,
                'Issue to exclude from the run',
            )
            ->addOption(
                'source',
                null,
                InputOption::VALUE_NEGATABLE,
                'Show the code of the issue in the report',
                true
            )
            ->addOption(
                'colors',
                null,
                InputOption::VALUE_NEGATABLE,
                'The report type to generate (e.g., full, json, checkstyle, summary).',
                true
            );

    }

    protected function validateInput(InputInterface $input): array|false {
        $options = $input->getOptions();
        $options['plugin'] = $input->getArgument('plugin');


        if (!is_dir($options['plugin'])) {
            $this->error("Error: Plugin directory not found or invalid: '{$options['plugin']}'");
            return false;
        }
        $option['plugin'] = realpath($options['plugin']);

        if ($options['format']) {
            foreach ($options['format'] as $format) {
                $format = explode(':', $format);
                $option['format'][array_shift($format)] = ($format ? join(':', $format) : null);
            }
        }

        return $option;
    }

    protected function main($options): int
    {
        $settings = new Settings($options);
        $this->text("Checking Moodle plugin in {$settings->plugin->fullpath}");


        // Store original $_SERVER['argv'].
        $originalArgv = $_SERVER['argv'] ?? [];
        //Clear for PHP_CodeSniffer or similar tools if needed.
        $_SERVER['argv'] = [];

        try {
            $this->info("Plugin Name: {$settings->plugin->fullname} (Version: {$settings->plugin->version})");
            $checker = new Checker($settings);
            $report = $checker->runChecks();

            $reporter = new Reporter($settings, $report);

            $this->text('Done, generating report...');
            $reporter->printReports();

            // Using SymfonyStyle's section for a title-like output
            $this->io->section('The following checks had run (* has issue)');

            $wIssue = $report->getReportWithIssue();
            $woIssue =  $report->getReportWithoutIssue();
            $executed = array_merge(
                array_map(fn ($name, $time) => "$name*: {$time}s", array_keys($wIssue), $wIssue),
                array_map(fn ($name, $time) => "$name: {$time}s", array_keys($woIssue), $woIssue),
            );
            sort($executed);
            $this->printList($executed);

            if ($reporter->totalErrors > 0 || $reporter->totalWarnings > 0) {
                $this->warning('All checks done but something is not shiny yet, check the report!');
                return Command::FAILURE;
            } else {
                $this->success('All checks passed! Ready to release!');
                return Command::SUCCESS;
            }

        } catch (Exception $e) {
            $this->error("An unexpected error occurred: {$e->getMessage()}");
            return Command::FAILURE;
        } finally {
            // Restore original $_SERVER['argv'] in a finally block to ensure it's always restored
            $_SERVER['argv'] = $originalArgv;
        }
    }
}
