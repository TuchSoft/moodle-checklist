<?php

namespace Tuchsoft\MoodleChecklist\Report\Format\Base;
use Tuchsoft\MoodleChecklist\Report\Report;

interface ParsableFormatInterface extends FormatInterface {

    function parse(string $input, string $name = 'Parsed report'): Report;

}