<?php

namespace Tuchsoft\MoodleChecklist\Report\Command;


use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\ListCommand;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Helper\DescriptorHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tuchsoft\MoodleChecklist\Report\Format\Base\ParsableFormatInterface;
use Tuchsoft\MoodleChecklist\Report\FormatFactory;

class ListFormat  extends Command {

    protected static $defaultName = 'list-format';

    protected function configure(): void
    {
        parent::configure();
        $this
            ->setDescription('Runs Moodle plugin checks and generates a report.')
            ->setHelp("The <info>%command.name%</info> command lists all available output formats:\n\n<info>%command.full_name%</info>");
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->getFormatter()->setStyle('cmd', new OutputFormatterStyle('blue'));
        $io->getFormatter()->setStyle('txt', new OutputFormatterStyle('gray'));
        $io->title('Available output format');
        foreach (FormatFactory::getRegisteredFormats() as $format) {
            $parsable = is_subclass_of($format, ParsableFormatInterface::class );
            $io->text("<txt>Name:</txt> <cmd>{$format::getName()}</cmd>");
            $io->text("<txt>Description:</txt> {$format::getDesc()}");
            $io->text("<txt>Format:</txt> {$format::getFormat()}");
            $io->text('<txt>Parsable:</txt>'.($parsable ? 'yes' : 'no'));
            $io->text("<txt>Options:</txt>");
            $helper = new DescriptorHelper();
            /** @var InputOption $option */
            foreach (array_unique($format::getOptionsDefinition(), SORT_REGULAR) as $option) {
                $helper->describe($io, $option, ['format' => 'txt']);
                if ($option->isNegatable()) {
                    $default =
                        ($option->getDefault() ? '(true) ' : '(false) ') .
                        ($option->getDefault() ? '--' : '--no-') .
                        $option->getName();
                    $io->write(" <comment>[default: $default]]</comment>");
                }
                $io->newLine();
            }
            $io->newLine();
            $io->text(str_repeat('-', 20));
            $io->newLine();
        }
        return Command::SUCCESS;
    }
}