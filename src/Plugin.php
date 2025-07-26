<?php

namespace Tuchsoft\MoodleChecklist;

use Tuchsoft\MoodleChecklist\Action\VersionParser;

class Plugin {
    public ?string $path;
    public string $fullpath;
    public string $component;
    public string $type;
    public string $name;
    public string $fullname;
    public int $version;
    public string $requires;
    public ?string $release = null;
    public ?int $maturity = null;
    public array $dependencies = [];
    public array $supported = [];
    public string $moodleroot;

    private ?string $errorMessage = null;

    public function __construct(string $pluginFullPath)
    {

        $parser = new VersionParser();
        $parsedData = ($parser)->parse($pluginFullPath);


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