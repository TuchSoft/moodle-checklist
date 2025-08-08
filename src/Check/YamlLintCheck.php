<?php

namespace Tuchsoft\MoodleChecklist\Check;

use Symfony\Component\Yaml\Exception\ParseException as YamlParseException;
use Symfony\Component\Yaml\Yaml;
use Tuchsoft\MoodleChecklist\Check\Subcheck\GetAllFile;
use Tuchsoft\MoodleChecklist\Check\Subcheck\LintConfig;

class YamlLintCheck extends AbstractCheck
{

    use GetAllFile;

    protected function execute(): void
    {
        $files = $this->getAllFile(ext: ['yml', 'yaml']);
        foreach ($files as $file) {
            $content = @file_get_contents($file);
            if ($content === false) {
                $this->runtimeError("Failed to read file: $file");
                continue;
            }

            try {
                Yaml::parseFile($file, Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE);
            } catch (YamlParseException $e) {
                // Catch specific YAML parsing exceptions, including line number
                $this->addError('invalid_yaml_format', $e->getMessage(), $file, $e->getParsedLine());
            } catch (\Exception $e) {
                // Catch any other general exceptions during file parsing (e.g., file not found, permissions)
                $this->runtimeError("Failed to parse YAML file: $file - " . $e->getMessage());
            }
        }
    }
    
}