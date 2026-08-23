<?php

namespace Tuchsoft\MoodleChecklist\Process;

/**
 * Rebuilds AMD modules via the Moodle Grunt `amd` task.
 *
 * This is a build/side-effect process rather than an issue collector, so it
 * extends AbstractProcess directly and only reports success/failure.
 */
class MoodleCiGruntAmdProcess extends AbstractProcess
{
    public function __construct(
        private string $moodleRoot,
    ) {
        parent::__construct($moodleRoot);
    }

    /**
     * @param float|null $timeout The maximum time (in seconds) the process is allowed to run.
     * Set to `null` for no timeout. Defaults to 300.0 seconds.
     */
    public function execute(?float $timeout = 300.0): bool
    {
        return parent::execute($timeout);
    }

    /**
     * @return array<string>
     */
    protected function getCommand(): array
    {
        $grunt = $this->findGrunt();
        if (!$grunt) {
            throw new \Exception('grunt not found. Install it in the Moodle root or run `npm install grunt`.');
        }

        return [$grunt, '--force', '--no-color', 'amd'];
    }

    private function findGrunt(): ?string
    {
        $candidates = [
            $this->moodleRoot . '/node_modules/.bin/grunt',
            $this->moodleRoot . '/node_modules/grunt/bin/grunt',
            // Moodle 5.1+ public docroot layout
            $this->moodleRoot . '/public/node_modules/.bin/grunt',
            $this->moodleRoot . '/public/node_modules/grunt/bin/grunt',
            // Moodle 5.2+ nested docroot layout
            $this->moodleRoot . '/../node_modules/.bin/grunt',
            $this->moodleRoot . '/../node_modules/grunt/bin/grunt',
            __DIR__ . '/../../node_modules/.bin/grunt',
            __DIR__ . '/../../node_modules/grunt/bin/grunt',
            'node_modules/.bin/grunt',
            'node_modules/grunt/bin/grunt',
        ];

        foreach ($candidates as $candidate) {
            $real = realpath($candidate);
            if ($real && is_executable($real)) {
                return $real;
            }
        }

        return null;
    }
}
