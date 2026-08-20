## IssueReporter integration fixes (Plan B)

- `Tuchsoft\IssueReporter\Command\ListFormat::$defaultName` must be `protected static $defaultName` (untyped) to match Symfony Console 5.4 parent.
- `Tuchsoft\IssueReporter\Factory` had property typo `$trasfomrer` -> `$transformer` and wrong import path for `LoadableInterface`.
- `Tuchsoft\IssueReporter\Format\Base\{Rich,Ansi,Html,Md}FormatTrait` needed `use Tuchsoft\IssueReporter\Issue;` to resolve `Issue::SEVERITY_*`.
- `moodle-checklist` process parsers must implement `getIssues(?string $code): array` and return `Tuchsoft\IssueReporter\Issue` instances.
- Process-generated issues need to be added through `BaseCheckTrait::addIssueObjects()` so definition enrichment still runs.
- `MoodleCIDocblockProcess::recursiveCopy()` called `$item->getSubPathName()` on `SplFileInfo`; use `$iterator->getSubPathName()` and `$item->getPathname()` in `copy()`.
- `--exclude-check`/`--include-check` replaced `--exclude`/`--include` in `bin/console check` to avoid collision with PHP_CodeSniffer Config properties (which expect sniff codes).
- `jasny/php-functions` (required by `jasny/phpdoc-parser`) emits PHP deprecation warnings on PHP 8.5+. Suppress with `error_reporting(E_ALL & ~E_DEPRECATED)` before requiring `vendor/autoload.php`.
- `finfo` detects Markdown files as `text/plain` on macOS. When checking `.md` files, allow `text/plain` if `text/markdown` is in the allowed list.
- `CheckLangStringInFile` had a bug referencing undefined `$langStrings` and literal `'$strname'` keys; fixed to use `$this->langStrings[$strname]`.
- `--format` is now wired to `Reporter::printReport()`. IssueReporter has no dedicated `json` format; `json` is aliased to `raw` (which is JSON).
- Fixture files under `tests/fixtures/` are Moodle-style (version.php, lang files) and intentionally have no PSR-4 namespace; static-analysis namespace warnings on them are expected.
