<?php

namespace Tuchsoft\MoodleChecklist\Check;

use Symfony\Component\Yaml\Exception\ParseException as YamlParseException;
use Symfony\Component\Yaml\Yaml;
use Tuchsoft\MoodleChecklist\Check\Subcheck\GetAllFile;
use Tuchsoft\MoodleChecklist\Check\Subcheck\LintConfig;
use Tuchsoft\MoodleChecklist\Process\PrettierFixProcess;

class YamlLintCheck extends AbstractCheck implements FixableCheckInterface
{

    use GetAllFile;

    public function canFix(): bool
    {
        return (new PrettierFixProcess([]))->isAvailable();
    }

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

    public function fix(bool $apply): bool
    {
        $files = $this->getAllFile(ext: ['yml', 'yaml']);
        if (empty($files)) {
            return true;
        }

        $process = new PrettierFixProcess($files);
        if (!$process->isAvailable()) {
            $this->io->warning('prettier is not available; skipping YAML fixer.');
            return false;
        }

        if (!$apply) {
            $this->io->text('Would run prettier on ' . count($files) . ' YAML file(s).');
            return true;
        }

        $process->execute();
        $exit = $process->getExitCode();
        if ($exit !== 0 && $exit !== 1) {
            $this->io->warning('YAML formatting finished with errors: ' . trim($process->getStderr() ?: 'unknown error'));
            return false;
        }
        $this->io->success('YAML files formatted with prettier.');
        return true;
    }

}