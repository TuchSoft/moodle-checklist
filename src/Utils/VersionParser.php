<?php

namespace Tuchsoft\MoodleChecklist\Utils;



use stdClass;
use Throwable;

class VersionParser extends AbstractUtils
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
     * @param string|null $moodleRoot Optional absolute path to the Moodle project root.
     *                               If provided and absolute, it is used directly.
     *                               Otherwise the root is guessed from the plugin path.
     * @return array|null An associative array of plugin data, or null on error.
     */
    public function parse(string $pluginFullPath, ?string $moodleRoot = null): ?array
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
                    $resolved = true;
                    break;
                }
            }

            if (!$resolved) {
                $this->lastError = "Could not determine plugin type and name from component or path for: {$pluginFullPath}";
                return null;
            }
        }

        $guessedRoot = substr($pluginData['fullpath'], 0, strlen($pluginData['path']) * -1);

        if ($moodleRoot !== null && $moodleRoot !== '' && str_starts_with($moodleRoot, '/')) {
            $pluginData['moodleroot'] = rtrim($moodleRoot, '/');
        } else {
            $pluginData['moodleroot'] = $guessedRoot;
            // Moodle 5.1+ uses a 'public/' web docroot. If the guessed root is the
            // docroot, move up one level to the actual project root. We detect the
            // project root by looking for admin/cli/upgrade.php, which is always
            // located there. This keeps standalone usage working when the wrapper
            // does not pass an explicit Moodle root.
            $upgradeFile = $pluginData['moodleroot'] . '/admin/cli/upgrade.php';
            if (!file_exists($upgradeFile)) {
                $parentRoot = dirname($pluginData['moodleroot']);
                if ($parentRoot !== $pluginData['moodleroot'] && file_exists($parentRoot . '/admin/cli/upgrade.php')) {
                    $pluginData['moodleroot'] = $parentRoot;
                }
            }
        }

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