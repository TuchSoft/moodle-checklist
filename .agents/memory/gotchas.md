## IssueReporter integration fixes (Plan B)

- `Tuchsoft\IssueReporter\Command\ListFormat::$defaultName` must be `protected static $defaultName` (untyped) to match Symfony Console 5.4 parent.
- `Tuchsoft\IssueReporter\Factory` had property typo `$trasfomrer` -> `$transformer` and wrong import path for `LoadableInterface`.
- `Tuchsoft\IssueReporter\Format\Base\{Rich,Ansi,Html,Md}FormatTrait` needed `use Tuchsoft\IssueReporter\Issue;` to resolve `Issue::SEVERITY_*`.
- `moodle-checklist` process parsers must implement `getIssues(?string $code): array` and return `Tuchsoft\IssueReporter\Issue` instances.
- Process-generated issues need to be added through `BaseCheckTrait::addIssueObjects()` so definition enrichment still runs.
- `MoodleCIDocblockProcess::recursiveCopy()` called `$item->getSubPathName()` on `SplFileInfo`; use `$iterator->getSubPathName()` and `$item->getPathname()` in `copy()`.
