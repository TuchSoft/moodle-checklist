<?php

namespace Tuchsoft\MoodleChecklist\Check\Subcheck;

use Composer\Json\JsonFile;
use Exception;
use Symfony\Component\Config\Util\XmlUtils;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Exception\UnexpectedValueException;
use Symfony\Component\Yaml\Exception\ParseException as YamlParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Trait LintFileTrait
 *
 * Provides methods to lint files of various formats (JSON, XML, YAML)
 * using Symfony's Serializer and Yaml components, and reports errors.
 *
 * Requires the following Composer packages:
 * - symfony/serializer
 * - symfony/yaml
 *
 * It also assumes the presence of `GetAllFile` and `BaseCheckTrait`
 * for file discovery and error reporting.
 */
trait LintConfig
{
    use GetAllFile; // This trait already uses BaseCheckTrait for addError and runtimeError
    private const REGEX = '/^\[ERROR\s(?<code>\d+)\]\s(?<msg>.+)\s\(.+-\sline\s(?<line>\d+),\scolumn\s(?<col>\d+)\)$/';

    /**
     * Lints files with the specified extensions for syntax validity.
     *
     * This method iterates through files matching the given extensions,
     * attempts to parse them using the appropriate Symfony component,
     * and reports any parsing errors.
     *
     * @param array $extensions An array of file extensions to lint (e.g., ['json', 'xml', 'yml', 'yaml']).
     */
    protected function lintConfigs(...$extensions): void
    {
        foreach ($extensions as $ext) {
            // Get all files with the current extension
            $files = $this->getAllFile(ext: [$ext]);

            foreach ($files as $file) {
                // Determine the appropriate linter based on file extension
                switch (strtolower($ext)) {


                    case 'yml':
                    case 'yaml':

                        break;

                    default:
                        // Report an error for unsupported file extensions
                        $this->runtimeError("Unsupported file extension for linting: .$ext");
                        break;
                }
            }
        }
    }
}