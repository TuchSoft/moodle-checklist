<?php

namespace Tuchsoft\MoodleChecklist\Check;

use Symfony\Component\Config\Util\XmlUtils;
use Tuchsoft\MoodleChecklist\Check\Subcheck\GetAllFile;
use Tuchsoft\MoodleChecklist\Check\Subcheck\LintConfig;
use Tuchsoft\MoodleChecklist\Process\XmllintFixProcess;

class XmlLintCheck extends AbstractCheck implements FixableCheckInterface
{
    use GetAllFile;

    private const REGEX = '/^\[ERROR\s(?<code>\d+)\]\s(?<msg>.+)\s\(.+-\sline\s(?<line>\d+),\scolumn\s(?<col>\d+)\)$/';

    public function canFix(): bool
    {
        return true;
    }

    public function getFixerGroup(): string
    {
        return 'data';
    }

    public function getFixerDependencies(): array
    {
        return ['metadata'];
    }

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

    public function fix(bool $apply): bool
    {
        $files = $this->getAllFile(ext: ['xml']);
        if (empty($files)) {
            return true;
        }

        $xmllint = new XmllintFixProcess($files);
        if ($xmllint->isAvailable()) {
            if (!$apply) {
                $this->io->text('Would run xmllint --format on ' . count($files) . ' XML file(s).');
                return true;
            }
            $success = true;
            foreach ($files as $file) {
                $process = new XmllintFixProcess([$file]);
                $process->execute();
                $exit = $process->getExitCode();
                if ($exit !== 0 && $exit !== 1) {
                    $this->io->warning("xmllint failed for {$file}: " . trim($process->getStderr() ?: 'unknown error'));
                    $success = false;
                }
            }
            $this->io->success('XML files formatted with xmllint.');
            return $success;
        }

        if (!$apply) {
            $this->io->text('Would pretty-print ' . count($files) . ' XML file(s) using PHP DOM.');
            return true;
        }

        $success = true;
        foreach ($files as $file) {
            if (!$this->formatXmlWithDom($file)) {
                $success = false;
            }
        }
        $this->io->success('XML files pretty-printed with PHP DOM.');
        return $success;
    }

    private function formatXmlWithDom(string $file): bool
    {
        $content = @file_get_contents($file);
        if ($content === false) {
            return false;
        }

        $dom = new \DOMDocument('1.0');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        if (!@$dom->loadXML($content)) {
            return false;
        }

        $xml = $dom->saveXML();
        if ($xml === false) {
            return false;
        }
        file_put_contents($file, $xml);
        return true;
    }
}