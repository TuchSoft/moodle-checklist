<?php

namespace Tuchsoft\MoodleChecklist\Check;

use Symfony\Component\Config\Util\XmlUtils;
use Tuchsoft\MoodleChecklist\Check\Subcheck\GetAllFile;
use Tuchsoft\MoodleChecklist\Check\Subcheck\LintConfig;

class XmlLintCheck extends AbstractCheck
{
    use GetAllFile;

    private const REGEX = '/^\[ERROR\s(?<code>\d+)\]\s(?<msg>.+)\s\(.+-\sline\s(?<line>\d+),\scolumn\s(?<col>\d+)\)$/';

    protected function execute(): void
    {

        $files = $this->getAllFile(ext: ['xml']);
        foreach ($files as $file) {
            $content = @file_get_contents($file);
            if ($content === false) {
                $this->runtimeError("Failed to read file: $file");
                continue;
            }

            try {
                XmlUtils::loadFile($file);
            } catch (\InvalidArgumentException $e) {
                $lines = explode("\n", $e->getMessage());
                foreach ($lines as $line) {
                    $match = [];
                    if (preg_match(static::REGEX, $line, $match)) {
                        $msg = "(#{$match['code']}): {$match['msg']}";
                        $this->addError('invalid_xml', $msg, $file, intval($match['line']));
                    }
                }
            }

        }
    }
}