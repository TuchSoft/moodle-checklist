## Recently done

- Fixed missing-file handling in `ReadmeCheck`/`GitIgnoreCheck` via `AbstractSingleFileCheck` Template Method.
- Hardened `RemarkProcess` fatal-message parsing.
- Added `tests/fixtures/missing-files-plugin` and parallel-mode integration tests.

## Notes / next

- `GitIgnoreCheck` has pre-existing unused imports (`GetAllFile`, `SplFileInfo`); not fixed as unrelated.
- Full clean/dirty CLI run still fails in this dev env because fixtures lack a Moodle install (external checks need config.php). PHPUnit excludes those checks.
