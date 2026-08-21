## Recently done

- Fixed SavePoint/DocBlock/PHPCS runtime errors for Moodle 5.2 public/ docroot.
- Added `--moodle-root` option to `check`/`fix` commands.
- Switched `tuchsoft/issue-reporter` from local path repo to VCS.
- Committed changes to moodle-checklist main.

## Notes / next

- `GitIgnoreCheck` has pre-existing unused imports (`GetAllFile`, `SplFileInfo`); not fixed as unrelated.
- Full clean/dirty CLI run still fails in this dev env because fixtures lack a Moodle install (external checks need config.php). PHPUnit excludes those checks.
