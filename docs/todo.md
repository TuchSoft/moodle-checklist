# Todo

Co-managed with humans. Only open items — move finished work out (agent memory's `volatile.md` tracks recently-done, this file should not).

## In progress

## Planned

## Backlog

## Done

- Fixed `composer.lock` resolution conflict caused by `composer/composer dev-main` requiring a newer `justinrainbow/json-schema` than the locked `dev-master`.
  - Added `"prefer-stable": true` to `composer.json` (kept `minimum-stability: dev`).
  - Regenerated `composer.lock`; `composer/composer` now resolves to stable `2.10.2` and `justinrainbow/json-schema` to stable `6.10.0`.
  - Verified `composer update --lock --no-install` succeeds and image/check tests pass.
- Integrated PHPCS as a check (`moodle-plugin-ci.phpcs`) and added a `fix` command.
  - New checks: `PhpCsCheck`, `JsLintCheck`.
  - New `fix` command, dry-run by default, `--apply` writes files.
  - Formatters added for PHP (`phpcbf`), JavaScript (`eslint --fix` + `grunt amd`), CSS/SCSS (`stylelint --fix`), JSON/YAML/Markdown (`prettier`), XML (`xmllint --format`), Mustache (`djlint --reformat`), Gherkin (`reformat-gherkin`).
  - Added `tests/fixtures/formatting-plugin` and `tests/Integration/FixCommandTest.php`.
- Added `--phase` option to the `check` command.
  - `none` preserves current behavior.
  - `pre-build` disables `forbidden-dir`/`forbidden-file` for dev dependencies.
  - `post-build`: disables source-only checks and whole checks (`marketplaceimages`, `readme`, `gitignore`, `repository`).
  - `--include-check` / `--exclude-check` still override phase defaults.
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
- Renamed `ImagesCheck` to `MarketplaceImagesCheck` and added a new `ImageCheck` for source images.
  - New dependencies: `intervention/image` (PHP), image optimizer binaries via npm (`pngquant-bin`, `cwebp-bin`, `gifsicle`, `mozjpeg`, `svgo`), and Python tools via `requirements.txt` (`djlint`, `reformat-gherkin`).
  - Added `bin/install-python-deps.php` and wired it into Composer's `post-autoload-dump`.
  - Added `src/Process/Image/*` optimizer process classes and updated `DjlintFixProcess`/`GherkinFixProcess` to find `.venv/bin/` first.
  - Added fixtures and integration tests for `ImageCheck`, `MarketplaceImagesCheck`, and image optimizer processes.
- Implemented auto-fixer for `GitIgnoreCheck`.
  - Fetches and caches gitignore.io template; cache is refreshed during `composer install`/`composer update`.
  - Rebuilds `.gitignore` from gitignore.io template + Moodle-specific patterns + user-defined `### Project defined ###` section.
  - Uses gitignore.io-style markers (`# Created by ...` / `# End of ...`) so the parser can replace managed blocks on re-runs.
  - Added `--refresh-gitignore-cache` option to the `fix` command.
  - Added `src/GitIgnore/GitIgnoreTemplateCache.php`, `src/GitIgnore/GitIgnoreAssembler.php`, `bin/cache-gitignore-templates.php`, and unit/integration tests.
- Fixed missing-file handling in `ReadmeCheck` and `GitIgnoreCheck`.
  - Refactored `AbstractSingleFileCheck` to a Template Method so subclasses can't skip file-existence validation.
  - Hardened `RemarkProcess::getIssues()` against remark fatal messages that lack `source`/`ruleId`.
  - Added `tests/fixtures/missing-files-plugin` and parallel-mode integration tests for missing `README.md` and `.gitignore`.
