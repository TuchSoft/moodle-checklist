<?php

namespace Tuchsoft\MoodleChecklist\Cli;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tuchsoft\MoodleChecklist\Settings;
use Tuchsoft\MoodleChecklist\Utils\InputOutput;

abstract class AbstractCommand extends Command
{
    private $validParams = true;

    protected Settings $settings;
    protected InputOutput $io;

    abstract protected function validateInput(InputInterface $input): array|false;
    abstract protected function main(): int;

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        parent::initialize($input, $output);
        if (($options = $this->validateInput($input)) === false) {
            $this->validParams = false;
        }
        $options['verbosity'] = $output->getVerbosity();
        $this->settings = new Settings($options);
        $this->io = new InputOutput($input, $output, $this->settings);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {

        if (!$this->validParams) {
            $this->io->error("Argument does not seems to be valid, tyeue -vvv to get more details");
            return Command::FAILURE;
        }

        try {
            return $this->main($this->settings);
        } catch (\Exception $e) {
            if ($this->settings->isVerbose()) {
                throw $e;
            }
            $this->io->error("An unexpected error occurred (use '--verbose' to get more details): {$e->getMessage()}");
            return Command::FAILURE;
        }
    }

}

