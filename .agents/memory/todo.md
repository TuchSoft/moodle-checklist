## Active / pending

## Done

- Plan A: stabilize `tuchsoft/issue-reporter`. 248,919 tests pass; `richenzi/pairwise` replaced with full Cartesian product.
- Plan B: migrate `moodle-checklist` internal report/format code to `IssueReporter`.
  - Deleted `src/Report` and removed the `Report\Format` autoload entry.
  - Moved definition enrichment into `BaseCheckTrait`.
  - Fixed `BaseCheckTrait::runtimeError()` TypeError and `Settings` undefined index.
- Plan C: update deps, verify parsers, add integration tests for `moodle-checklist`.
  - `moodlehq/moodle-plugin-ci` 4.5.11; PHPUnit 9.6 dev dep.
  - `bin/console` error_reporting = E_ALL & ~E_DEPRECATED before autoload.
  - Fixed `CheckLangStringInFile` undefined var bug.
  - Fixed `CheckFileMimeType` markdown-as-text/plain false positive.
  - Fixed `PhpLintCheck` undefined `$code`.
  - Renamed `--exclude`/`--include` → `--exclude-check`/`--include-check`; no PHPCS collision.
  - Wired `--format` to `Reporter::printReport()`; `json` aliased to `raw`.
  - Fixed all `moodle-checklist` PHP static-analysis diagnostics.
  - Removed `src/Check/TestCheck.php` artifact.
  - Fixtures `tests/fixtures/clean-plugin` + `tests/fixtures/dirty-plugin`.
  - `tests/Integration/CheckCommandTest.php` clean/dirty + format smoke tests.

## Backlog
