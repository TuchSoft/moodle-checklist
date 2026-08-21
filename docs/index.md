# Project Documentation

Entry point for the human-readable docs. AI-maintained to stay in sync with the code.

`moodle-checklist` is a CLI tool that validates Moodle plugins against a set of coding, documentation, and repository-quality checks.

## CLI usage

### `check`

```bash
./bin/console check <plugin-path> [options]
```

Validates the plugin and reports issues.

### `fix`

```bash
./bin/console fix <plugin-path> [options]
```

Auto-formats the plugin files. **Dry-run by default**; add `--apply` to write changes. After applying, re-run `check` to verify any remaining issues.

### Common options

- `--phase=<phase>` — choose a validation profile:
  - `none` (default): current behavior, all checks active.
  - `pre-build`: validate the source repository during development. Disables `forbidden-dir`/`forbidden-file` so dependency directories (e.g. `node_modules`, `vendor`) and lockfiles are allowed.
  - `post-build`: validate a built distribution artifact. Disables source-only checks such as `.moodleplugin/`, `.git/`, README/CHANGELOG/LICENSE/CONTRIBUTING, `.gitignore`, screenshots, and repository-history checks.
- `--include-check=<check>` / `--exclude-check=<check>` — include or exclude individual checks. These always take precedence over `--phase`.
- `--format=<format>` — output format for `check` (`info`, `json`, `checkstyle`, etc.).
- `--no-parallel` — run checks sequentially instead of in parallel.
- `--apply` — global guard for `fix`; without it the command only prints what would be changed.

## Checks and formatters

| Check | Validates | Formatter (used by `fix --apply`) |
|---|---|---|
| `moodle-plugin-ci.phpcs` | PHP coding style | `phpcbf` |
| `moodle-plugin-ci.jslint` | JavaScript lint | `eslint --fix` + `grunt amd` rebuild |
| `moodle-plugin-ci.stylelint` | CSS/SCSS | `stylelint --fix` |
| `jsonlint` | JSON | `prettier` |
| `xmllint` | XML | `xmllint --format` (or PHP DOM fallback) |
| `yamllint` | YAML | `prettier` |
| `readme` | README.md | `prettier` |
| `moodle-plugin-ci.mustache` | Mustache templates | `djlint --reformat` |
| `moodle-plugin-ci.gherkinlint` | Behat/Gherkin | `reformat-gherkin` |
| `marketplaceimages` | `.moodleplugin/` poster + screenshots | — |
| `image` | Source images: format, MIME, size, dimensions, location, naming, EXIF metadata, compression | `pngquant`, `mozjpeg`, `svgo`, `gifsicle`, `cwebp` |

Formatters that are not installed in the environment are reported as skipped.

## Dependencies

All runtime dependencies are listed and installed automatically:

- **PHP** — managed by Composer (`composer.json`).
- **Node tools** — managed by npm (`package.json`). Composer's `post-autoload-dump` runs `npm i`.
- **Python tools** — managed by pip (`requirements.txt`). Composer's `post-autoload-dump` creates `.venv/` and installs them.

Composer configuration:

- `minimum-stability` is set to `dev` because some required packages (e.g. `symfony/serializer`, `schlessera/markdown-escape`, `automattic/ignorefile`, `tuchsoft/issue-reporter`) only exist as dev branches.
- `prefer-stable` is set to `true` so that every package that *can* be resolved to a stable release is pinned to a stable release. This prevents unrelated transitive dependencies from drifting onto dev branches and breaking the lock file.

## Docs

- `index.md` — this file.
- `todo.md` — roadmap, bugs, planned features.
