# Todo

Co-managed with humans. Only open items — move finished work out (agent memory's `volatile.md` tracks recently-done, this file should not).

## In progress

## Planned

- Plan C: add/update automated tests for `moodle-checklist` after the IssueReporter migration.

## Backlog

## Done

- Plan A: stabilize `tuchsoft/issue-reporter`.
- Plan B: migrate `moodle-checklist` internal report/format code to `tuchsoft/issue-reporter`.
  - Deleted `src/Report` and removed the `Report\Format` autoload entry.
  - Moved definition enrichment into `BaseCheckTrait`.
  - Fixed `BaseCheckTrait::runtimeError()` TypeError and `Settings` undefined index.
