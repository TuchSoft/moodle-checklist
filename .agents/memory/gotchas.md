## Parallel mode treats child stderr as fatal

- `Checker` merges child-process stderr in parallel mode. Any PHP warning/notice from a single check becomes a top-level fatal error.
- Single-file checks must not emit PHP warnings when their target file is missing; use `AbstractSingleFileCheck` Template Method.

## Missing single-file check targets

- `ReadmeCheck` and `GitIgnoreCheck` previously crashed when `README.md`/`.gitignore` were absent.
  - `ReadmeCheck` called `lintMarkdown()` after `parent::execute()` returned early.
  - `GitIgnoreCheck` overrode `execute()` without ever checking file existence.
- Fixed by enforcing the Template Method in `AbstractSingleFileCheck`.
