<?php

namespace Tuchsoft\MoodleChecklist\Report\Format\Base;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Tuchsoft\MoodleChecklist\Report\Report;


interface FormatInterface {

    const FORMAT_TXT = 'txt';
    const FORMAT_HTML = 'html';
    const FORMAT_XML = 'xml';
    const FORMAT_JSON = 'json';
    const FORMAT_MD = 'markdown';


    static function getName(): string;
    static function getDesc(): string;
    static function getFormat(): string;
    function generate(Report $report): string;

    /**
     * @return InputOption[]
     */
    static function getOptionsDefinition(): array;
    function setOptions(array $options);

}