<?php

namespace Tuchsoft\MoodleChecklist\Utils;

use Exception;

class PhpFileLoader extends AbstractUtils
{


    public function load($filePath, $var): mixed
    {
        $this->checkForError();


        if (!file_exists($filePath)) {
            $this->lastError = "File not found: {$filePath}";
            return null;
        }

        // Define MOODLE_INTERNAL as required by Moodle language files
        if (!defined('MOODLE_INTERNAL')) {
            define('MOODLE_INTERNAL', true);
        }

        // Temporarily suppress errors to avoid polluting output if file is malformed.
        $oldErrorLevel = error_reporting(0);
        try {
            unset($$var);
            require $filePath;
            $value = $$var;
            if (!$value) {
                $this->lastError = "Error, variable `$var` is not defined in '{$filePath}'";
                return null;
            }
        } catch (Exception $e) {
            $this->lastError = "Error loading file {$filePath}: " . $e->getMessage();
            return null;
        } finally {
            error_reporting($oldErrorLevel); // Ensure error reporting is restored
        }
        return $value;
    }


}