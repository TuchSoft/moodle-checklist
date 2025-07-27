<?php

namespace Tuchsoft\MoodleChecklist\Action;

class PluginType extends AbstractAction
{
    private static array $pluginTypesCache = []; // Static property for caching
    private static bool $loaded = false; // To track if it has been loaded

    public function __construct(private readonly string $pluginTypesJsonUrl = 'https://tuchsoft.com/resources/moodle/plugintype.json')
    {
        parent::__construct();
    }

    private function load(): void // Changed return type to void for consistency with original
    {
        if (self::$loaded) {
            return; // Already loaded during this request
        }

        $jsonContent = @file_get_contents($this->pluginTypesJsonUrl);

        if ($jsonContent === false) {
            $this->lastError = "Could not fetch plugin type definitions from {$this->pluginTypesJsonUrl}";
            return;
        }

        $data = json_decode($jsonContent, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->lastError = 'Error decoding plugin type definitions: ' . json_last_error_msg();
            return;
        }

        foreach ($data as $typeInfo) {
            if (isset($typeInfo['component']) && isset($typeInfo['path'])) {
                self::$pluginTypesCache[$typeInfo['component']] = $typeInfo['path'];
            }
        }

        if (empty(self::$pluginTypesCache)) {
            $this->lastError = 'No valid plugin type paths found in the JSON data.';
        } else {
            self::$loaded = true; // Mark as loaded
        }
    }

    public function get(): array
    {
        if (empty(self::$pluginTypesCache) && !self::$loaded) { // Check cache and loaded status
            $this->load();
        }
        return self::$pluginTypesCache;
    }
}