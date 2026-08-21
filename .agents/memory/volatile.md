## Recently done

- Implemented `--phase` option for `check` command (`none`/`pre-build`/`post-build`).
- Added `phases/pre-build.json` and `phases/post-build.json` profiles.
- Changed `Definition` to accept multiple definition files and merge with `array_replace_recursive`.
- Updated `docs/index.md`, `docs/todo.md`, and memory files.

## Notes / next

- `GitIgnoreCheck` has pre-existing unused imports (`GetAllFile`, `SplFileInfo`); not fixed as unrelated.
- Full clean/dirty CLI run still fails in this dev env because fixtures lack a Moodle install (external checks need config.php). PHPUnit excludes those checks.
