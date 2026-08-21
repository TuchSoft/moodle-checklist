<?php

namespace Tuchsoft\MoodleChecklist\Cli;

use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Tuchsoft\IssueReporter\Reporter;
use Tuchsoft\MoodleChecklist\Checker;

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
                "The report type to generate (e.g., info, json, checkstyle). <comment>For AI/LLM agents, 'emacs' is the recommended format.</comment>",
                ['info']
            )
            ->addOption(
                'config',
                'c',
                InputOption::VALUE_OPTIONAL,
                'Path to a config file',
            )
            ->addOption(
                'include-check',
                'i',
                InputOption::VALUE_IS_ARRAY | InputOption::VALUE_OPTIONAL,
                'Check to include in the run (e.g. readme, filestructure)',
            )
            ->addOption(
                'exclude-check',
                'x',
                InputOption::VALUE_IS_ARRAY | InputOption::VALUE_OPTIONAL,
                'Check to exclude from the run (e.g. marketplaceimages, image, repository)',
            )
            ->addOption(
                'phase',
                null,
                InputOption::VALUE_REQUIRED,
                'Validation phase: none (default, current behavior), pre-build, or post-build',
                'none'
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
                'moodle-root',
                null,
                InputOption::VALUE_OPTIONAL,
                'Absolute path to the Moodle project root (not the web docroot).',
                null
            )
            ->addOption(
                'only',
                null,
                InputOption::VALUE_OPTIONAL,
                'DO NOT USE | Used internally to achieve parallel execution',
                null
            );
            foreach (Reporter::getOptionsDefinition() as $option) {
                $this->getDefinition()->addOption($option);
            }


    }

    protected function validateInput(InputInterface $input): array|false {
        $options = $input->getOptions();
        $options['plugin'] = $input->getArgument('plugin');


        if (!is_dir($options['plugin'])) {
            $this->validationError = "Plugin directory not found or invalid: '{$options['plugin']}'";
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

            $formats = $this->settings->reports ?: ['info' => 'php://stdout'];
            foreach ($formats as $format => $outputFile) {
                $outputFile = $outputFile ?: 'php://stdout';
                // IssueReporter has no dedicated 'json' format; 'raw' is JSON-serialized report.
                if ($format === 'json') {
                    $format = 'raw';
                }
                Reporter::printReport($report, $format, $outputFile);
            }

            if ($report->getTotalErrors() > 0 || $report->getTotalWarnings() > 0) {
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
