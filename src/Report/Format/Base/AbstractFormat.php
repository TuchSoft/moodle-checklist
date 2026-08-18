<?php

namespace Tuchsoft\MoodleChecklist\Report\Format\Base;

use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;

abstract class AbstractFormat implements FormatInterface
{

    public int $maxLineLenght = 60;
    public array $options = [];

    public function __construct(array $options) {
        $this->setOptions($options);
    }

    static function getName(): string
    {
        $splitted = explode('\\', static::class);
        return strtolower(array_pop($splitted));
    }


    static public function getOptionsDefinition():array {
        return[
            new InputOption('--show-ref', '', InputOption::VALUE_NEGATABLE, 'Show (or don\'t show --no-show-ref) external reference field', false),
            new InputOption('--show-help', '', InputOption::VALUE_NEGATABLE, 'Show (or don\'t show --no-show-help) help (fix) field', true),
            new InputOption('--show-code', '', InputOption::VALUE_NEGATABLE, 'Show (or don\'t show --no-show-code) issue code field', true),
        ];
    }

    public function setOptions(array $options): void {
        foreach($this->getOptionsDefinition() as $option) {
            $this->options[$option->getName()] = $options[$option->getName()] ?? $option->getDefault();
        }
    }

}