# Contributing to Post Chapters

Thank you for your interest in contributing to Post Chapters! This document explains how to set up the development environment, run the checks and tests, and submit changes.

## Requirements

| Tool | Version | Used for |
|------|---------|----------|
| WordPress | 6.3+ (tested up to 7.1.4) | Runtime |
| PHP | 7.4–8.4 | Runtime, Composer tools |
| Composer | 2.x | PHPCS, WPCS, PHPCompatibilityWP, PHPUnit (dev only) |
| Node.js | 22.13+ | `@wordpress/env` 12 (wp-env) |
| Docker | Recent | wp-env containers |
| WP-CLI + GNU gettext | WP-CLI 2.12+ | Translations (`wp i18n`, `msgmerge`, `msgfmt`), `bin/check-i18n.sh` |

The plugin has no JavaScript or CSS build step: `blocks/subpage/index.js` is plain ES5 using the `wp.*` globals, and the minified CSS/JS files are committed.

## Getting Started

1. Fork the repository on GitHub
2. Clone your fork into a folder named `post-chapters` (wp-env mounts it under that name):
   ```bash
   git clone https://github.com/<your-username>/post-chapters.git
   cd post-chapters
   ```
3. Install the development tools:
   ```bash
   composer install
   ```
4. Start WordPress (7.1.4, PHP 8.4, `WP_DEBUG` and `SCRIPT_DEBUG` off, see `.wp-env.json`):
   ```bash
   npx @wordpress/env@12 start
   ```
   The site runs at http://localhost:8888 (user `admin`, password `password`), with Post Chapters active. Local overrides (ports, WordPress or PHP version) go in `.wp-env.override.json`, which is git-ignored. Use `npx @wordpress/env@12`, not `npx wp-env`: the npm package `wp-env` is unrelated to WordPress. The same environment runs the tests: `composer test:wp-env` reinstalls its database, so anything created there by hand is erased by the next test run.

You can also use another local environment (Local, DDEV...): symlink or copy the folder into `wp-content/plugins/` and activate **Post Chapters**.

## Project Structure

```
post-chapters/
  post-chapters.php       # Plugin entry point (header, PCHAP_VERSION)
  class-pchap.php                 # PChap class: request setup, rendering, SEO
  uninstall.php                 # Data cleanup (multisite aware)
  blocks/subpage/               # Subpage block: block.json, index.js, index.asset.php, editor.css
  classes/
    class-pchap-admin.php         # Admin: menus, Settings API, editor buttons
    class-pchap-shortcodes.php    # [nextpage] shortcode handler
  inc/
    pchap-parser.php              # PChap_Parser: content splitting
    pchap-template.php            # Titles, navigation & TOC rendering
    pchap-options.php             # Option definitions, validation, getters
    pchap-update.php              # Upgrades, subpage index rebuild, WP-Cron
    pchap-blocks.php              # Block registration, JS translations
    pchap-cli.php                 # wp post-chapters rebuild|status
    pchap-functions.php           # Version getters, text domain
    admin/                      # Settings screens, TinyMCE plugin
  css/, admin/css/              # Frontend and admin styles (LTR / RTL, minified)
  languages/                    # .pot, .po, .mo, .l10n.php, .json
  tests/                        # PHPUnit integration suite
  bin/                          # build-zip.sh, check-i18n.sh, php-lint.php
```

See [ARCHITECTURE.md](ARCHITECTURE.md) for the request lifecycle, the parser, the data model and the hooks reference.

## Development Workflow

| Command | What it does |
|---------|--------------|
| `composer lint` | `php -l` on every PHP file with the current PHP binary (`bin/php-lint.php`) |
| `composer phpcs` | WordPress Coding Standards + PHPCompatibilityWP (`phpcs.xml.dist`) |
| `composer phpcbf` | Fix the violations that can be fixed automatically |
| `composer test:wp-env` | PHPUnit suite inside the running wp-env `cli` container (reinstalls its database) |
| `composer test` | PHPUnit with the local PHP, when the WordPress test library is available (`WP_TESTS_DIR`) |
| `bin/check-i18n.sh` | Translation files in sync with the code (see [Translations](#translations)) |
| `bin/check-seo.sh` | Checks the chapters against Yoast SEO, Rank Math, SEOPress, All in One SEO and The SEO Framework on the running wp-env site (installs them from wordpress.org; run it before a release, they change their output) |
| `bin/build-zip.sh` | Release archive `build/post-chapters.zip` (`--wporg`: `build/post-chapters-wporg.zip`, without `Update URI`) |

### Coding standards

`composer phpcs` must report **0 errors** (CI fails otherwise; warnings are reported but do not fail it). The ruleset checks PHP 7.4+ compatibility, WordPress 6.3+ APIs, the `post-chapters` text domain and the global prefixes `pchap_` (internal helpers included: no leading underscore, Plugin Check rejects it) and `multipage`.

- Follow the [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/)
- Strict comparisons (`===` / `!==`) and Yoda conditions
- Escape all output late: `esc_html()`, `esc_attr()`, `esc_url()`, `wp_kses()` / `wp_kses_post()`. Subpage titles go through `pchap_get_subpage_title_html()` or `pchap_get_subpage_title_text()`
- Sanitize all input: `sanitize_text_field()`, `sanitize_key()`, `intval()`, `wp_unslash()`; check nonces and capabilities
- Settings are declared in `pchap_get_option_definitions()` and saved through the Settings API, never with a custom save handler
- Use `$this` (not `&$this`) in callback arrays; avoid deprecated WordPress functions (e.g. `time()` instead of `current_time( 'timestamp' )`)
- PHP 7.4 syntax only, and every class property declared explicitly (no dynamic properties, PHP 8.2+)
- Never translate a string before `init`
- Keep public functions, methods, filters and their signatures backward compatible; deprecate with `_deprecated_function()` instead of removing
- The Subpage block `save()` output must stay byte-identical, or every existing post would show an invalid block; keep the metadata copy in `blocks/subpage/index.js` in sync with `block.json`
- When you edit a `.css` or `.js` file that has a `.min` sibling, regenerate the minified file (e.g. `npx clean-css-cli`, `npx terser`) and keep the RTL stylesheets in sync

### Tests

The suite uses PHPUnit 9.6 and the WordPress test library. It runs inside wp-env:

```bash
npx @wordpress/env@12 start
composer test:wp-env
# A subset:
npx @wordpress/env@12 run cli --env-cwd=wp-content/plugins/post-chapters vendor/bin/phpunit --filter Test_PChap_Split_Html
npx @wordpress/env@12 run cli --env-cwd=wp-content/plugins/post-chapters vendor/bin/phpunit --group parser
```

- The configuration is strict: PHP notices, warnings and deprecations, risky tests and unexpected output fail the run
- Test classes extend `PChap_UnitTestCase` (`tests/includes/class-pchap-unittestcase.php`), which resets the `PChap` singleton between simulated requests and provides helpers (`create_post()`, `go_to_subpage()`, `render_subpage()`, `set_options()`...)
- Content cases shared by several tests live in `PChap_Test_Corpus` (`tests/includes/class-pchap-test-corpus.php`); add a case there when you fix a parsing bug
- Splitting changes must keep every page balanced (tags and block delimiters) and must not leave empty elements: see `tests/test-split-html.php` and `tests/test-request-invariant.php`
- Many sites run a persistent object cache. CI runs the suite once more with Redis (Redis Object Cache drop-in): to do the same locally, start a Redis container on the wp-env network (`docker network ls` shows `wp-env-post-chapters-…_default`; `docker run -d --name pchap-redis --network <network> --network-alias redis redis:7-alpine`), put `{ "config": { "WP_REDIS_HOST": "redis" } }` in `.wp-env.override.json`, run `npx @wordpress/env@12 start --update`, then in the `cli` container `wp plugin install redis-cache` and copy `wp-content/plugins/redis-cache/includes/object-cache.php` to `wp-content/object-cache.php`
- CI runs the suite on WordPress 6.3 / PHP 7.4, WordPress 7.1.4 / PHP 7.4 and WordPress 7.1.4 / PHP 8.4. To reproduce a pair locally, set `WP_ENV_CORE` (e.g. `https://downloads.wordpress.org/release/wordpress-6.3.zip`) and `WP_ENV_PHP_VERSION` before `npx @wordpress/env@12 start`

Also check your change by hand in the browser: the block editor, the classic editor (Classic Editor plugin), a block theme (Twenty Twenty-Five) and a classic theme (Twenty Twenty-One).

### Continuous integration

`.github/workflows/ci.yml` runs on every push to `main`, on tags and on pull requests: PHP lint (7.4 to 8.4), PHPCS, the i18n check, PHPUnit (wp-env matrix above) and the release zip (uploaded as an artifact).

## Making Changes

1. Create a feature branch from `main`:
   ```bash
   git checkout -b fix/short-description
   ```
2. Make your changes, keeping commits focused and atomic
3. Add or update tests, then run `composer lint`, `composer phpcs` and `composer test:wp-env`
4. If you changed translatable strings, refresh the translation files (below) and run `bin/check-i18n.sh`
5. Update `CHANGELOG.md` under the upcoming version

## Translations

- Text domain: `post-chapters`. Every string with a placeholder needs a `/* translators: */` comment, and counts use `_n()`
- `languages/post-chapters.pot` is the template; `.po` files are the sources (not shipped); `.mo`, `.l10n.php` and `.json` (JavaScript, named after the md5 of `blocks/subpage/index.js`) are generated and committed
- The bundled files take precedence over the language packs installed in `wp-content/languages/plugins` (those of translate.wordpress.org belong to the original plugin), for the bundled locales only; see `pchap_prefer_bundled_translations()`

To refresh the files after changing strings, from the plugin root:

```bash
# 1. Template (reads PHP, JavaScript and block.json)
wp i18n make-pot . languages/post-chapters.pot --domain=post-chapters \
  --exclude=vendor,tests,bin,node_modules,build,.claude,.github --package-name="Post Chapters" \
  --headers='{"Report-Msgid-Bugs-To":"https://github.com/PaulArgoud/post-chapters/issues"}'

# 2. Merge the new strings into every catalog, drop obsolete entries
for po in languages/*.po; do
  msgmerge --quiet --no-wrap --backup=off --update "$po" languages/post-chapters.pot
  msgattrib --no-obsolete --no-wrap -o "$po" "$po"
done

# 3. Translate the new and fuzzy entries (Poedit or a text editor), remove the fuzzy flags

# 4. Compile
for po in languages/*.po; do msgfmt --check --statistics -o "${po%.po}.mo" "$po"; done
wp i18n make-json languages --no-purge
wp i18n make-php languages

# 5. Check
bin/check-i18n.sh
```

`bin/check-i18n.sh` fails when `make-pot` warns (e.g. a missing translators comment), when the `.pot` misses a string of the code, when a `.po` has untranslated, fuzzy or obsolete entries or fails `msgfmt --check`, or when a `.mo`, `.l10n.php` or `.json` file is missing or stale. Set `WP_CLI_BIN` (e.g. `WP_CLI_BIN="php wp-cli.phar"` or a wp-env wrapper) and `PHP_BIN` if `wp` or `php` are not on your `PATH`.

French translations follow the French typography rules (non-breaking space before `:`, `;`, `?`, `!` and inside « », curly apostrophe), and every locale follows the WordPress glossary of its language.

## Building a Release

```bash
bin/build-zip.sh           # GitHub release: build/post-chapters.zip
bin/build-zip.sh --wporg   # WordPress.org: build/post-chapters-wporg.zip
```

The archive `build/post-chapters.zip` holds one top-level `post-chapters/` folder with the files known to git (tracked or staged), minus everything listed in `.distignore` (tooling, tests, contributor docs, `.po` sources). Stage new files with `git add` before building, or they are left out. Bump `Version` and `PCHAP_VERSION` in `post-chapters.php` and `version` in `blocks/subpage/block.json`, and the database version in `PChap::setup_globals()` when an upgrade routine is added.

The plugin header declares `Update URI: https://github.com/PaulArgoud/post-chapters`, so WordPress never offers to replace this version with a WordPress.org plugin of the same slug. `--wporg` builds the same archive without that line (Plugin Check rejects it, and WordPress.org updates would not reach the sites): use it for anything sent to WordPress.org, and run Plugin Check on it first. The listing assets (screenshots matching the `== Screenshots ==` captions of `readme.txt`, banners and icons) live in `.wordpress-org/` and go to the SVN `/assets` folder.

## Submitting a Pull Request

1. Push your branch to your fork
2. Open a pull request against the `main` branch
3. Provide a clear description of the changes and the problem they solve
4. Reference any related issues (e.g. `Fixes #42`)
5. Make sure the CI checks pass

## Reporting Issues

- Use [GitHub Issues](https://github.com/PaulArgoud/post-chapters/issues)
- Include your WordPress version, PHP version, theme, editor (block or classic) and any relevant error messages
- For display problems, include the post content (Code editor view) or the shortcodes used
- Describe the steps to reproduce the problem

## License

By contributing, you agree that your contributions will be licensed under the [GNU General Public License v3.0](LICENSE).
