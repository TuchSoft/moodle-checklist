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
    public array $checkInclude = [];
    public array $checkExclude = [];
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
        $this->execute = $options['only'] ?? (($options['parallel'] ?? true) ? self::PARALLEL_EXECUTION : self::SEQUENTIAL_EXECUTION);

        $inputDefinitions = [];
        if ($options['include-check'] ?? false) {
            $this->checkInclude = $options['include-check'];
            $inputDefinitions = ['*' => ['active' => false]];
            foreach ($options['include-check'] as $include) {
                $inputDefinitions[$include] = ['active' => true];
            }
        } else if ($options['exclude-check'] ?? false) {
            $this->checkExclude = $options['exclude-check'];
            foreach ($options['exclude-check'] as $exclude) {
                $inputDefinitions[$exclude] = ['active' => false];
            }
        } else if ($options['definition'] ?? null) {
            $inputDefinitions = $options['definition'];
        }

        if ($options['additional-check'] ?? false) {
            foreach ($options['additional-check'] as $checkStr) {
                $parsed = explode(':', $checkStr);
                $this->customChecks[$parsed[0]] = $parsed[1];
            }
        }

        // Load the issue definition, optionally merged with a phase profile.
        $phase = $options['phase'] ?? 'none';
        $definitionFiles = [__DIR__.'/../issue_definition.json'];
        if ($phase !== 'none' && is_file(__DIR__."/../phases/{$phase}.json")) {
            $definitionFiles[] = __DIR__."/../phases/{$phase}.json";
        }

        $this->definition = new Definition($definitionFiles, $inputDefinitions);

        //Call PHPCS constructor
        parent::__construct([], false);

        $this->reportFile = $options['reportFile'] ?? 'php://stdout';
        // Apply our specific options for PHPCS reporting system
        $formats = ['info' => null];
        if ($options['format'] ?? false) {
            $formats = [];
            foreach ($options['format'] as $format) {
                $splitted = explode(':', $format);
                $formats[$splitted[0]] = $splitted[1] ?? $this->reportFile;
            }
        }
        $this->reports = $formats;

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