<?php

namespace Tuchsoft\MoodleChecklist\Report\Format\Base;

use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;

trait JsonFormatTrait {
    public static function getOptionsDefinition():array {
        return [
            ...parent::getOptionsDefinition(),
            new InputOption('--pretty', '', InputOption::VALUE_NEGATABLE, 'Force (or disable --no-color) prettied output', false),
            new InputOption('--escape-slash', '', InputOption::VALUE_NEGATABLE, 'Force (or disable --no-color) prettied output', true),
            new InputOption('--escape-unicode', '', InputOption::VALUE_NEGATABLE, 'Force (or disable --no-color) prettied output', true),
            ];
    }


    protected function jsonEncode(mixed $value):string {
        $flags =
            ($this->options['pretty'] ? JSON_PRETTY_PRINT : 0) |
            (!$this->options['escape-slash'] ? JSON_UNESCAPED_SLASHES : 0) |
            (!$this->options['escape-unicode'] ? JSON_UNESCAPED_UNICODE : 0);
        return json_encode($value, $flags, 1024);
    }

    public static function getFormat(): string
    {
        return self::FORMAT_JSON;
    }
}