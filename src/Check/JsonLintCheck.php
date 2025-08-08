<?php

namespace Tuchsoft\MoodleChecklist\Check;

use Seld\JsonLint\ParsingException;
use Tuchsoft\MoodleChecklist\Utils\JsonValidator;
use Tuchsoft\MoodleChecklist\Check\Subcheck\GetAllFile;
use Tuchsoft\MoodleChecklist\Check\Subcheck\LintConfig;

class JsonLintCheck extends AbstractCheck
{

    use GetAllFile;

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

}