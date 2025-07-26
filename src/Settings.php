<?php

namespace Tuchsoft\MoodleChecklist;





class Settings extends \PHP_CodeSniffer\Config
{
    public Plugin $plugin;

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

        $inputDefinitions = [];
        if ($options['include']) {
            $inputDefinitions = ['*' => ['active' => false]];
            foreach ($options['include'] as $include) {
                $inputDefinitions[$include] = ['active' => true];
            }
        } else if ($options['exclude']) {
            foreach ($options['exclude'] as $include) {
                $inputDefinitions[$include] = ['active' => false];
            }
        } else if ($options['definition']) {
            $inputDefinitions = $options['definition'];
        }

        //Load the issue definition
        $this->definition = new Definition(__DIR__.'/../issue_definition.json', $inputDefinitions);

        //Call PHPCS constructor
        parent::__construct([], false);

        // Apply our specific options for PHPCS reporting system
        $this->reports = $options['reports'] ?? ['summary' => null, 'full' => null];
        $this->reportFile = $options['reportFile'] ?? null;
        $this->showSources = $options['showSources'] ?? false;
        $this->reportWidth = $options['reportWidth'] ?? 80;
        $this->interactive = false;
        $this->colors = $options['colors'] ?? true;


    }
}