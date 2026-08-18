<?php

namespace Tuchsoft\MoodleChecklist\Report\Format\Base;

use DOMDocument;
use Symfony\Component\Console\Input\InputOption;
use Tuchsoft\MoodleChecklist\Report\Report;

trait XmlFormatTrait {
    public static function getOptionsDefinition():array {
        return [
            ...parent::getOptionsDefinition(),
            new InputOption('--pretty', '', InputOption::VALUE_NEGATABLE, 'Force (or disable --no-color) prettied output', false),
            ];
    }


    protected function saveXML(DOMDocument $doc):bool|string {
        if ($this->options['pretty']) {
            $doc->formatOutput = true;
        }
        return $doc->saveXML();
    }

    public static function getFormat(): string
    {
        return self::FORMAT_XML;
    }


}