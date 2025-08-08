<?php

namespace Tuchsoft\MoodleChecklist\Utils;

use Exception;

class LangParser extends AbstractUtils
{


    public function parse(object $plugin): ?array
    {
        $this->checkForError();

        if (!isset($plugin->fullpath) || !isset($plugin->component)) {
            $this->lastError = "Invalid Plugin object provided. Missing 'fullpath' or 'component'.";
            return null;
        }

        $pluginFullPath = rtrim($plugin->fullpath, '/\\');
        $langFileName = $plugin->component . '.php'; // e.g., 'mod_myplugin.php'
        $filePath = $pluginFullPath . DIRECTORY_SEPARATOR . 'lang' . DIRECTORY_SEPARATOR . 'en' . DIRECTORY_SEPARATOR . $langFileName;

        if (!file_exists($filePath)) {
            $this->lastError = "English language file not found for component '{$plugin->component}': {$filePath}";
            return null;
        }

        $string = []; // Initialize or reset $string for the include
        $allStrings = [];

        // Define MOODLE_INTERNAL as required by Moodle language files
        if (!defined('MOODLE_INTERNAL')) {
            define('MOODLE_INTERNAL', true);
        }

        // Temporarily suppress errors to avoid polluting output if file is malformed.
        $oldErrorLevel = error_reporting(0);
        try {
            include $filePath;
            if (is_array($string)) {
                $allStrings = $string; // For a single file, we just take its content
            } else {
                $this->lastError = "Language file '{$filePath}' did not define an array variable named \$string.";
                return null;
            }
        } catch (Exception $e) {
            $this->lastError = "Error parsing language file {$filePath}: " . $e->getMessage();
            return null; // Return null on parsing error
        } finally {
            error_reporting($oldErrorLevel); // Ensure error reporting is restored
        }

        return $allStrings;
    }


}