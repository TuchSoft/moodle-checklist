<?php

namespace Tuchsoft\MoodleChecklist\Report;

use PHP_CodeSniffer\Files\File as PhpCodeSnifferFile;
use PHP_CodeSniffer\Ruleset;
use PHP_CodeSniffer\Config;
use Tuchsoft\MoodleChecklist\Settings;

class File extends PhpCodeSnifferFile
{



    /**
     * Custom constructor for our File class.
     *
     * Initializes the parent PhpCodeSnifferFile with mock Ruleset and Config objects,
     * as we only need its addMessage functionality and not its full linting capabilities.
     *
     * @param string $path The absolute path to the file.
     * @param Settings $settings An instance of your Settings class (not directly used by parent, but passed for consistency).
     */
    public function __construct(string $path, Settings $config)
    {
        $this->path    = $path;
        $this->ruleset = null;
        $this->config  = $config;
        $this->fixer   = null;

        $parts     = explode('.', $path);
        $extension = array_pop($parts);
        if (isset($config->extensions[$extension]) === true) {
            $this->tokenizerType = $config->extensions[$extension];
        } else {
            // Revert to default.
            $this->tokenizerType = 'PHP';
        }

        $this->configCache['cache']           = $this->config->cache;
        $this->configCache['sniffs']          = array_map('strtolower', $this->config->sniffs);
        $this->configCache['exclude']         = array_map('strtolower', $this->config->exclude);
        $this->configCache['errorSeverity']   = $this->config->errorSeverity;
        $this->configCache['warningSeverity'] = $this->config->warningSeverity;
        $this->configCache['recordErrors']    = $this->config->recordErrors;
        $this->configCache['trackTime']       = $this->config->trackTime;
        $this->configCache['ignorePatterns']  = $this->ruleset->ignorePatterns;
        $this->configCache['includePatterns'] = $this->ruleset->includePatterns;

    }


}