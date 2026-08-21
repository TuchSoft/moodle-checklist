## Parallel mode treats child stderr as fatal

- `Checker` merges child-process stderr in parallel mode. Any PHP warning/notice from a single check becomes a top-level fatal error.
- Single-file checks must not emit PHP warnings when their target file is missing; use `AbstractSingleFileCheck` Template Method.

## Native image optimizer binaries on ARM

- `pngquant-bin`, `mozjpeg`, `gifsicle`, `cwebp-bin` ship x86_64 prebuilt binaries.
- On ARM64 Linux they fall back to compiling and fail without native build tools (`build-essential`, `automake`, `libtool`, `nasm`, `libpng-dev`, `libjpeg-dev`, `pkg-config`).
- Consuming Docker images must install build tooling before `npm i`.

## Moodle 5.1+ `public/` docroot breaks root guessing

- `VersionParser` guessed `moodleroot` by stripping the plugin path from `fullpath`.
- For Moodle 5.1+ this yields `/var/www/html/public` (web docroot) instead of `/var/www/html` (project root).
- Fix: accept `--moodle-root` CLI option; when absent, fall back to looking for `admin/cli/upgrade.php` one directory up.

## PHPCS `moodle` standard not registered without composer installer

- `dealerdirect/phpcodesniffer-composer-installer` must be allowed in `composer.json` `allow-plugins`.
- If disabled, `vendor/squizlabs/php_codesniffer/CodeSniffer.conf` is never created and `--standard=moodle` fails.

## Missing single-file check targets

- `ReadmeCheck` and `GitIgnoreCheck` previously crashed when `README.md`/`.gitignore` were absent.
  - `ReadmeCheck` called `lintMarkdown()` after `parent::execute()` returned early.
  - `GitIgnoreCheck` overrode `execute()` without ever checking file existence.
- Fixed by enforcing the Template Method in `AbstractSingleFileCheck`.
