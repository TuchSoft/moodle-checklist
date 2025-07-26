<?php

namespace Tuchsoft\MoodleChecklist\Process;

use Tuchsoft\MoodleChecklist\Process\AbstractProcess;

/**
 * MoodleUpgradeProcess runs the Moodle CLI upgrade script.
 * This is typically used to install or uninstall plugins.
 */
class MoodleUpgradeProcess extends AbstractProcess
{
    /**
     * @param string $moodleRoot The absolute path to the Moodle project's root directory.
     */
    public function __construct(string $moodleRoot)
    {
        // The Moodle root will be the working directory for the command.
        parent::__construct(rtrim($moodleRoot, '/'));
    }

    /**
     * Defines the Moodle upgrade command.
     *
     * @return array<string> The command as an array of strings.
     */
    protected function getCommand(): array
    {
        return [
            'php',
            'admin/cli/upgrade.php',
            '--non-interactive',
            '--allow-unstable',
        ];
    }


}