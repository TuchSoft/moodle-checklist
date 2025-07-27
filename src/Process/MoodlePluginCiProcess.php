<?php

namespace Tuchsoft\MoodleChecklist\Process;

class MoodlePluginCiProcess extends AbstractProcess
{

    public function __construct(private readonly string $command, private readonly array $options)
    {
        parent::__construct();
        parent();
    }

    /**
     * @inheritDoc
     */
    protected function getCommand(): array
    {
        return [
            realpath(__DIR__.'/../../xvendor/moodle-plugin-ci/bin/moodle-plugin-ci'),
            $this->command,
            '--ansi',
            ...$this->options
        ];
    }

}