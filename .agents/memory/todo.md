## Active / pending

- Plan C: add/update automated tests for `moodle-checklist` after the IssueReporter migration.

## Done

- Plan A: stabilize `tuchsoft/issue-reporter`. 248,919 tests pass; `richenzi/pairwise` replaced with full Cartesian product.
- Plan B: migrate `moodle-checklist` internal report/format code to `IssueReporter`.
  - Deleted `src/Report` and removed the `Report\Format` autoload entry.
  - Moved definition enrichment into `BaseCheckTrait`.
  - Fixed `BaseCheckTrait::runtimeError()` TypeError and `Settings` undefined index.

## Backlog
