<?php

namespace Tuchsoft\MoodleChecklist\Report\Format\Base;

use Symfony\Component\Console\Input\InputOption;
use Tuchsoft\MoodleChecklist\Report\Report;

trait RichFormatTrait
{

    static public function getOptionsDefinition():array {
        return[
            ...parent::getOptionsDefinition(),
            new InputOption('--max-width', '', InputOption::VALUE_OPTIONAL, 'Max line width (in character) of the output, 0 means no wrap, in tabled output is used as per-column width', 0),
            new InputOption('--color', '', InputOption::VALUE_NEGATABLE, 'Force (or disable --no-color) colored output (ANSI)', true),
            new InputOption('--emoji', '', InputOption::VALUE_NEGATABLE, 'Force (or disable --no-emoji) emoji output', true),
        ];
    }

    protected function isColored(): bool {
        return $this->options['color'];
    }
    protected function areEmojiActive(): bool {
        return $this->options['emoji'];
    }

    protected function getSeverityEmoji(int $severity): string
    {
        if (!$this->areEmojiActive()) return '';
        return match ($severity) {
            Report::SEVERITY_ERROR =>  "\u{274C}  ",
            Report::SEVERITY_WARNING => "\u{26A0}\u{FE0F} ",
            Report::SEVERITY_TIP => "\u{1F4A1} "
        };
    }

}