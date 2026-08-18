<?php

namespace Tuchsoft\MoodleChecklist\Report\Format\Base;

use DavidBadura\MarkdownBuilder\MarkdownBuilder;
use Parsedown;
use Symfony\Component\Console\Output\BufferedOutput;
use Tuchsoft\MoodleChecklist\Report\Report;

trait HtmlFormatTrait
{
    use MdFormatTrait;

    protected function getSeverityIcon(int $severity): string
    {
        return $this->getSeverityEmoji($severity) .  match ($severity) {
            Report::SEVERITY_ERROR => '<span style="color:red;">ERROR</span>',
            Report::SEVERITY_WARNING => '<span style="color:orange;">WARNING</span>',
            Report::SEVERITY_TIP => '<span style="color:yellowgreen;">TIP</span>',
        };
    }

    protected function writeHtml() {
        return (new Parsedown())->text($this->writeMd());
    }

    public static function getFormat(): string
    {
        return self::FORMAT_HTML;
    }

}