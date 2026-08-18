<?php

namespace Tuchsoft\MoodleChecklist\Report\Format\Base;

use DavidBadura\MarkdownBuilder\MarkdownBuilder;
use Symfony\Component\Console\Output\BufferedOutput;
use Tuchsoft\MoodleChecklist\Report\Report;

trait MdFormatTrait
{
    use RichFormatTrait;

    protected BufferedOutput $buffer;
    protected MarkdownBuilder $builder;

    public function __construct(array $options)
    {
        parent::__construct($options);
        $this->builder = new MarkdownBuilder();
    }

    protected function getSeverityIcon(int $severity): string
    {
        return $this->getSeverityEmoji($severity) .  match ($severity) {
            Report::SEVERITY_ERROR => 'ERROR',
            Report::SEVERITY_WARNING => 'WARNING',
            Report::SEVERITY_TIP => 'TIP'
        };
    }

    protected function writeMd(): string {
        return $this->builder->getMarkdown().PHP_EOL;
    }


    public static function getFormat(): string
    {
        return self::FORMAT_MD;
    }

}