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
     */
    public function fix(bool $apply): void;
}
