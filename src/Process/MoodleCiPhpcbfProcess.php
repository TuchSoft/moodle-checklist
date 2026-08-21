<?php

namespace Tuchsoft\MoodleChecklist\Process;

class MoodleCiPhpcbfProcess extends AbstractProcess
{
    public function __construct(private string $pluginPath, private string $moodleRoot)
    {
        parent::__construct($pluginPath);
    }

    protected function getCommand(): array
    {
        $phpcbf = realpath(__DIR__ . '/../../vendor/squizlabs/php_codesniffer/bin/phpcbf');
        if (!$phpcbf) {
            throw new \Exception('phpcbf binary not found.');
        }

        return [
            'php',
            '-d', 'error_reporting=E_ALL^E_DEPRECATED^E_USER_DEPRECATED',
            $phpcbf,
            '--standard=moodle',
            '--extensions=php',
            '-p',
            '--no-cache',
            '--encoding=utf-8',
            $this->pluginPath,
        ];
    }
}
