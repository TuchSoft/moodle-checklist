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
            )
            ->addOption(
                'additional-check',
                'a',
                InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY,
                "An optional list of namespace and path for additional check, in the form '<info>my\\namespace:/my/path</info>'",
                []
            )
            ->addOption(
                'parallel',
                null,
                InputOption::VALUE_NEGATABLE,
                'Execute all the check in parallel',
                true
            )
            ->addOption(
                'only',
                null,
                InputOption::VALUE_OPTIONAL,
                'DO NOT USE | Used internally to achieve parallel execution',
                null
            );


    }

    protected function validateInput(InputInterface $input): array|false {
        $options = $input->getOptions();
        $options['plugin'] = $input->getArgument('plugin');


        if (!is_dir($options['plugin'])) {
            $this->error("Error: Plugin directory not found or invalid: '{$options['plugin']}'");
            return false;
        }
        $options['plugin'] = realpath($options['plugin']);



        return $options;
    }

    protected function main(): int
    {

        $this->io->text("Checking Moodle plugin in {$this->settings->plugin->fullpath}");


        // Store original $_SERVER['argv'] and clear for PHP_CodeSniffer or similar tools if needed.
        $_SERVER['_argv'] = $_SERVER['argv'] ?? [];
        $_SERVER['argv'] = [];

        try {
            $this->io->info("Plugin Name: {$this->settings->plugin->component} (Version: {$this->settings->plugin->version})");
            $checker = new Checker($this->settings, $this->io);
            $report = $checker->runChecks();

            $reporter = new Reporter($this->settings, $report);


            $reporter->printReports();

            // Using SymfonyStyle's section for a title-like output
            $this->io->section('The following checks had run (* has issue)');

            $wIssue = $report->getReportWithIssue();
            $woIssue =  $report->getReportWithoutIssue();
            $executed = array_filter(array_merge(
                array_map(fn ($name, $time) => $time !== null ? "$name*: {$time}ms" : null, array_keys($wIssue), $wIssue),
                array_map(fn ($name, $time) =>  $time !== null ?  "$name: {$time}ms" : null, array_keys($woIssue), $woIssue),
            ));
            sort($executed);
            $this->io->printList($executed);

            if ($reporter->totalErrors > 0 || $reporter->totalWarnings > 0) {
                $this->io->warning('All checks done but something is not shiny yet, check the report!');
                return Command::FAILURE;
            } else {
                $this->io->success('All checks passed! Ready to release!');
                return Command::SUCCESS;
            }

        } catch (Exception $e) {
            throw $e;
        } finally {
            $_SERVER['argv'] = $_SERVER['_argv'];
        }
    }
}
