<?php

namespace Tuchsoft\MoodleChecklist\Report\Format;

use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Tuchsoft\MoodleChecklist\Report\Format\Base\AbstractFormat;
use Tuchsoft\MoodleChecklist\Report\Format\Base\JsonFormatTrait;
use Tuchsoft\MoodleChecklist\Report\Format\Base\ParsableFormatInterface;
use Tuchsoft\MoodleChecklist\Report\Report;

class Raw extends AbstractFormat implements ParsableFormatInterface
{

    use JsonFormatTrait;

    public function generate(Report $report): string
    {
        return $this->jsonEncode($report);
    }

    public function parse(string $input, $name = null): Report
    {
       return Report::fromJson(json_decode($input));
    }

    static function getDesc(): string
    {
        return 'Complete JSON rappresetation';
    }


}