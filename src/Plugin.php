<?php

namespace Tuchsoft\MoodleChecklist;

use Tuchsoft\MoodleChecklist\Utils\VersionParser;
use MoodlePluginCI\PluginValidate\Plugin as MoodleCiPlugin;

class Plugin extends MoodleCiPlugin {
    public ?string $path; //ES. local/myplugin
    public string $fullpath; // Es. /var/www/html/moodle/local/myplugin
    public string $component; //ES. local_myplugin
    public string $type; //Es. local
    public string $name; //ES. myplugin
    public int $version; // Es 2025072700
    public string $requires; // Es. 2035030900
    public ?string $release = null; // Es. 1.2.3
    public ?int $maturity = null; // Cojnatnt from moodle (ES MATURIRY_STABLE)
    public array $dependencies = []; //Other plugin dependency
    public array $supported = []; //Moodle supported branch, min - max
    public string $moodleroot; //Moodle root directory

    private ?string $errorMessage = null; //Any error message that arise during the execution

    public function __construct(string $pluginFullPath, ?string $moodleRoot = null)
    {

        $parser = new VersionParser();
        $parsedData = ($parser)->parse($pluginFullPath, $moodleRoot);


        if ($parsedData === null) {
            $this->errorMessage = $parser->getLastError();
            return;
        }

        // Populate properties from parsed data
        foreach ($parsedData as $key => $value) {
            if (!is_null($value) && property_exists($this, $key)) {
                $this->$key = $value;
            }
        }

        parent::__construct($this->component, $this->type, $this->name, $this->fullpath);
    }

    public function hasError(): bool
    {
        return $this->errorMessage !== null;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }
}