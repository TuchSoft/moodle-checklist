<?php

namespace Tuchsoft\MoodleChecklist\Cli;

use Tuchsoft\MoodleChecklist\Check\FixableCheckInterface;

/**
 * Partitions fixable checks into concurrency groups and orders the groups
 * into waves based on declared dependencies.
 *
 * Checks in the same group run sequentially. Groups in the same wave run
 * concurrently. A wave is started only after all groups it depends on have
 * completed.
 */
class FixerGroupScheduler
{
    /**
     * @param FixableCheckInterface[] $checks
     * @return array<int, array<string, FixableCheckInterface[]>>
     */
    public function schedule(array $checks): array
    {
        /** @var array<string, FixableCheckInterface[]> $groups */
        $groups = [];
        foreach ($checks as $check) {
            $groups[$check->getFixerGroup()][] = $check;
        }

        if ($groups === []) {
            return [];
        }

        $waves = [];
        $done = [];
        $remaining = array_keys($groups);

        while ($remaining !== []) {
            $wave = [];
            foreach ($remaining as $index => $group) {
                $deps = $this->getGroupDependencies($groups[$group]);
                $unfinished = array_diff($deps, $done);
                if ($unfinished === []) {
                    $wave[] = $group;
                    unset($remaining[$index]);
                }
            }

            if ($wave === []) {
                throw new \RuntimeException('Circular or unsatisfiable dependency in fixer groups: ' . implode(', ', $remaining));
            }

            foreach ($wave as $group) {
                $done[] = $group;
            }

            $waveMap = [];
            foreach ($wave as $group) {
                $waveMap[$group] = $groups[$group];
            }
            $waves[] = $waveMap;
        }

        return $waves;
    }

    /**
     * @param FixableCheckInterface[] $checks
     * @return string[]
     */
    private function getGroupDependencies(array $checks): array
    {
        $deps = [];
        foreach ($checks as $check) {
            foreach ($check->getFixerDependencies() as $dep) {
                $deps[$dep] = true;
            }
        }
        return array_keys($deps);
    }
}
