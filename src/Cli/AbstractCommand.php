<?php

namespace Tuchsoft\MoodleChecklist\Cli;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

abstract class AbstractCommand extends Command
{
    protected SymfonyStyle $io;

    abstract protected function validateInput(InputInterface $input): array|false;
    abstract protected function main(array $options): int;

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        parent::initialize($input, $output);
        $this->io = new SymfonyStyle($input, $output);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {

        if (($options = $this->validateInput($input)) === false) {
            return Command::FAILURE;
        }
        return $this->main($options);
    }

    protected function info($msg): void
    {
        $this->io->block($msg, 'INFO', 'fg=white;bg=blue', ' ', true);
    }

    protected function warning($msg): void
    {
        $this->io->block($msg, 'WARNING', 'fg=black;bg=yellow', ' ', true);
    }

    protected function error($msg): void
    {
        $this->io->block($msg, 'ERROR', 'fg=white;bg=red', ' ', true);
    }

    protected function success($msg): void
    {
        $this->io->block($msg, 'SUCCESS', 'fg=white;bg=green', ' ', true);
    }

    protected function text($msg): void
    {
        $this->io->text($msg);
    }

    protected function printList(array $list): void
    {
        $this->io->listing($list);
    }
}

