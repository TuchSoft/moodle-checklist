## AbstractSingleFileCheck Template Method

- `AbstractSingleFileCheck::execute()` is `final`. It validates file existence, runs base checks (size/encoding/mimetype), then calls `executeSingleFile()`.
- Single-file checks (`ReadmeCheck`, `GitIgnoreCheck`) implement `executeSingleFile()`, not `execute()`.
- This prevents subclasses from forgetting to short-circuit when the target file is missing.

## Process parser defensive defaults

- `RemarkProcess::getIssues()` must not assume remark messages contain `source`/`ruleId`/`line`.
- Fatal remark messages (e.g. "No such file or folder") only carry `fatal` and `reason`; use coalesced defaults.

## Fixable checks

- `FixableCheckInterface` is implemented only by checks that actually provide a formatter.
- `AbstractCheck` does not implement `FixableCheckInterface`; fixable checks declare it explicitly.
- Checks that implement the interface provide `canFix()` and `fix(bool $apply): bool`.
- `fix()` returns `true` on success, `false` on internal/runtime failure.
- `Checker` injects the `InputOutput` instance into checks via `setIo()` before running.
- `FixPluginCommand` discovers active checks and keeps only those that implement `FixableCheckInterface`.
- `FixPluginCommand` is dry-run by default; `--apply` is required to write files.
- The final summary counts only successful formatters as "ran"; failures and missing tools are reported as "skipped". Checks without a fixer do not appear in the output.
- Fixer processes report `stderr` and accept exit code `1` as success when the tool fixed (or partially fixed) files.

## FileStructure fixer rules

- `FileStructureCheck::fix()` only creates missing items; it never overwrites or deletes existing files/directories.
- Required files are scaffolded with TODO comments/placeholders so humans know to fill them in.
- `.gitignore` is created with only a TODO comment; the `GitIgnoreCheck` fixer is responsible for populating standard patterns.
- UTF-8 re-encoding uses `mb_convert_encoding()` with `//TRANSLIT//IGNORE` and skips files already detected as UTF-8 or ASCII.

## GitIgnore fixer marker convention

- `.gitignore` is rebuilt from three sections: gitignore.io template, Moodle-specific additions, and a user `### Project defined ###` section.
- Managed sections use gitignore.io-style markers (`# Created by ...` / `# End of ...`) so the same parser handles both gitignore.io blocks and our Moodle block.
- No pattern-level deduplication is performed; git ignores duplicate rules natively.
- Re-running the fixer is idempotent because it replaces blocks by their markers and preserves the `### Project defined ###` body.

## File scanning exclusions

- `GetAllFile::isPathIgnored()` is the single source of truth for paths that should be skipped by checks and fixers.
- It uses `automattic/ignorefile` to combine a hardcoded safety list with the plugin's `.gitignore`.
- Hardcoded dirs: `node_modules`, `.git`, `vendor`, `.venv`, `.idea`, `.moodleplugin`, `.complex_plans`, `.agents`, `.phpunit.cache`.
- No hardcoded temporary-file exclusions: checks that need to run a vendor script do so via stdin/CWD instead of copying files into the plugin root.

## Dependency management rule

- Every runtime tool must be declared in `composer.json` (PHP), `package.json` (Node), or `requirements.txt` (Python).
- `composer.json` uses `minimum-stability: dev` (some required packages only exist as dev branches) **and** `prefer-stable: true` (so every package that *can* be stable is pinned to a stable release).
- Python tools are installed into a local `.venv/` via `bin/install-python-deps.php`, triggered by Composer's `post-autoload-dump`.
- Process classes must locate bundled binaries in `node_modules/.bin/` or `.venv/bin/` before falling back to `which`.
- Node image-optimizer binaries (`pngquant-bin`, `mozjpeg`, `gifsicle`, `cwebp-bin`) need native build tooling on non-x86_64 platforms because they compile from source. The consuming environment (e.g. Docker image) must provide it; document this in the project docs.

## Phase-based definition overrides

- `Settings` builds an ordered list of definition files: `issue_definition.json` first, then `phases/{phase}.json` when phase != `none`.
- `Definition` accepts that list and merges with `array_replace_recursive()` so later files override earlier ones.
- `--include-check` / `--exclude-check` are passed as the `override` argument to `Definition`, so they always win over phase defaults.
