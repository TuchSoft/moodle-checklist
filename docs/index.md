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

Auto-formats the plugin files. **Dry-run by default**; add `--apply` to write changes. After applying, re-run `check` to verify any remaining issues. The final summary reports how many formatters ran, failed, and were skipped.

### Common options

- `--phase=<phase>` — choose a validation profile:
  - `none` (default): current behavior, all checks active.
  - `pre-build`: validate the source repository during development. Disables `forbidden-dir`/`forbidden-file` so dependency directories (e.g. `node_modules`, `vendor`) and lockfiles are allowed.
  - `post-build`: validate a built distribution artifact. Disables source-only checks such as `.moodleplugin/`, `.git/`, README/CHANGELOG/LICENSE/CONTRIBUTING, `.gitignore`, screenshots, and repository-history checks.
- `--include-check=<check>` / `--exclude-check=<check>` — include or exclude individual checks. These always take precedence over `--phase`.
- `--format=<format>` — output format for `check` (`info`, `json`, `checkstyle`, etc.).
- `--no-parallel` — run checks sequentially instead of in parallel. In parallel mode, a single failing subprocess no longer aborts the whole run; the failing check is reported as a runtime error and the remaining checks continue.
- `--moodle-root=<path>` — absolute path to the Moodle project root (the directory containing `config.php` and `admin/cli/upgrade.php`, not the web docroot). When omitted, the root is guessed from the plugin path, with a fallback for Moodle 5.1+ `public/` docroot layouts.
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

Formatters that are not installed in the environment are reported as skipped. File scanning and fixers respect `.gitignore` and a hardcoded safety list (`node_modules`, `.git`, `vendor`, `.venv`, `.idea`, `.moodleplugin`, `.complex_plans`, `.agents`, `.phpunit.cache`) so dependency directories and temporary files such as `check_upgrade_savepoints.php` are ignored.

## Dependencies

All runtime dependencies are listed and installed automatically:

- **PHP** — managed by Composer (`composer.json`).
- **Node tools** — managed by npm (`package.json`). Composer's `post-autoload-dump` runs `npm i`.
- **Python tools** — managed by pip (`requirements.txt`). Composer's `post-autoload-dump` creates `.venv/` and installs them.

`composer.json` declares `tuchsoft/issue-reporter` as a VCS dependency (`https://github.com/TuchSoft/issue-reporter.git`). A standard `composer install` clones it automatically.

### Native build tools for image optimization

The image formatter uses npm packages that ship prebuilt binaries for common platforms (`pngquant-bin`, `mozjpeg`, `gifsicle`, `cwebp-bin`). On non-x86_64 platforms (e.g. ARM64 Linux) these packages may need to compile from source during `npm i`. Make sure the environment provides native build tooling such as `build-essential`, `automake`, `libtool`, `nasm`, `libpng-dev`, `libjpeg-dev`, and `pkg-config`.

Composer configuration:

- `minimum-stability` is set to `dev` because some required packages (e.g. `symfony/serializer`, `schlessera/markdown-escape`, `automattic/ignorefile`, `tuchsoft/issue-reporter`) only exist as dev branches.
- `prefer-stable` is set to `true` so that every package that *can* be resolved to a stable release is pinned to a stable release. This prevents unrelated transitive dependencies from drifting onto dev branches and breaking the lock file.

## Docs

- `index.md` — this file.
- `todo.md` — roadmap, bugs, planned features.
