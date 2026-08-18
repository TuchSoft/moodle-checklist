<?php

namespace Tuchsoft\MoodleChecklist\Report\Format\Base;

use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tuchsoft\MoodleChecklist\Report\Report;
use Tuchsoft\MoodleChecklist\Report\Utils\Formatter;
use Tuchsoft\MoodleChecklist\Report\Utils\NullInput;

trait AnsiFormatTrait
{
    use RichFormatTrait;

    protected BufferedOutput $buffer;
    protected SymfonyStyle $builder;

    public function __construct(array $options)
    {
        parent::__construct($options);
        $this->buffer = new BufferedOutput();
        $this->buffer->setVerbosity(OutputInterface::VERBOSITY_NORMAL);
        $this->buffer->setFormatter(new Formatter($this->options['color'], maxWidth: $this->options['max-width']));
        $this->builder = new SymfonyStyle(new NullInput(),  $this->buffer);
    }

    protected function getSeverityIcon(int $severity): string
    {
        return $this->getSeverityEmoji($severity) .  match ($severity) {
                Report::SEVERITY_ERROR => '<error>ERROR</error>',
                Report::SEVERITY_WARNING => '<warning>WARNING</warning>',
                Report::SEVERITY_TIP => '<tip>TIP</tip>'
            };
    }

    public static function getFormat(): string
    {
        return self::FORMAT_TXT;
    }

}