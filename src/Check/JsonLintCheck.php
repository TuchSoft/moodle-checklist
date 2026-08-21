<?php

namespace Tuchsoft\MoodleChecklist\Check;

use Seld\JsonLint\ParsingException;
use Tuchsoft\MoodleChecklist\Utils\JsonValidator;
use Tuchsoft\MoodleChecklist\Check\Subcheck\GetAllFile;
use Tuchsoft\MoodleChecklist\Check\Subcheck\LintConfig;
use Tuchsoft\MoodleChecklist\Process\PrettierFixProcess;

class JsonLintCheck extends AbstractCheck
{

    use GetAllFile;

    public function canFix(): bool
    {
        // PHP native fallback is always available.
        return true;
    }

    protected function execute(): void
    {
        $files = $this->getAllFile(ext: ['json']);

        foreach ($files as $file) {
            $content = @file_get_contents($file);
            if ($content === false) {
                $this->runtimeError("Failed to read file: $file");
                continue;
            }
            try {
                JsonValidator::validate($content);
            } catch (\Exception|ParsingException $e) {
                $this->addError(
                    'parse-error',
                    $e->getMessage(),
                    $file,
                    $e::class == ParsingException::class ? $e->getLine() : null
                );
            }
        }
    }

    public function fix(bool $apply): void
    {
        $files = $this->getAllFile(ext: ['json']);
        if (empty($files)) {
            return;
        }

        $prettier = new PrettierFixProcess($files);
        if ($prettier->isAvailable()) {
            if ($apply) {
                $prettier->execute();
                $exit = $prettier->getExitCode();
                if ($exit !== 0 && $exit !== 1) {
                    $this->io->warning('JSON formatting finished with errors: ' . trim($prettier->getStderr() ?: 'unknown error'));
                } else {
                    $this->io->success('JSON files formatted with prettier.');
                }
            } else {
                $this->io->text('Would run prettier on ' . count($files) . ' JSON file(s).');
            }
            return;
        }

        if (!$apply) {
            $this->io->text('Would pretty-print ' . count($files) . ' JSON file(s) using PHP.');
            return;
        }

        foreach ($files as $file) {
            $content = file_get_contents($file);
            if ($content === false) {
                continue;
            }
            $decoded = json_decode($content, true);
            if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
                continue;
            }
            $pretty = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
            file_put_contents($file, $pretty);
        }
        $this->io->success('JSON files pretty-printed.');
    }

}