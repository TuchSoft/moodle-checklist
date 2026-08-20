<?php

namespace Tuchsoft\MoodleChecklist\Utils;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Tuchsoft\MoodleChecklist\Settings;
use \Symfony\Component\Console\Style\SymfonyStyle;

class InputOutput extends SymfonyStyle {

    public const OUTPUT_ERROR= 512;

    public function __construct(InputInterface $input, OutputInterface $output, private Settings $settings)
    {
        parent::__construct($input, $output);
    }

    public function info($message, $level = Settings::VERBOSITY_NORMAL): void
    {
        $this->block($message, 'INFO', 'fg=white;bg=blue', ' ', true, level: $level);
    }

    public function warning($message, $level = Settings::VERBOSITY_NORMAL): void
    {
        $this->block($message, 'WARNING', 'fg=black;bg=yellow', ' ', true, level: $level);
    }

    public function error($message, $level = Settings::VERBOSITY_NORMAL): void
    {

        $this->block($message, 'ERROR', 'fg=white;bg=red', ' ', true, level: self::OUTPUT_ERROR);
    }


    public function writeln($messages, int $type = self::OUTPUT_NORMAL): void
    {
        if ($type === self::OUTPUT_ERROR) {
            $this->getErrorOutput()->writeln($messages, self::OUTPUT_NORMAL);
        } else {
            parent::writeln($messages, $type);
        }

    }

    public function success($message, $level = Settings::VERBOSITY_NORMAL): void
    {
        $this->block($message, 'SUCCESS', 'fg=white;bg=green', ' ', true, level: $level);
    }

    public function text($message, $level = Settings::VERBOSITY_NORMAL): void
    {
        if (!$this->settings->isVerbosityAtLeast($level)) return;
        parent::text($message);
    }

    /**
     * @param string $message
     */
    public function verbose($message): void
    {
        $this->text($message, Settings::VERBOSITY_VERBOSE);
    }

    /**
     * @param string $message
     */
    public function debug($message): void
    {
        $this->text($message, Settings::VERBOSITY_DEBUG);
    }
    public function block($messages, ?string $type = null, ?string $style = null, string $prefix = ' ', bool $padding = false, bool $escape = true, $level = Settings::VERBOSITY_NORMAL): void
    {
        if ($level != self::OUTPUT_ERROR && !$this->settings->isVerbosityAtLeast($level)) return;
        parent::block($messages, $type, $style, $prefix, $padding, $escape);
    }

    /**
     * @param string[] $list
     * @param int $offset
     * @param int $level
     */
    public function printList(array $list, $offset = 1, $level = Settings::VERBOSITY_NORMAL): void
    {
        if (!$this->settings->isVerbosityAtLeast($level)) return;
        //Use a custom char "-" for the list, as "*" is already used for something else in output
        $elements = join("\n",array_map(fn ($el) => \sprintf(str_repeat(' ', $offset).'- %s', $el), $list));
        $this->text(substr($elements, 1).PHP_EOL);
    }

}