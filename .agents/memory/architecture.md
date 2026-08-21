## moodle-checklist + IssueReporter integration

- `moodle-checklist` no longer contains report/format classes. It delegates to `tuchsoft/issue-reporter`.
- `src/Report` deleted; autoload entry `Tuchsoft\MoodleChecklist\Report\Format\` removed.
- Issue enrichment (definition lookup, message templates, severity/ref/help overrides) lives in `BaseCheckTrait`.
- `BaseCheckTrait::addTip/addWarning/addError/addIssue` build/enrich issues and call `Tuchsoft\IssueReporter\Report::addIssue()`.
- Process parsers return `Tuchsoft\IssueReporter\Issue` objects; checks add them via `addIssueObjects()` so enrichment runs.
- `Checker` creates the final `Report` with the plugin path as base path and uses `Settings::$definition` for active-check filtering.
- `FixableCheckInterface` lets a check expose a formatter. `FixPluginCommand` reuses `Checker` discovery and calls `fix($apply)` on active fixable checks.
- `FixPluginCommand` is dry-run by default; `--apply` enables file writes. Fixers call external tools (`phpcbf`, `stylelint --fix`, `prettier`, `xmllint`, `djlint`, `reformat-gherkin`, `eslint --fix`).
