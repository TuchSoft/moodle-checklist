<?php

namespace Tuchsoft\MoodleChecklist\Check;

/**
 * Interface for checks that can also auto-format the files they validate.
 */
interface FixableCheckInterface
{
    /**
     * Unique check name, matching the one used in the issue definition.
     */
    public static function getName(): string;

    /**
     * Whether this check has a formatter available in the current environment.
     */
    public function canFix(): bool;

    /**
     * Run the formatter.
     *
     * @param bool $apply If true, overwrite files. If false, dry-run only.
     * @return bool True if the formatter ran successfully, false if it failed.
     */
    public function fix(bool $apply): bool;

    /**
     * Concurrency group this fixer belongs to.
     *
     * Fixers in the same group run sequentially. Groups with no unresolved
     * dependencies run in parallel.
     */
    public function getFixerGroup(): string;

    /**
     * Concurrency groups that must finish before this group can start.
     *
     * @return string[]
     */
    public function getFixerDependencies(): array;
}
