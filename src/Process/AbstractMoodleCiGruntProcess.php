<?php

namespace Tuchsoft\MoodleChecklist\Process;

/**
 * AbstractMoodleCiGruntProcess provides a base for executing Moodle CI Grunt tasks.
 *
 * This class extends `AbstractIssuesProcess` to specifically handle interactions
 * with Grunt tasks commonly used in Moodle Continuous Integration environments.
 * It sets up the basic command structure for invoking Grunt with common flags.
 *
 * Child classes need to implement `getIssues()` and `parseOutput()` as per
 * `AbstractIssuesProcess`, and will define the specific Grunt task to be run
 * via their constructor or `getCommand` override.
 */
abstract class AbstractMoodleCiGruntProcess extends AbstractIssuesProcess
{

    /**
     * @param string $ploginRoot The root directory of the Moodle plugin or component.
     * @param string $task The specific Grunt task to be executed (e.g., 'amdlint', 'csslint').
     */
    public function __construct(
        string $ploginRoot,
        private string $moodleRoot,
        private string $task,
    ) {
        parent::__construct($ploginRoot);
    }

    /**
     * Returns the command array for executing a Moodle CI Grunt task.
     *
     * This method constructs the command to run the Grunt CLI,
     * including `--force` to continue on warnings/errors and `--no-color`
     * for consistent output, followed by the specific Grunt task.
     *
     * @return array<string> The command as an array of strings.
     * @inheritDoc
     */
    protected function getCommand(): array
    {
        $grunt = $this->findGrunt();
        if (!$grunt) {
            throw new \Exception('grunt not found. Install it in the Moodle root or run `npm install grunt`.');
        }
        return [$grunt, '--force', '--no-color', $this->task];
    }

    private function findGrunt(): ?string
    {
        $candidates = [
            $this->moodleRoot . '/node_modules/.bin/grunt',
            $this->moodleRoot . '/node_modules/grunt/bin/grunt',
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