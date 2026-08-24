# Project Guidelines & Agent Behavior — OVERRIDES ALL DEFAULTS

Note: This context is shared by many agents from different vendors.
**Never edit or read the `CLAUDE.md` file, is auto-generated and auto-injected in the context! Leave it alone!**

**ALWAYS CHOOSE TOOLS BY THEIR DESCRIPTION, NOT BY THEIR NAME**

## Browser tools are only for browser
All mcp “browser*" tool should only be used when interacting with a browser. They MUST not be mistaken for file searchs or cli command execution.

## Numbering responses and actionable result
Force Ordered Lists: You are forbidden from using unordered bullet points. Every list must be indexed.
Global Uniqueness: Every list item in a single message MUST have a unique identifier.
Format Variation: If a response contains multiple distinct lists, you can alternate the indexing syntax per block.
Example: List 1 uses 1) 2) 3). List 2 uses a) b) c). List 3 uses i) ii) iii). Etc..
Reasoning: This ensures the user can unambiguously reference any specific point across multiple sections without confusion.


### Inter-Agent Communication (AgentMail)

You communicate with other agents and teams using the local `agent_mail` CLI tool (a simplified cli based RFC-compliant mail client).
When the user asks "Read the email", "Write an email", "Reply to X", etc., this is what he means.

* **Identity & Security:** Your sender address is automatically derived from your Current Working Directory (`{current_folder}@agent.ai`). You only have access to your own inbox.
* **Stateful Reading:** Messages have states (New/Unread/Read). Reading a message automatically marks it as read, hiding it from the default inbox view to prevent duplicate processing.
* **In-project coordination:** Identity is per-directory, every agent working in the same project shares one mailbox — there is no separate per-agent address within a project. You can still use this system to coordinate with other agents in the same project by sending to yourself (target = your own folder name). When you do this, be extra careful: use a specific, consistent Subject per topic, and state who is writing and who the message is for directly in the body (e.g. `"Fixing feature X agent" -> "Backend main agent": ...`), since every message in that shared inbox has the same From address. `reply` handles this automatically: replying to a message that was itself sent from your own shared mailbox goes back to that mailbox.

**Available Commands**
Use standard bash execution to interact with the mail system:

1. **Check Inbox:**
   * `agent_mail list` (Shows only New/Unread messages)
   * `agent_mail list --all` (Shows the full history, including read messages)
   * `agent_mail list --thread "Subject"` (Shows every message in that thread, chronologically)
2. **Read Message:** `agent_mail read <id>` (Prints the message and silently marks it as read)
3. **Send Message:** Pipe your text directly into the command.
   * Syntax: `echo "Message body" | agent_mail send <target> "Subject"`
   * Example: `echo "API is ready." | agent_mail send frontend "API Update"` (The `@agent.ai` domain is auto-appended if omitted).
   * Multiple recipients: comma-separate targets, e.g. `agent_mail send "frontend,backend" "API Update"`. Everyone in the list receives an identical copy — there is no separate CC/BCC.
   * For anything longer than one line, use a heredoc instead of `echo` so formatting/quoting survives intact: `agent_mail send frontend "API Update" <<'EOF'\n...multi-line body...\nEOF`
4. **Reply to a Message:** Pipe your text directly into the command.
   * `echo "..." | agent_mail reply "Subject"` — replies to the latest message in that thread, sender only.
   * `echo "..." | agent_mail reply --id <id>` — replies to one specific message, sender only.
   * `echo "..." | agent_mail reply --all "Subject"` — same, but also sends to every other original recipient (minus yourself). Use this when the message you're replying to went to a group and your answer is relevant to everyone (status updates, group decisions, broadcasts). Use the sender-only default for private replies or side conversations.
   * `echo "..." | agent_mail reply --all --include-self "Subject"` — REQUIRES `--all`. Same as `--all`, but also keeps yourself (your shared mailbox) in the recipient list instead of excluding it. Use this when replying to a mix of external recipients and other agents sharing your own in-project mailbox, and both groups need to see the reply.
   * Prefer `reply` over `send` whenever you are responding to an existing message — it automatically keeps the Subject/thread intact and links the messages via headers.
   * Same rule applies here: use a heredoc for long or multi-line reply bodies, e.g.:
   ```bash
   agent_mail reply "Subject" <<'EOF'
   ...
   EOF
   ```.
5. **Cleanup:** `agent_mail delete <id>` or `agent_mail delete --thread "Subject"` (Permanently removes messages when a task is fully completed or obsolete, ask for permission first in interactive session).

## Prompt compression
This project use an automatic message and tool ouput token compression.
It might happen that you see "strange" artifacts in the context, those are not error.
The compression methods are:
- Whitespace
- Caveman (useless wording)
- RTK (tool output, tool may return a "strangly formatted" result, the still contain all the info anyway)
- Content deduplication (large chunk of text get replaced by reference if found twice in the context)

## Project Documentation (`./docs`)

**Human-readable source of truth. AI-maintained to stay in sync with code.**

- `@docs/index.md` — entry point. Index of all docs with short descriptions.
- `@docs/todo.md` — co-managed with humans. Roadmap, bugs, planned features.
- **MANDATORY:** After any code change, update the relevant `./docs` files to match the new state. If unsure whether a change warrants a doc update, ask before doing.

## Definition of Done (DoD)

Task is incomplete until:
1. **Tests:** Relevant unit/integration tests written, updated, passing. Do NOT auto-run tests unless asked.
2. **Docs aligned:** `./docs` (including `todo.md`) updated to reflect new state.
3. **AI memory updated:** Any new pattern, gotcha, decision, or structural change reflected in `.agents/memory/`.

## Memory (`.agents/memory`)

**`.agents/memory` is your only memory. You own it. Humans don't touch it. Model it to your own mental model**

These files are automatically injected at the bottom of this project's agent context file at every session, so you don't need to read them (they are already in context).

This is a collective memory shared by many agents.

Use the information you receive to help you understand the project.
Trust this information, it is kept up to date!
Use the filemap to navigate.

**Remember to remember!** Don't wait for the user to prompt you to save things — be proactive.

### Files you maintain

* `.agents/memory/architecture.md`: Mini compressed system map. Key modules, how they connect, data flow. Fast mental model — not a copy of human docs.
* `.agents/memory/patterns.md`: "Always do X this way" rules discovered while working. Conventions, constraints, enforced idioms.
* `.agents/memory/decisions.md`: Why (not what) choices are made. Append-only log. Never delete. Format: `YYYY-MM-DD: decision — why`.
* `.agents/memory/gotchas.md`: Warnings + non-obvious quirks. Things that cost time or cause bugs if unknown.
* `.agents/memory/filemap.md`: Where stuff lives. File-level by default. Folder-level only when the folder name is self-explanatory and contents are uniform.
* `.agents/memory/volatile.md`: Temporary stuff — like RAM. Pass info session to session, track ephemeral status. Note when data should be deleted, and clean often.
* `.agents/memory/index.md`: (not proper memory, must be kept in sync) slim summarized version of `docs/index.md`.
* `.agents/memory/todo.md`: (not proper memory, must be kept in sync) slim summarized version of `docs/todo.md`.

### Rules

- Caveman style. You're the only reader. Fragments ok. Ultra short.
- Update immediately when you discover something worth saving.
- Delete/overwrite stale entries. No bloat.
- These are NOT architecture docs — those live in `./docs`. These are your runtime notes.
- Raw logic. Fragments only. Technical terms exact. Shorthand.
- Drop most articles (a/an/the) when context is enough, everything non-essential.
- SMS shorthand when context is enough: u, ur, r, b/c, msg, pls, thx, b4, 2, lmk

# Planning

**FOR COMPLEX, MULTI-FILE EDITS, ALWAYS GENERATE A MARKDOWN PLAN FIRST:** If the user requests a complex task that requires editing multiple files, traversing the project, or making many changes, **YOU MUST** create a markdown plan and ask the user to confirm it before proceeding. Use the `complex_plans_createPlan` tool (and subsequent `complex_plans_readPlan`, `complex_plans_updatePlan`, `complex_plans_listPlans`, `complex_plans_openInEditor`, and optionally `complex_plans_deletePlan` tools).

**ALWAYS** ask the user to review and accept the plan after calling `complex_plans_openInEditor` and **BEFORE** doing anything else. Do not proceed with the implementation until the user has accepted the plan.

Follow the instructions provided by each tool. When asked to create a plan, always use `complex_plans_createPlan`.

**IMPORTANT**: Ignore the built-in `EnterPlanMode` and `ExitPlanMode` tools — use `complex_plans_*` instead for all planning workflows. This tool might be deferred; when the user talks about planning, first use `tool_search` to load the `complex_plans_*` toolset.

# Project context

Overview of the index of the human documentation, refer to it if necessary, read file when needed.

- CLI entry `bin/console check <plugin-path>`.
- `bin/console fix <plugin-path>` is dry-run by default; `--apply` writes files.
- `--phase=none|pre-build|post-build` switches validation profiles.
- `--moodle-root=<path>` overrides guessed Moodle root for Moodle 5.1+ `public/` docroot.
- `--refresh-gitignore-cache` forces refresh of gitignore.io template cache for the `fix` command.
- Checks: `marketplaceimages` (poster/screenshots), `image` (source image quality), `gitignore` (with auto-fixer), `filestructure` (required files/dirs, encoding, forbidden artifacts; partial auto-fixer).
- Runtime deps: `composer.json` (PHP), `package.json` (Node), `requirements.txt` (Python via `.venv/`).
- `tuchsoft/issue-reporter` is a VCS dependency (`https://github.com/TuchSoft/issue-reporter.git`).
- Composer config: `minimum-stability: dev` + `prefer-stable: true`; `dealerdirect/phpcodesniffer-composer-installer` allowed.
- Native image optimizer npm binaries need build tooling on non-x86_64 platforms.
- All `src/Process/*` classes default to a 300-second timeout; heavy lint/build tasks can complete on large plugins.
- `src/Process/MoodleCiGruntAmdProcess.php` runs the `grunt amd` rebuild after `eslint --fix`.
- Docs live in `./docs`; this file mirrors `docs/index.md`.

### Active / pending

### Done

- Fixed `composer.lock` resolution: added `prefer-stable: true`, regenerated lock.
- Added `--phase` option to `check` command.
  - `none` = current behavior.
  - `pre-build` disables `filestructure.forbidden-dir`/`forbidden-file`.
  - `post-build` disables source-only checks + whole `marketplaceimages`/`readme`/`gitignore`/`repository` checks.
  - `--include-check`/`--exclude-check` override phase defaults.
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
- Renamed `ImagesCheck` -> `MarketplaceImagesCheck`; added `ImageCheck` for source images.
  - Deps: `intervention/image` (PHP); optimizer binaries via npm (`pngquant-bin`, `cwebp-bin`, `gifsicle`, `mozjpeg`, `svgo`); Python tools via `requirements.txt` (`djlint`, `reformat-gherkin`).
  - `bin/install-python-deps.php` wired into `composer.json` `post-autoload-dump`.
  - New `src/Process/Image/*` optimizer processes; `DjlintFixProcess`/`GherkinFixProcess` now check `.venv/bin/` first.
  - Fixtures + integration tests for `ImageCheck`, `MarketplaceImagesCheck`, optimizer processes.
- Implemented `GitIgnoreCheck` fixer.
  - Cache gitignore.io template during `composer install`/`update`; TTL 30d, stale fallback.
  - Rebuild `.gitignore` from template + Moodle block + `### Project defined ###` user section.
  - Marker-based parser (`# Created by ...` / `# End of ...`) keeps re-runs idempotent.
  - `--refresh-gitignore-cache` flag on `fix` command.
  - New: `src/GitIgnore/GitIgnoreTemplateCache.php`, `src/GitIgnore/GitIgnoreAssembler.php`, `bin/cache-gitignore-templates.php`, `data/gitignore/`, unit + integration tests.
- Fixed missing-file handling in `ReadmeCheck` and `GitIgnoreCheck`.
  - `AbstractSingleFileCheck` Template Method; subclasses implement `executeSingleFile()`.
  - `RemarkProcess::getIssues()` coalesces `source`/`ruleId`/`line` defaults.
  - Fixture `tests/fixtures/missing-files-plugin` + parallel-mode integration tests.
- Implemented partial auto-fixer for `FileStructureCheck`.
  - `FileStructureCheck` now implements `FixableCheckInterface`.
  - Scaffolds `.moodleplugin/`, `README.md`, `CHANGELOG.md`, `LICENSE.md`, `CONTRIBUTING.md`, `.gitignore` with TODO placeholders.
  - Re-encodes known text files to UTF-8 using `iconv`.
  - Fixture `tests/fixtures/filestructure-fix-plugin/` + `tests/Integration/FileStructureFixCommandTest.php`.
  - Updated `docs/index.md` and agent memory.
- Raised all process timeouts to 300 seconds; moved `JsLintCheck::rebuildAmd()` to `MoodleCiGruntAmdProcess`.
  - Previous timeout fix missed a hardcoded 120 s call in `JsLintCheck::rebuildAmd()`, causing `grunt amd` to time out during `fix --apply` on large plugins.
  - `AbstractProcess`, `AbstractIssuesProcess`, `MoodleCiEslintProcess`, `MoodleCiPhpcsProcess`, `MoodleCISavepointProcess`, and `MoodleCiGruntAmdProcess` now default to 300 s.
  - Updated `docs/index.md` and agent memory.

### Backlog

# Agent memory

-----
**From here down: memory files. These are effectively your memories; it's your responsibility to keep them up to date.**
-----


### Fix / formatter layer

- `src/Check/FixableCheckInterface.php` — interface for checks that can auto-format; declares group/dependencies for parallel scheduling.
- `src/Cli/FixPluginCommand.php` — `fix` command, dry-run by default, `--apply` writes; schedules fixer groups in dependency waves.
- `src/Cli/FixerGroupScheduler.php` — partitions fixers into concurrency groups and orders them into waves.
- `src/Process/ParallelFixProcess.php` — runs one wave of fixer groups as parallel subprocesses.
- `tests/Unit/Cli/FixerGroupSchedulerTest.php` — unit tests for the scheduler.
- `tests/Integration/FixParallelCommandTest.php` — integration tests for parallel `fix`.
- `src/Check/PhpCsCheck.php` — PHPCS check + `phpcbf` fixer.
- `src/Check/JsLintCheck.php` — ESLint check + `eslint --fix` + `grunt amd` fixer.
- `src/Process/MoodleCiPhpcsProcess.php` — runs `phpcs --report-json`.
- `src/Process/MoodleCiPhpcbfProcess.php` — runs `phpcbf`.
- `src/Process/MoodleCiEslintProcess.php` / `MoodleCiEslintFixProcess.php` — ESLint check/fix.
- `src/Process/MoodleCiGruntAmdProcess.php` — `grunt amd` rebuild after `eslint --fix`.
- `src/Process/MoodleCiStylelintFixProcess.php` — `stylelint --fix`.
- `src/Process/PrettierFixProcess.php` — `prettier` for JSON/YAML/Markdown.
- `src/Process/XmllintFixProcess.php` — `xmllint --format`.
- `src/Process/DjlintFixProcess.php` — `djlint --reformat` for Mustache.
- `src/Process/GherkinFixProcess.php` — `reformat-gherkin` for feature files.
- `src/Process/Image/AbstractImageOptimizerProcess.php` — base for image optimizer processes.
- `src/Process/Image/PngquantProcess.php`, `MozjpegProcess.php`, `SvgoProcess.php`, `CwebpProcess.php`, `GifsicleProcess.php` — image optimizer wrappers.
- `src/Check/MarketplaceImagesCheck.php` — renamed from `ImagesCheck`; validates `.moodleplugin/` poster + screenshots.
- `src/Check/ImageCheck.php` — validates all source images (format, MIME, size, dimensions, location, naming, EXIF, compression).
- `src/Check/FileStructureCheck.php` — validates required files/dirs, MIME types, encoding, forbidden artifacts; now implements fixer that scaffolds missing items and re-encodes text files to UTF-8.
- `src/Check/GitIgnoreCheck.php` — validates `.gitignore`; implements fixer that rebuilds it from gitignore.io template + Moodle patterns + user section.
- `tests/fixtures/filestructure-fix-plugin/` — fixture for `FileStructureCheck` fixer tests.
- `tests/Integration/FileStructureFixCommandTest.php` — integration tests for `FileStructureCheck` fixer.
- `src/GitIgnore/GitIgnoreTemplateCache.php` — fetches/caches gitignore.io template.
- `src/GitIgnore/GitIgnoreAssembler.php` — parses and re-assembles `.gitignore` by marker comments.
- `bin/cache-gitignore-templates.php` — Composer script that populates `data/gitignore/default.template`.
- `data/gitignore/` — cache directory for gitignore.io template.
- `tests/Unit/GitIgnore/GitIgnoreAssemblerTest.php` — unit tests for assembler parser.
- `tests/Unit/GitIgnore/GitIgnoreTemplateCacheTest.php` — unit tests for template cache.
- `tests/Integration/GitIgnoreFixCommandTest.php` — integration tests for gitignore fix command.
- `requirements.txt` — Python deps (`djlint`, `reformat-gherkin`).
- `bin/install-python-deps.php` — creates `.venv/` and installs Python deps.
- `tests/fixtures/formatting-plugin/` — fixture for `fix` command tests.
- `tests/Integration/FixCommandTest.php` — integration tests for dry-run and apply.
- `tests/Integration/ImageCheckTest.php` — integration tests for `ImageCheck`.
- `tests/Integration/MarketplaceImagesCheckTest.php` — integration tests for `MarketplaceImagesCheck`.
- `tests/Integration/ImageOptimizerProcessTest.php` — verifies optimizer binaries are available.

### moodle-checklist + IssueReporter integration

- `moodle-checklist` no longer contains report/format classes. It delegates to `tuchsoft/issue-reporter`.
- `src/Report` deleted; autoload entry `Tuchsoft\MoodleChecklist\Report\Format\` removed.
- Issue enrichment (definition lookup, message templates, severity/ref/help overrides) lives in `BaseCheckTrait`.
- `BaseCheckTrait::addTip/addWarning/addError/addIssue` build/enrich issues and call `Tuchsoft\IssueReporter\Report::addIssue()`.
- Process parsers return `Tuchsoft\IssueReporter\Issue` objects; checks add them via `addIssueObjects()` so enrichment runs.
- `Checker` creates the final `Report` with the plugin path as base path and uses `Settings::$definition` for active-check filtering.
- `FixableCheckInterface` lets a check expose a formatter. `FixPluginCommand` reuses `Checker` discovery and calls `fix($apply)` on active fixable checks.
- `FixPluginCommand` is dry-run by default; `--apply` enables file writes. Fixers call external tools (`phpcbf`, `stylelint --fix`, `prettier`, `xmllint`, `djlint`, `reformat-gherkin`, `eslint --fix`) except `GitIgnoreCheck` which writes the file directly.
- `GitIgnoreTemplateCache` fetches and caches the gitignore.io template; `GitIgnoreAssembler` parses/rebuilds `.gitignore` by marker comments.
- `VersionParser` derives `moodleroot` from the plugin path and now accepts an explicit `--moodle-root` from the CLI. For Moodle 5.1+ `public/` docroot it falls back to the parent directory when `admin/cli/upgrade.php` is found there.

### AbstractSingleFileCheck Template Method

- `AbstractSingleFileCheck::execute()` is `final`. It validates file existence, runs base checks (size/encoding/mimetype), then calls `executeSingleFile()`.
- Single-file checks (`ReadmeCheck`, `GitIgnoreCheck`) implement `executeSingleFile()`, not `execute()`.
- This prevents subclasses from forgetting to short-circuit when the target file is missing.

### Process parser defensive defaults

- `RemarkProcess::getIssues()` must not assume remark messages contain `source`/`ruleId`/`line`.
- Fatal remark messages (e.g. "No such file or folder") only carry `fatal` and `reason`; use coalesced defaults.

### Fixable checks

- `FixableCheckInterface` is implemented only by checks that actually provide a formatter.
- `AbstractCheck` does not implement `FixableCheckInterface`; fixable checks declare it explicitly.
- Checks that implement the interface provide `canFix()` and `fix(bool $apply): bool`.
- `fix()` returns `true` on success, `false` on internal/runtime failure.
- Fixers also implement `getFixerGroup(): string` and `getFixerDependencies(): array` so `FixPluginCommand` can schedule them safely.
- `Checker` injects the `InputOutput` instance into checks via `setIo()` before running.
- `FixPluginCommand` discovers active checks and keeps only those that implement `FixableCheckInterface`.
- `FixPluginCommand` is dry-run by default; `--apply` is required to write files.
- The final summary counts only successful formatters as "ran"; failures and missing tools are reported as "skipped". Checks without a fixer do not appear in the output.
- Fixer processes report `stderr` and accept exit code `1` as success when the tool fixed (or partially fixed) files.

### Fixer concurrency groups

- Fixers are partitioned into concurrency groups (`bootstrap`, `metadata`, `php`, `js`, `css`, `mustache`, `gherkin`, `data`, `image`).
- Checks in the same group run sequentially; independent groups run in parallel waves based on declared dependencies.
- `bootstrap` (`FileStructureCheck`) runs first because it scaffolds files other fixers may read.
- `metadata` (`GitIgnoreCheck`, `ReadmeCheck`) runs next because it rewrites `.gitignore` and `README.md`.
- All other groups depend on `metadata` so they never race on `.gitignore` reads.
- Custom fixers must return a group and dependencies; pick an existing group or declare a new one with the correct dependencies.

### Fixer parallel execution

- `FixPluginCommand` spawns one subprocess per group for each wave using `ParallelFixProcess`.
- Each subprocess runs `bin/console fix --fixer-group=<group> --no-parallel` and prints a `MOODLE_CHECKLIST_FIXER_SUMMARY` marker.
- Parent parses the marker, strips it from visible output, and aggregates ran/failed/skipped counts.
- `--jobs` caps groups per wave; default is 4.

### FileStructure fixer rules

- `FileStructureCheck::fix()` only creates missing items; it never overwrites or deletes existing files/directories.
- Required files are scaffolded with TODO comments/placeholders so humans know to fill them in.
- `.gitignore` is created with only a TODO comment; the `GitIgnoreCheck` fixer is responsible for populating standard patterns.
- UTF-8 re-encoding uses `mb_convert_encoding()` with `//TRANSLIT//IGNORE` and skips files already detected as UTF-8 or ASCII.

### GitIgnore fixer marker convention

- `.gitignore` is rebuilt from three sections: gitignore.io template, Moodle-specific additions, and a user `### Project defined ###` section.
- Managed sections use gitignore.io-style markers (`# Created by ...` / `# End of ...`) so the same parser handles both gitignore.io blocks and our Moodle block.
- No pattern-level deduplication is performed; git ignores duplicate rules natively.
- Re-running the fixer is idempotent because it replaces blocks by their markers and preserves the `### Project defined ###` body.

### File scanning exclusions

- `GetAllFile::isPathIgnored()` is the single source of truth for paths that should be skipped by checks and fixers.
- It uses `automattic/ignorefile` to combine a hardcoded safety list with the plugin's `.gitignore` and any paths declared in `thirdpartylibs.xml`.
- Hardcoded dirs: `node_modules`, `.git`, `vendor`, `.venv`, `.idea`, `.moodleplugin`, `.complex_plans`, `.agents`, `.phpunit.cache`.
- `thirdpartylibs.xml` locations are parsed by `ThirdPartyLibsParser` and added as directory patterns (e.g. `amd/src/select2/`).
- No hardcoded temporary-file exclusions: checks that need to run a vendor script do so via stdin/CWD instead of copying files into the plugin root.

### Dependency management rule

- Every runtime tool must be declared in `composer.json` (PHP), `package.json` (Node), or `requirements.txt` (Python).
- `composer.json` uses `minimum-stability: dev` (some required packages only exist as dev branches) **and** `prefer-stable: true` (so every package that *can* be stable is pinned to a stable release).
- Python tools are installed into a local `.venv/` via `bin/install-python-deps.php`, triggered by Composer's `post-autoload-dump`.
- Process classes must locate bundled binaries in `node_modules/.bin/` or `.venv/bin/` before falling back to `which`.
- Node image-optimizer binaries (`pngquant-bin`, `mozjpeg`, `gifsicle`, `cwebp-bin`) need native build tooling on non-x86_64 platforms because they compile from source. The consuming environment (e.g. Docker image) must provide it; document this in the project docs.

### Phase-based definition overrides

- `Settings` builds an ordered list of definition files: `issue_definition.json` first, then `phases/{phase}.json` when phase != `none`.
- `Definition` accepts that list and merges with `array_replace_recursive()` so later files override earlier ones.
- `--include-check` / `--exclude-check` are passed as the `override` argument to `Definition`, so they always win over phase defaults.

### Process timeout policy

- All `src/Process/*` classes default to a 300-second timeout (`AbstractProcess` / `AbstractIssuesProcess`) so heavy lint/build tasks can finish on large plugins.
- Do not hardcode shorter timeouts inline (e.g. `new Process(..., 120)`). If a process needs a different default, override `execute()` and document why.
- Callers can still pass `null` for no timeout or a custom value per run.

### Parallel mode treats child stderr as fatal

- `Checker` merges child-process stderr in parallel mode. Any PHP warning/notice from a single check becomes a top-level fatal error.
- Single-file checks must not emit PHP warnings when their target file is missing; use `AbstractSingleFileCheck` Template Method.

### Native image optimizer binaries on ARM

- `pngquant-bin`, `mozjpeg`, `gifsicle`, `cwebp-bin` ship x86_64 prebuilt binaries.
- On ARM64 Linux they fall back to compiling and fail without native build tools (`build-essential`, `automake`, `libtool`, `nasm`, `libpng-dev`, `libjpeg-dev`, `pkg-config`).
- Consuming Docker images must install build tooling before `npm i`.

### Moodle 5.1+ `public/` docroot breaks root guessing

- `VersionParser` guessed `moodleroot` by stripping the plugin path from `fullpath`.
- For Moodle 5.1+ this yields `/var/www/html/public` (web docroot) instead of `/var/www/html` (project root).
- Fix: accept `--moodle-root` CLI option; when absent, fall back to looking for `admin/cli/upgrade.php` one directory up.

### PHPCS `moodle` standard not registered without composer installer

- `dealerdirect/phpcodesniffer-composer-installer` must be allowed in `composer.json` `allow-plugins`.
- If disabled, `vendor/squizlabs/php_codesniffer/CodeSniffer.conf` is never created and `--standard=moodle` fails.

### GitIgnore fixer relies on marker comments

- The fixer identifies its managed sections by `# Created by ...` / `# End of ...` markers, just like gitignore.io.
- If a user deletes or renames those markers, the next run will treat the whole file as user content and wrap it in a fresh `### Project defined ###` section.
- Duplicate rules are harmless because git ignores them, but the file will grow if markers are repeatedly removed.

### Missing single-file check targets

- `ReadmeCheck` and `GitIgnoreCheck` previously crashed when `README.md`/`.gitignore` were absent.
  - `ReadmeCheck` called `lintMarkdown()` after `parent::execute()` returned early.
  - `GitIgnoreCheck` overrode `execute()` without ever checking file existence.
- Fixed by enforcing the Template Method in `AbstractSingleFileCheck`.

2026-08-20: Integrated PHPCS + `fix` command — because the tool already ships `phpcs`/`phpcbf` via `moodle-plugin-ci` but never ran them. Added a separate `fix` command (dry-run by default, `--apply` writes) and per-check `FixableCheckInterface` so formatting stays aligned with validation. Chose `prettier` for JSON/YAML/Markdown, `djlint` for Mustache, and `reformat-gherkin` for Gherkin because no official Moodle formatters exist for those file types and they can be bundled in the Docker image.
2026-08-21: Renamed `ImagesCheck` to `MarketplaceImagesCheck` and added `ImageCheck` for source images — keeps marketplace metadata validation separate from strict source-image quality rules. Listed all new dependencies (PHP, Node, Python) instead of relying on system packages, because the tool is consumed by other Docker images and must be self-describing. Used npm binary wrappers (`pngquant-bin`, `cwebp-bin`, `gifsicle`, `mozjpeg`) rather than system packages so optimizers ship with the project and Process classes can find them in `node_modules/.bin`.
2026-08-21: Fixed `composer.lock` resolution by adding `"prefer-stable": true` while keeping `minimum-stability: dev`. Dev stability is still required for packages like `symfony/serializer`, `schlessera/markdown-escape`, `automattic/ignorefile`, and `tuchsoft/issue-reporter`, but `prefer-stable` prevents unrelated transitive packages from drifting onto dev branches. This matches the original Composer best-practice advice from the dependency-hell incident and avoids adding fake root requirements for transitive packages.
2026-08-21: Changed `FixableCheckInterface::fix()` return type from `void` to `bool` so `FixPluginCommand` can distinguish successful formatters from failed ones and report accurate counts. Accepts breaking the interface because the project is still internal and no external checks are known. Keeps `canFix()` unchanged; unavailable formatters are still reported as skipped rather than failed.
2026-08-21: Centralized file exclusion in `GetAllFile` using `automattic/ignorefile` plus hardcoded rules. Reusing the existing dependency avoids a custom parser and keeps the safety list consistent across all checks/fixers. Parallel subprocess errors are caught per-check and converted into `runtime-error` issues so one failing check does not abort the whole run.
2026-08-21: Added `--moodle-root` option and `VersionParser` public-dir heuristic — because the `moodle` CLI wrapper knows the real project root but `moodle-checklist` was guessing from the plugin path. Passing an absolute root is the cleanest cross-project fix; the heuristic keeps standalone usage working on Moodle 5.1+ `public/` layouts.
2026-08-21: Fixed `MoodleCISavepointProcess` file collision by using a unique temp filename in the plugin root instead of the hardcoded `check_upgrade_savepoints.php`. The script uses `dirname(__FILE__)` so it still checks the correct plugin files.
2026-08-22: Replaced the temp-file copy in `MoodleCISavepointProcess` with stdin execution. The vendor script is piped to `php` with CWD set to the plugin root; `dirname(__FILE__)` from stdin resolves to `.` (the plugin root), so no temporary file is created and no cleanup is needed.
2026-08-21: Enabled `dealerdirect/phpcodesniffer-composer-installer` in `composer.json` so the `moodle` PHPCS standard and its sniff dependencies are auto-registered. The alternative was manual `installed_paths` configuration, which is fragile and easy to forget.
2026-08-21: Kept native npm image optimizer binaries (`pngquant-bin`, `mozjpeg`, `gifsicle`, `cwebp-bin`) as declared deps instead of replacing them with `sharp` or `intervention/image`. They provide better format-specific optimization; ARM/non-x86_64 build tooling is the consuming image's responsibility, documented in `docs/index.md`.
2026-08-21: Changed `tuchsoft/issue-reporter` repository from `path` to VCS (`https://github.com/TuchSoft/issue-reporter.git`) in `composer.json`. A local path only worked on the development machine; VCS makes `composer install` self-contained for any user or Docker build.
2026-08-22: Removed `FixableCheckInterface` from `AbstractCheck` and implemented it only on checks that ship a formatter. This makes the interface meaningful: if a check implements it, `FixPluginCommand` knows it can fix; otherwise it is ignored. Keeps the existing `canFix()`/`fix()` contract and the skipped-message for genuinely missing tools.
2026-08-22: Implemented GitIgnoreCheck fixer without pattern-level deduplication. Git handles duplicate ignore rules natively, so deduplication adds complexity without benefit. The fixer uses gitignore.io-style markers (`# Created by ...` / `# End of ...`) to keep managed blocks idempotent across re-runs and to preserve user patterns in a `### Project defined ###` section.
2026-08-22: Cache gitignore.io template at `composer install`/`composer update` time via `bin/cache-gitignore-templates.php` and store it under `data/gitignore/`. This lets the fixer work offline immediately after install. TTL is 30 days; stale cache is used if the network refresh fails.
2026-08-23: Raised default process timeouts to 300 seconds and added `MoodleCiGruntAmdProcess` — the previous timeout fix only updated base process classes, leaving a hardcoded 120-second timeout in `JsLintCheck::rebuildAmd()`. That caused `npx grunt amd` to time out during `fix --apply` on larger plugins. All `src/Process/*` classes now default to 300 seconds, with explicit overrides only where a different default is needed. The AMD rebuild now uses a dedicated process class so it shares the same timeout policy.
2026-08-23: Implemented parallel `fix` execution with dependency-aware groups — heavy formatters (PHP, JS, CSS, images, etc.) can run concurrently, while `FileStructureCheck` (bootstrap) and `GitIgnoreCheck`/`ReadmeCheck` (metadata) are isolated to avoid file races. Added `getFixerGroup()` and `getFixerDependencies()` to `FixableCheckInterface`, a `FixerGroupScheduler` for topological waves, and `ParallelFixProcess` to run one wave of groups as subprocesses. Kept `--no-parallel` and `--jobs` options so callers can opt out or cap concurrency.

### Recently done

- Fixed SavePoint/DocBlock/PHPCS runtime errors for Moodle 5.2 public/ docroot.
- Added `--moodle-root` option to `check`/`fix` commands.
- Switched `tuchsoft/issue-reporter` from local path repo to VCS.
- Committed changes to moodle-checklist main.

### Notes / next

- `GitIgnoreCheck` has pre-existing unused imports (`GetAllFile`, `SplFileInfo`); not fixed as unrelated.
- Full clean/dirty CLI run still fails in this dev env because fixtures lack a Moodle install (external checks need config.php). PHPUnit excludes those checks.

