## AbstractSingleFileCheck Template Method

- `AbstractSingleFileCheck::execute()` is `final`. It validates file existence, runs base checks (size/encoding/mimetype), then calls `executeSingleFile()`.
- Single-file checks (`ReadmeCheck`, `GitIgnoreCheck`) implement `executeSingleFile()`, not `execute()`.
- This prevents subclasses from forgetting to short-circuit when the target file is missing.

## Process parser defensive defaults

- `RemarkProcess::getIssues()` must not assume remark messages contain `source`/`ruleId`/`line`.
- Fatal remark messages (e.g. "No such file or folder") only carry `fatal` and `reason`; use coalesced defaults.

## Fixable checks

- `AbstractCheck` implements `FixableCheckInterface` with safe no-op defaults.
- Checks that have a formatter override `canFix()` and `fix(bool $apply): bool`.
- `fix()` returns `true` on success, `false` on internal/runtime failure.
- `Checker` injects the `InputOutput` instance into checks via `setIo()` before running.
- `FixPluginCommand` discovers active checks, filters to `FixableCheckInterface`, and calls `fix()`.
- `FixPluginCommand` is dry-run by default; `--apply` is required to write files.
- The final summary counts only successful formatters as "ran"; failures are reported separately.
- Fixer processes report `stderr` and accept exit code `1` as success when the tool fixed (or partially fixed) files.

## File scanning exclusions

- `GetAllFile::isPathIgnored()` is the single source of truth for paths that should be skipped by checks and fixers.
- It uses `automattic/ignorefile` to combine a hardcoded safety list with the plugin's `.gitignore`.
- Hardcoded dirs: `node_modules`, `.git`, `vendor`, `.venv`, `.idea`, `.moodleplugin`, `.complex_plans`, `.agents`, `.phpunit.cache`.
- Hardcoded file: `check_upgrade_savepoints.php` (temporary CI script copied into plugin root).

## Dependency management rule

- Every runtime tool must be declared in `composer.json` (PHP), `package.json` (Node), or `requirements.txt` (Python).
- `composer.json` uses `minimum-stability: dev` (some required packages only exist as dev branches) **and** `prefer-stable: true` (so every package that *can* be stable is pinned to a stable release).
- Python tools are installed into a local `.venv/` via `bin/install-python-deps.php`, triggered by Composer's `post-autoload-dump`.
- Process classes must locate bundled binaries in `node_modules/.bin/` or `.venv/bin/` before falling back to `which`.

## Phase-based definition overrides

- `Settings` builds an ordered list of definition files: `issue_definition.json` first, then `phases/{phase}.json` when phase != `none`.
- `Definition` accepts that list and merges with `array_replace_recursive()` so later files override earlier ones.
- `--include-check` / `--exclude-check` are passed as the `override` argument to `Definition`, so they always win over phase defaults.
