<?php

namespace Tuchsoft\MoodleChecklist\Action;



use stdClass;
use Throwable;

class VersionParser extends AbstractAction
{
    private array $pluginTypePaths;

    public function __construct()
    {
        $pluginResolver = new PluginType();
        $this->pluginTypePaths = $pluginResolver->get();
        $this->initError = $pluginResolver->getLastError();
        parent::__construct();
    }



    /**
     * Parses plugin information from its full path and version.php.
     *
     * @param string $pluginFullPath The absolute path to the plugin directory.
     * @return array|null An associative array of plugin data, or null on error.
     */
    public function parse(string $pluginFullPath): ?array
    {
        $this->checkForError();
        $pluginFullPath = rtrim($pluginFullPath, '/\\');

        if (!is_dir($pluginFullPath)) {
            $this->lastError = "Plugin directory not found: {$pluginFullPath}";
            return null;
        }

        $versionFilePath = $pluginFullPath . DIRECTORY_SEPARATOR . 'version.php';
        if (!file_exists($versionFilePath)) {
            $this->lastError = "version.php not found for plugin: {$pluginFullPath}";
            return null;
        }

        $pluginData = [
            'fullpath' => $pluginFullPath,
            'type' => null,
            'name' => null,
            'path' => null,
            'fullname' => null,
            'version' => null,
            'release' => null,
            'component' => null,
            'maturity' => null,
            'requires' => null,
            'dependencies' => null,
            'supported' => null,
            'moodleroot' => null
        ];

        // 1. Parse version.php to get component and other details
        $versionInfo = $this->parseVersionFile($versionFilePath);
        if ($versionInfo === null) {
            return null; // Error already set by parseVersionFile
        }
        $pluginData = array_merge($pluginData, $versionInfo);
        preg_match('/^([a-z0-9]+)_([a-z0-9_]+)$/', $pluginData['component'], $matches);

        // 2. Determine type and name using component first
        if ($pluginData['component'] && preg_match('/^([a-z0-9]+)_([a-z0-9_]+)$/', $pluginData['component'], $matches)) {
            $componentType = $matches[1];
            $componentName = $matches[2];

            // Validate against known types from JSON

            if (array_key_exists($componentType, $this->pluginTypePaths)) {
                $pluginData['type'] = $componentType;
                $pluginData['name'] = $componentName;
                $pluginData['path'] = $this->pluginTypePaths[$componentType] . '/' . $componentName;
                $pluginData['fullname'] = $pluginData['type'] . '_' . $pluginData['name'];
            }
        }

        // 3. Fallback: Determine type and name from fullpath if not resolved by component
        if ($pluginData['type'] === null || $pluginData['name'] === null) {
            $resolved = false;
            foreach ($this->pluginTypePaths as $typeName => $typePathSegment) {
                // Construct a regex pattern to match the fullpath
                // e.g., /path/to/moodle/local/myplugin
                // or /path/to/moodle/mod/myplugin
                // We need to match the segment before the plugin name
                $pattern = '#/' . preg_quote($typePathSegment, '#') . '/([^/]+)$#';
                if (preg_match($pattern, $pluginFullPath, $matches)) {
                    $pluginData['type'] = $typeName;
                    $pluginData['name'] = $matches[1];
                    $pluginData['path'] = $typePathSegment . '/' . $pluginData['name'];
                    $pluginData['fullname'] = $pluginData['type'] . '_' . $pluginData['name'];
                    $resolved = true;
                    break;
                }
            }

            if (!$resolved) {
                $this->lastError = "Could not determine plugin type and name from component or path for: {$pluginFullPath}";
                return null;
            }
        }

        $pluginData['moodleroot'] = substr($pluginData['fullpath'], 0, strlen($pluginData['path']) * -1);

        return $pluginData;
    }

    private function parseVersionFile(string $versionFilePath): ?array
    {
        $plugin = new stdClass(); // Temporary object to capture variables

        // Temporarily suppress errors to avoid polluting output if file is malformed.
        $oldErrorLevel = error_reporting(0);
        define('MOODLE_INTERNAL', 1);
        define('MATURITY_ALPHA', 50);
        define('MATURITY_BETA', 100);
        define('MATURITY_RC', 150);
        define('MATURITY_STABLE', 200);
        define('ANY_VERSION', 'any');
        try {
            include $versionFilePath;
        } catch (Throwable $e) {
            $this->lastError = "Error parsing version.php for plugin {$versionFilePath}: " . $e->getMessage();
            error_reporting($oldErrorLevel);
            return null;
        }
        error_reporting($oldErrorLevel);

        return [
            'version' => $plugin->version ?? null,
            'release' => $plugin->release ?? null,
            'component' => $plugin->component ?? null,
            'maturity' => $plugin->maturity ?? null,
            'requires' => $plugin->requires ?? null,
            'dependencies' => $plugin->dependencies ?? null,
            'supported' => $plugin->supported ?? null,
        ];
    }


}