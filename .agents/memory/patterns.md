## AbstractSingleFileCheck Template Method

- `AbstractSingleFileCheck::execute()` is `final`. It validates file existence, runs base checks (size/encoding/mimetype), then calls `executeSingleFile()`.
- Single-file checks (`ReadmeCheck`, `GitIgnoreCheck`) implement `executeSingleFile()`, not `execute()`.
- This prevents subclasses from forgetting to short-circuit when the target file is missing.

## Process parser defensive defaults

- `RemarkProcess::getIssues()` must not assume remark messages contain `source`/`ruleId`/`line`.
- Fatal remark messages (e.g. "No such file or folder") only carry `fatal` and `reason`; use coalesced defaults.
