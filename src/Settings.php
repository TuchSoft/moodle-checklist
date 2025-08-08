<?php

namespace Tuchsoft\MoodleChecklist;





use MoodlePluginCI\Bridge\Moodle;
use PHP_CodeSniffer\Config;
use Symfony\Component\Console\Output\OutputInterface;

class Settings extends Config
{
    public const PARALLEL_EXECUTION = 'PARALLEL';
    public const SEQUENTIAL_EXECUTION = 'SEQUENTIAL';
    public const VERBOSITY_QUIET = 0;
    public const VERBOSITY_NORMAL = 1;
    public const VERBOSITY_VERBOSE = 2;
    public const VERBOSITY_DEBUG = 3;

    public Plugin $plugin;
    public Moodle $moodle;
    public string $execute;
    public array $include = [];
    public array $exclude = [];
    /**
     * @var array namesapce => dir mappings for additional custom checks
     */
    public array $customChecks = [];

    public Definition $definition;

    /**
     * @param array $options An associative array of configuration options.
     * Expected keys: 'reports', 'reportFile', 'showSources', 'reportWidth', 'interactive', 'colors'.
     */
    public function __construct( array $options = [])
    {
        global $_SERVER;
        //Load the plugin
        $this->plugin = new Plugin($options['plugin']);
        $this->moodle = new Moodle($this->plugin->moodleroot);
        $this->execute = $options['only'] ?? ($options['parallel'] ? self::PARALLEL_EXECUTION : self::SEQUENTIAL_EXECUTION);

        $inputDefinitions = [];
        if ($options['include']) {
            $this->include = $options['include'];
            $inputDefinitions = ['*' => ['active' => false]];
            foreach ($options['include'] as $include) {
                $inputDefinitions[$include] = ['active' => true];
            }
        } else if ($options['exclude']) {
            $this->exclude = $options['exclude'];
            foreach ($options['exclude'] as $include) {
                $inputDefinitions[$include] = ['active' => false];
            }
        } else if ($options['definition']) {
            $inputDefinitions = $options['definition'];
        }

        if ($options['additional-check']) {
            foreach ($options['additional-check'] as $checkStr) {
                $parsed = explode(':', $checkStr);
                $this->customChecks[$parsed[0]] = $parsed[1];
            }
        }

        //Load the issue definition
        $this->definition = new Definition(__DIR__.'/../issue_definition.json', $inputDefinitions);

        //Call PHPCS constructor
        parent::__construct([], false);

        // Apply our specific options for PHPCS reporting system
        $formats = ['summary' => null, 'full' => null];
        if ($options['format']) {
            $formats = [];
            foreach ($options['format'] as $format) {
                $splitted = explode(':', $format);
                $formats[$splitted[0]] = $splitted[1] ?? 'php://stdout';
            }
        }
        $this->reports = $formats;
        $this->reportFile = $options['reportFile'] ?? null;
        $this->showSources = $options['showSources'] ?? false;
        $this->reportWidth = $options['reportWidth'] ?? 80;
        $this->interactive = false;
        $this->colors = $options['colors'] ?? true;
        $this->verbosity = match( $options['verbosity'] ) {
            OutputInterface::VERBOSITY_QUIET => self::VERBOSITY_QUIET,
            OutputInterface::VERBOSITY_NORMAL => self::VERBOSITY_NORMAL,
            OutputInterface::VERBOSITY_VERY_VERBOSE => self::VERBOSITY_VERBOSE,
            OutputInterface::VERBOSITY_DEBUG => self::VERBOSITY_DEBUG,
        };


    }

    public function isQuiet(): bool {
        return  $this->isVerbosityAtLeast(self::VERBOSITY_QUIET);
    }

    public function isVerbose(): bool {
        return $this->isVerbosityAtLeast(self::VERBOSITY_VERBOSE);
    }

    public function isDebug(): bool {
        return $this->isVerbosityAtLeast(self::VERBOSITY_DEBUG);
    }

    public function isVerbosityAtLeast(int $level): bool {
        return $level <= $this->verbosity;
    }


    public function isSingle(): bool {
        return $this->execute != self::PARALLEL_EXECUTION && $this->execute != self::SEQUENTIAL_EXECUTION;
    }
}