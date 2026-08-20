# Todo

Co-managed with humans. Only open items — move finished work out (agent memory's `volatile.md` tracks recently-done, this file should not).

## In progress

## Planned

## Backlog

## Done

- Plan A: stabilize `tuchsoft/issue-reporter`.
- Plan B: migrate `moodle-checklist` internal report/format code to `tuchsoft/issue-reporter`.
  - Deleted `src/Report` and removed the `Report\Format` autoload entry.
  - Moved definition enrichment into `BaseCheckTrait`.
  - Fixed `BaseCheckTrait::runtimeError()` TypeError and `Settings` undefined index.
- Plan C: update dependencies, verify parsers, and add integration tests for `moodle-checklist`.
  - Updated `moodlehq/moodle-plugin-ci` to 4.5.11 and installed PHPUnit 9.6 as dev dependency.
  - Cleaned up `bin/console` error reporting and suppressed `jasny/php-functions` deprecations.
  - Fixed `CheckLangStringInFile` to use `$this->langStrings` instead of undefined local `$langStrings`.
  - Fixed `CheckFileMimeType` to accept `text/plain` for `.md` files when `text/markdown` is allowed.
  - Fixed `PhpLintCheck` undefined `$code` variable.
  - Renamed `--exclude`/`--include` to `--exclude-check`/`--include-check` to avoid collision with PHP_CodeSniffer Config properties.
  - Wired `--format` option into `Reporter::printReport()` and aliased `json` to IssueReporter's `raw` JSON format.
  - Fixed all remaining `moodle-checklist` PHP static-analysis diagnostics (unused imports, undefined variables, missing docblocks, PHPCS option clash).
  - Removed leftover `src/Check/TestCheck.php` production test artifact.
  - Added `tests/fixtures/clean-plugin` and `tests/fixtures/dirty-plugin` fixtures.
  - Added `tests/Integration/CheckCommandTest.php` covering clean/dirty plugin runs and JSON/checkstyle formats.
