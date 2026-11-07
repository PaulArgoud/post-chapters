# Changelog

All notable changes to the Multipage plugin are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.7.0] - 2026-11-07

Major rewrite of the Subpage block, the content parser and the frontend rendering. Existing content needs no change: blocks saved by earlier versions stay valid, and the subpage index of every post is rebuilt in the background after the update.

### Compatibility

- Tested up to **WordPress 7.1.4** (iframed block editor, block themes such as Twenty Twenty-Five, classic themes such as Twenty Twenty-One) and **PHP 7.4 to 8.4**
- **Minimum WordPress version raised from 5.0 to 6.3** (`Requires at least: 6.3`), required by the `block.json` registration and block `apiVersion` 3. PHP 7.4 is still the minimum
- **Subpage block rewritten** for the current block editor: registered from `blocks/subpage/block.json` (`apiVersion` 3, so it works in the iframed editor), plain ES5 script using the `wp.*` globals with no build step, translatable placeholder. Its `save()` output is **byte-identical** to previous versions, so every existing post stays valid (no "This block contains unexpected or invalid content" warning, no block recovery)
- The block is now registered on every request (`init`), whatever the editor in use, and still registers on third-party editor screens that do not bootstrap the server-side block definitions
- Classic Editor plugin detection (`mpp_is_block_editor_active()`) mirrors Classic Editor 1.7 settings, including the multisite network defaults and the "allow users to switch editors" option
- SEO plugins: dedicated integrations for **Yoast SEO**, **Rank Math** and **SEOPress** replace the old Yoast string replacement (which loaded the `wordpress-seo` text domain to replace "Page X of Y"). On subpages 2 and up, the subpage title fills the `%%page%%` (Yoast, SEOPress) or `%page%` (Rank Math) title variable. Each integration is only registered when that plugin is active

### Fixed

- **Broken page layout (user-reported on a production site)**: a `[nextpage]` shortcode placed inside `<p>`, `<h2>`, `<h3 style="…">`, `<div>` or in the middle of a paragraph left unclosed elements, so the table of contents and the navigation ended up nested inside a heading. Every subpage is now a balanced fragment: the blocks and elements open at the cut are closed at the end of the page and reopened on the next one
- Elements left empty by the cut (e.g. `<h3 style="…">[nextpage]</h3>`, also with only `&nbsp;` or `<br>` next to the shortcode, or a paragraph/heading block holding only the shortcode) are removed instead of rendering as empty headings or paragraphs; whitespace next to the cut is trimmed (except in `<pre>`)
- **Parser rewrite**: titles and pages could get out of step (wrong title on a page, missing or extra subpages). Subpages are now derived from a single parse of the live post content, in document order:
  - untitled `[nextpage]` shortcodes and attribute-less Subpage blocks (saved without a title) now create their own subpage (fallback title "Page N") instead of being ignored or titled "false"
  - posts mixing Subpage blocks, `[nextpage]` shortcodes and core page breaks (`<!--nextpage-->` or the Page Break block) are split in document order; previously the shortcodes were ignored as soon as one block was found, and core breaks shifted every title
  - intro detection: an intro made only of an image, an embed or a dynamic block is recognized; whitespace, `&nbsp;` and empty paragraphs before the first subpage no longer create an empty "Intro" page, and leading content that is not an intro (scripts, styles, separators) is kept on the first page instead of being lost
  - the self-closing block form and Subpage blocks nested in groups or columns are supported
  - `[nextpage]` inside an HTML comment, a tag attribute, a `<script>`/`<style>`/`<textarea>` element or a block delimiter is no longer treated as a page break, and the escaped `[[nextpage]]` is displayed literally
  - duplicate titles always get unique slugs (the 100-duplicate limit is gone)
- The frontend no longer depends on the `_mpp_data` post meta: posts imported, or edited while the plugin was inactive, no longer show an outdated table of contents
- **Table of contents injected where it does not belong**: the title, TOC and navigation are now only added to the multipage post rendered by the main query — no longer to related posts, Query Loop blocks, widgets, excerpts (`get_the_excerpt()`, `the_excerpt()`, direct `wp_trim_excerpt()` calls), descriptions built in `wp_head`, or password-protected posts before the password is entered
- The TOC is no longer added twice when a theme or page builder applies `the_content` again to the rendered output, or when a shortcode (box, tabs, accordion…) filters its inner content with `the_content`
- **1-based page handling**: `/post/` and `/post/1/` are the same first page everywhere. "Display comments on: first page" now shows the comments on `/post/1/`, and the current TOC item, rel links, navigation and document title use the same page number
- **Block themes**: the "first page" / "last page" comments setting now also hides the comment blocks (`core/comments` and related blocks), not only the classic `comments_template()`; the standard page links appended by the Post Content block are hidden when "Disable the standard pagination" is checked, and still shown when it is not
- "Only on the first page" (table of contents) is now honored when the TOC is displayed below the content
- "Hide intro title" now also applies on `/post/1/`, and only when the first page is the automatic intro (a first subpage with its own title keeps it)
- **Title escaping**: one policy for every output. Block titles (HTML-encoded by the editor) keep their inline formatting (`strong`, `em`, `code`, `mark`, `sub`, `sup`…) in the heading, the TOC and the navigation instead of showing literal tags, and any other markup is stripped. Classic shortcode titles are treated as text, so `C++ <T>` or `a < b & c` display literally instead of being truncated or emptied. The document title and the SEO plugins receive plain text, without tags or entities
- **SEO**: with Yoast SEO, subpages 2 and up declared the first page as their canonical URL (which tells search engines to drop them); they now use their own URL, and a canonical set in Yoast is kept. Yoast SEO and Rank Math no longer print their own `rel=prev` / `rel=next` links on multipage posts (computed from the raw content, they could be wrong or duplicate the plugin's)
- **Translations were never loaded**: `load_plugin_textdomain()` was called without the `languages/` path, so the bundled `.mo` files were ignored and the plugin always displayed English unless a language pack was installed
- TOC, navigation and rel link URLs always point to the multipage post (they were built from the global post), and use `?page=N` whenever permalinks are plain (also when the `permalink_structure` option is missing)
- The navigation no longer prints the `<!-- .nav-links -->` / `<!-- .mpp-post-navigation -->` HTML comments, and the internal empty-page placeholder is never printed
- **CSS**: the TOC and the navigation use `rem` sizes and reset the typography (font size, weight and style, line height, text transform, letter spacing), so they no longer inherit the styles of a surrounding heading
- **RTL**: RTL sites requested `css/multipage-rtl[.min].css` and `admin/css/multipage-admin-rtl[.min].css`, which did not exist (404); both stylesheets are now shipped. The "align left" / "align right" TOC positions keep their physical side
- **TinyMCE and Quicktags buttons**: the typed title is now escaped (`&`, `"`, `<`, `>`, `[`, `]`), so quotes no longer break the shortcode and markup cannot be injected; an empty or cancelled title inserts nothing (it used to throw a `ReferenceError` or re-insert the previous shortcode)
- **Classic block**: the `[nextpage]` placeholder was a blank image in the Classic block of the block editor; the plugin TinyMCE stylesheet is now loaded there too
- Backslashes in subpage titles are preserved when the `_mpp_data` index is saved
- The subpage index rebuild ("Build Multipage postmetas") loaded every matching post in a single request, skipped drafts and untitled shortcodes, and never removed stale entries; it now runs in batches, indexes every post that `save_post` indexes and deletes stale entries
- The 1.3 → 1.4 migration no longer runs a full synchronous rebuild on `admin_init`, which could exceed `max_execution_time` on large sites and retry on every admin request
- The updater treated 1.4–1.6 sites as fresh installs (so no upgrade routine could run); it now also reads the stored `_mpp_db_version`

### Changed

- **Behaviour change**: `/post/1/` is now redirected (301) to the post permalink by WordPress core instead of being served as a duplicate of the first page
- **Behaviour change**: an out-of-range subpage (e.g. `/post/99/`) is redirected to the permalink, as WordPress does for posts split with core page breaks
- **Behaviour change**: in contexts where the content is not split (feeds, REST API, excerpts, other `do_shortcode()` callers), `[nextpage]` renders nothing; it used to output a `<!--nextpage-->` comment
- **Behaviour change**: with the bundled translations now loaded, the intro title "Intro" is displayed as "Introduction" in French ("Einleitung" in German, "Introducción" in Spanish, "Introduzione" in Italian)
- **Behaviour change**: settings fields added to the Multipage settings screens by third-party code with `add_settings_field()` are no longer saved automatically: register them with `register_setting( 'mpp-settings', … )` (or `'mpp-advanced-settings'` for the Advanced screen)
- **Settings API**: both settings screens now save through `options.php`. Every value is validated against its allowed values (an invalid value keeps the previous one and shows an error naming the field), unchecked checkboxes are stored as `0`, and custom priorities set in the database are kept (shown as "Custom (N)"). Fields have correct labels (`label_for`, fieldset legends), and priority labels are translatable
- "Build Multipage postmetas" is replaced by a **Rebuild subpage index** button on the Advanced screen: posts are processed in batches for about 15 seconds, the remaining posts continue in the background, and the screen shows the last rebuild date and the background progress
- After updating to 1.7.0, and on every activation, the subpage index is rebuilt in the background through WP-Cron (batched, resumable, protected by a lock)
- The `X-Pingback` header is no longer sent by the plugin (WordPress core sends it since 6.1)
- `uninstall.php` now cleans every site of a multisite network, and also removes the new internal options and the scheduled background rebuild
- Bundled translations: complete and reviewed `fr_FR`, `de_DE`, `es_ES` and `it_IT` catalogs (following the WordPress glossary of each locale), JavaScript translations for the block (`.json`) and `.l10n.php` files (WordPress 6.5+ performant translations). Language packs from translate.wordpress.org always take precedence over the bundled files
- Translatable strings are escaped (`esc_html__()`, `esc_attr__()`), every string with a placeholder has a translators comment, and counts use `_n()`
- The current TOC item has `aria-current="page"`
- The Subpage block title field: Enter at the end of the title adds a paragraph after the block, and long titles wrap

### Added

- **WP-CLI**: `wp multipage rebuild [--batch-size=<n>] [--dry-run]` rebuilds the subpage index (and completes any pending background rebuild); `wp multipage status [--format=<format>]` reports indexed, missing and stale entries, the background rebuild state and the database version
- **Rebuild subpage index** button (Settings > Multipage > Advanced), see Changed
- RTL stylesheets `css/multipage-rtl[.min].css` (frontend) and `admin/css/multipage-admin-rtl[.min].css` (admin)
- Translated fallback title "Page N" for untitled subpages

### Developer

- **Single source of truth**: `Multipage_Parser::parse( $content )` returns `is_multipage`, `has_intro` and the ordered `pages` (`slug`, `title`, `content`, `kind`: `intro`, `block`, `shortcode` or `core`). `Multipage_Parser::multipage_return_array()` is now derived from it (same signature and return shape). New: `Multipage_Parser::build_paginated_content( $pages, $use_block = null )`, `Multipage_Parser::is_meaningful_content()`, constants `Multipage_Parser::BLOCK_PAGE_BREAK` and `Multipage_Parser::EMPTY_PAGE_PLACEHOLDER`
- **Request setup**: `Multipage::setup()` (idempotent) parses the main query's post from `pre_handle_404`, `redirect_canonical` and `wp`, so the plugin keeps working when another plugin short-circuits one of them. `mpp_post()` rebuilds `post_content` so that WordPress' own splitting returns exactly the parsed pages, and filters `content_pagination`. The parse result is cached in the object cache group `multipage` (key: plugin version + content hash). New public members: `Multipage::$post_id`, `$current_page` (1-based), `$pages`, `is_multipage_view()`, `get_current_page()`, `get_title_format()`, `comments_allowed_on_current_page()`; `$page` stays the raw `page` query var
- New template helpers: `mpp_get_subpage_title_html( $title, $format = 'html' )`, `mpp_get_subpage_title_text( $title, $format = 'html' )`, `mpp_get_subpage_title_allowed_html()`; `_mpp_link_page_url()` takes an optional third `$post` argument
- New options API: `mpp_get_option_definitions()`, `mpp_get_option_definition()`, `mpp_get_option_choice_labels()`, `mpp_validate_option_value()`, `mpp_sanitize_option()`. All getters keep their names, signatures and filters, and now return validated values. The options cache is cleared automatically when a Multipage option is added, updated or deleted
- New rebuild API (`inc/mpp-update.php`): `mpp_run_postmeta_rebuild()`, `mpp_process_postmeta_batch()`, `mpp_schedule_postmeta_rebuild()`, `mpp_get_postmeta_index_stats()`, `mpp_is_rebuild_locked()`. WP-Cron hook `mpp_rebuild_postmeta_batch`; internal options `_mpp-rebuild-state`, `_mpp-rebuild-last` and `_mpp-rebuild-lock` (not autoloaded); the database version is now `1700`
- New filters: `mpp_untitled_subpage_title`, `mpp_comment_block_names`, `mpp_subpage_title_allowed_html`, `mpp_parser_is_meaningful_content`, `mpp_get_option_definitions`, `mpp_get_option_choice_labels`, `mpp_rebuild_postmeta_batch_size`, `mpp_rebuild_cron_time_limit`, `mpp_rebuild_admin_time_limit`
- New files: `blocks/subpage/` (`block.json`, `index.js`, `index.asset.php`, `editor.css`), `inc/mpp-blocks.php` (`mpp_register_blocks()`, bundled JS translations fallback), `inc/mpp-cli.php` (`MPP_CLI_Command`)
- `_mpp_data` is kept as an index for third-party code (written on `save_post` and by the rebuild); untitled subpages are stored with title `''` and slug `page-N`. `MPP_PATTERN` and `MPP_GUTENBERG_PATTERN` are kept for backward compatibility only
- Removed: `admin/js/mpp-editor-blocks.js`, `admin/css/mpp-editor-blocks[.min].css` (replaced by `blocks/subpage/`) and the global `prompt_subtitle()` JavaScript function of the Quicktags button
- **Tooling**: `composer.json` with dev-only dependencies (WPCS 3, PHPCompatibilityWP, PHPUnit 9.6, Yoast PHPUnit Polyfills) and the `lint`, `phpcs`, `phpcbf`, `test` and `test:wp-env` scripts; `phpcs.xml.dist` (WordPress + PHPCompatibilityWP, PHP 7.4+, WordPress 6.3+), with 0 errors on the codebase
- **Tests**: PHPUnit integration suite (about 380 tests) running in `wp-env` (`.wp-env.json`: WordPress 7.1.4, PHP 8.4): parser corpus, HTML balance of every split, full requests of every subpage, rendering, titles, SEO, comments, options, rebuild, upgrade, blocks, uninstall
- **CI**: GitHub Actions workflow (PHP 7.4–8.4 lint, PHPCS, i18n check, PHPUnit on WordPress 6.3 / PHP 7.4, 7.1.4 / PHP 7.4 and 7.1.4 / PHP 8.4, release zip)
- **Release and i18n scripts**: `bin/build-zip.sh` (zip of the files known to git, filtered by `.distignore`), `bin/php-lint.php`, `bin/check-i18n.sh` (POT, `.po`, `.mo`, `.json` and `.l10n.php` files in sync with the code); `.distignore` and `.gitattributes` keep development files out of the release

### Deprecated

- `MPP_Admin::mpp_enqueue_block_editor_assets()` — no longer hooked, the block is registered by `mpp_register_blocks()`
- `mpp_admin_settings_save()` and `mpp_admin_advanced_settings_save()` — no-ops, the settings are saved by the Settings API
- `mpp_admin_advanced_callback_build_mpp_postmeta_data()` — replaced by `mpp_admin_rebuild_postmeta_section()`
- `Multipage::mpp_the_title()` — no longer hooked, returns the title unchanged (see the SEO integrations)
- `Multipage::multipage_return_array()` (deprecated since 1.5.14) now triggers a `_deprecated_function()` notice; use `Multipage_Parser::multipage_return_array()`

## [1.6.0] - Unreleased

- Version number bump only (plugin header, `MPP_VERSION` and README badge), no functional change. This version was never tagged on GitHub.

## [1.5.15] - 2026-02-19

### Fixed

- **XSS**: subpage title now escaped with `esc_html()` in `mpp_the_content()` output
- **XSS**: translated strings in quicktag JS now escaped with `esc_js()` instead of raw `_e()`
- **PHP 8.2**: re-declared `$admin` property on `Multipage` class to prevent dynamic property deprecation warning
- `mpp_pre_handle_404()` returned `null` instead of `true`, failing to short-circuit the 404 handler
- `versions()` called `get_option( null, 'multipage' )` with swapped arguments — corrected to `get_option( 'multipage' )`
- `$comments_link` potentially undefined before use in `mpp_the_content()` — now initialized
- Admin CSS enqueued without version string, preventing cache busting — now uses `MPP_VERSION`
- `save_post` hook ran on revisions and autosaves — now filtered with `wp_is_post_revision()` / `wp_is_post_autosave()`
- `save_post()` could crash if `get_post()` returned `null` — added guard
- `mpp_the_title()` loaded the `wordpress-seo` text domain even when Yoast SEO was not active — now guarded with `defined( 'WPSEO_VERSION' )`
- Double slash in `hide_comments()` path (`MPP_PLUGIN_DIR . '/index.php'`)
- Navigation CSS class not sanitized — now uses `sanitize_html_class()`
- Missing text domain on `__( 'Next page' )`, `__( 'Previous page' )`, and `__( 'Comments' )` in `mpp-template.php`
- `_mpp_link_page_url()` could crash if `get_post()` returned `null` — added guard
- Duplicate `$handle` assignment in `MPP_Admin::enqueue_scripts()`
- Plugin URI used `http://` instead of `https://`
- `MPP_VERSION` constant was `'1.5.14'` while plugin header declared `1.5.15`
- Comments link in TOC was missing `<li>` wrapper, producing invalid HTML
- `multipage_return_array()` returned an empty array instead of `false` when no valid shortcodes were found
- "Build Multipage postmetas" only found shortcode posts — now also detects Gutenberg block posts
- Plugin action links detection used fragile `/trunk` path hack — now uses `plugin_basename()`
- **Security**: missing `exit` after `wp_safe_redirect()` in both settings save functions — PHP continued executing after redirect
- `esc_html()` used instead of `esc_attr()` on radio input `value` attributes in settings page
- Operator precedence ambiguity in `versions()` — `(int)` cast applied to condition instead of ternary result
- Incorrect `@since 1.6` docblock tags corrected to `@since 1.5` (version 1.6 does not exist)
- Copy-paste docblock errors: `mpp_hide_intro_title()`, `mpp_toc_only_on_the_first_page()`, and `mpp_hide_toc_header()` incorrectly described as "standard WordPress pagination"
- **SEO**: `rel=next` link on the first page pointed to itself instead of page 2 — normalized page index in `mpp_rel_links()`
- Comments TOC link for `first-page` / `last-page` was missing `#comments` URL fragment — `'comments'` was passed as `$rel` instead of `$p` parameter to `_mpp_link_page()`
- Swapped file headers: `admin-settings.php` said "Admin Advanced" and `admin-advanced-settings.php` said "Admin Settings"
- `mpp_get_comments_on_page()` docblock declared `@return int` but the function returns a `string`
- `mpp_the_content` filter docblock had wrong parameter names (`$toc_labels` instead of `$toc`) and types (`array` instead of `string`)
- `mpp_link_pages_args` filter docblock had `@since 3.0.0` copied from WordPress core — corrected to `@since 1.4`
- Typo « fitler » corrected to « filter » in `setup_actions()` comment
- `mpp_toc()` default position was `'top_right'` (underscore) instead of `'top-right'` (hyphen), producing an invalid CSS class
- `mpp_toc_pages_row` filter docblock documented 2 parameters but 3 are passed (`$row`, `$title`, `$i`)
- Typo « positon » corrected to « position » in `mpp_get_toc_position()` filter docblock
- `@param bool|string` corrected to `@param int` for priority option defaults in `mpp-options.php`
- Missing `$multipage` parameter documented in `mpp_link_pages()` and `mpp_toc()` docblocks
- Redundant `$post &&` check removed in `mpp_pre_handle_404()` — already validated above
- **JS TypeError**: cancelling the quicktag subpage prompt caused `null.length` crash — now checks for `null` explicitly
- **CSS handle**: `enqueue_styles()` used full filesystem path (`get_stylesheet_directory()`) as handle identifier — replaced with `get_stylesheet()` / `get_template()` slug
- `mpp_get_admin_tabs()` docblock declared `@return string` but the function returns an `array`
- `mpp_get_db_version()` and `mpp_get_db_version_raw()` docblocks declared `@return string` but the functions return an `int`
- Self-referencing `@uses enqueue_styles()` tag removed from `enqueue_styles()` docblock
- `_mpp_link_page_url()` docblock `@param string $p Paragraph id` corrected to « Fragment identifier »
- `in_array()` missing strict mode (`true`) in `mpp_modify_admin_menu_highlight()`
- `mpp_toc()` docblock for `$pagelink` incorrectly described as "Link text for the previous page link" — corrected to "Page number template; `%` is replaced by the page number"
- `mpp_link_pages()` docblock declared wrong defaults: `$before` = `<p> Pages:` / `$after` = `</p>` / `$echo` = `1|true` — corrected to `<div>` / `</div>` / `0|false`
- `mpp_toc()` docblock declared `$echo` default as `1|true` — corrected to `0|false`
- `mpp_toc()` docblock for `$comments` declared `@type int|bool` but actually receives an opening `<a>` tag or `0` — corrected to `string|int`
- Unused `global $numpages` declaration removed from `mpp_toc()`
- Dead JavaScript variable `shortcode` removed from quicktag `prompt_subtitle()` function
- **Security**: missing `current_user_can( 'manage_options' )` check in `mpp_admin_settings_save()` and `mpp_admin_advanced_settings_save()` — added for defense-in-depth
- `mpp_the_content` filter docblock started with `/*` instead of `/**`, invisible to PHPDoc
- `hide_standard_pagination()` docblock missing `@param` and `@return` tags
- `mpp_pre_handle_404()` docblock missing `@return` tag
- `mpp_add_post_multipage_meta()` docblock missing `@param` for `$update_option`

### Added

- Docblocks for `frontend_init()` and `mpp_post()` methods in `Multipage` class
- Docblock for `_mpp_link_page()` helper function in `mpp-template.php`
- `uninstall.php` to clean up all plugin options and `_mpp_data` post meta on deletion
- `Requires at least: 5.0` and `Requires PHP: 7.4` headers in plugin file
- History section in `README.md` documenting the plugin's origins and fork lineage
- `aria-label` attributes on TOC and navigation `<nav>` elements for accessibility

### Changed

- `_e()` replaced with `esc_html_e()` in admin settings templates (17 occurrences) for translation security
- `_mpp_link_page_url()` called `get_permalink()` up to 3 times — now stored in a local variable
- `$this->version` now reads from `MPP_VERSION` constant instead of a hardcoded string
- All `require()` calls replaced with `require_once` (without parentheses) in `class-mpp.php` and `class-mpp-admin.php`
- `sanitize_html_class()` applied to TOC container position class
- `mpp_is_block_editor_active()` simplified — removed unnecessary WP < 5.0 and Gutenberg plugin checks
- TinyMCE disable setting description updated (no longer references WordPress 3.9)
- `mpp_get_continue_or_prev_next()` called once and stored instead of twice
- `mpp_get_toc_position()` called once and stored instead of three times in `mpp_the_content()`
- `mpp_get_comments_on_page()` called once and stored instead of twice in `mpp_post()`
- `constant('MPP_PLUGIN_DIR')` / `constant('MPP_PLUGIN_URL')` replaced with direct constant access in `setup_globals()`
- `strip_tags()` string allowlist updated to array form (recommended since PHP 7.4) in `Multipage_Parser`
- Redundant `trailingslashit()` removed from `setup_globals()` — `plugin_dir_path()` / `plugin_dir_url()` already add a trailing slash
- Redundant `is_null( $post ) || empty( $post )` simplified to `empty( $post )` in `mpp_post()`
- `json_encode()` replaced with `wp_json_encode()` in TinyMCE `languages.php`
- Minor formatting: added missing space in `$this->page - 1` arithmetic expression
- Removed trailing semicolons (`};`) after ABSPATH guard closing braces in `class-mpp.php` and `mpp-update.php`
- Gutenberg block description replaced (was "lorem ipsum" placeholder)
- Gutenberg block script dependency changed from deprecated `wp-editor` to `wp-block-editor`
- Frontend CSS header updated to current author and HTTPS Plugin URI

### Removed

- Obsolete WP < 4.4 title fallback in `mpp_the_title()`
- Orphaned `<input type="checkbox">` and commented-out toggle HTML in TOC template
- Dead `mpp_admin_settings_callback_excerpt_on_all_pages()` (called non-existent `mpp_excerpt_all_pages()`)
- Dead `mpp_admin_settings_callback_prettylinks()` (referenced old `envire.it` URL)
- Unused `$notices` property from `MPP_Admin` class
- Unused `$options` property from `Multipage` class
- Dead widget files: `class-mpp-table-of-contents-widget.php` and `mpp-widgets.php` (never loaded, non-functional)
- Dead `mpp_uninstall()` function in `mpp-update.php` (replaced by `uninstall.php`)
- Orphaned `.toctogglespan` CSS rule (toggle was removed from PHP)
- Dead `mpp_get_default_options()` function (never called, option keys used underscores instead of hyphens)
- Unused `MPP__MINIMUM_WP_VERSION` constant (minimum WP version already declared in plugin header)
- Redundant `MPP__PLUGIN_DIR` constant (same value as `MPP_PLUGIN_DIR`, only used once — inlined)

## [1.5.14] - 2026-02-19

### Fixed

- Uninitialized `$custom_intro` variable causing PHP 8.4 warnings (`class-mpp.php`)
- Array bounds check missing for `$_mpp_page_keys` access in `mpp_pre_handle_404()`
- Ambiguous operator precedence in comments display condition (added explicit parentheses)
- Integer settings sanitized with `sanitize_text_field()` instead of `intval()` in advanced settings
- Missing `esc_attr()` on `<option>` values in admin settings forms (4 occurrences)
- Options cache never invalidated after saving — `mpp_clear_options_cache()` now resets the static cache
- Deprecated `current_time('timestamp')` replaced with `time()` in `mpp-update.php` and `admin-advanced-settings.php`
- Loose `==` / `!=` comparisons replaced with `===` / `!==` across all PHP files
- `array_search()` without `false` check in TinyMCE button insertion (`class-mpp-admin.php`)
- Operator precedence ambiguity in TOC active page condition (`mpp-template.php`)
- Preview link parameters now properly sanitized with `intval()` / `sanitize_text_field()` (`mpp-template.php`)
- Incorrect `@since 1.9.0` docblock tags corrected to `1.4`

### Added

- `LICENSE` file (GPL v3 full text)
- `CONTRIBUTING.md` with coding standards, project structure, and contribution guidelines
- `ARCHITECTURE.md` describing the plugin file structure and data flow

### Changed

- Replaced `&$this` with `$this` in all callback arrays (unnecessary since PHP 5.4)
- Updated Codex link to `developer.wordpress.org` in advanced settings
- Extracted content parser into `Multipage_Parser` class (`inc/mpp-parser.php`) from the main `Multipage` class

### Removed

- Dead debug code in table of contents widget
- Unused `admin-premium.php` page placeholder
- Dead `is_gutenberg_page()` method in `class-mpp.php` (replaced by `mpp_is_block_editor_active()`)

## [1.5.13] - 2026-02-19

### Fixed

- Quotes in subpage titles (e.g. `"F48"`) breaking page parsing — titles are now extracted from Gutenberg block JSON attributes first, with shortcode fallback for the classic editor; the block save function also escapes `"` as `&quot;` in shortcode output
- PHP 8.2+ deprecated dynamic property warnings — all class properties are now explicitly declared
- Catastrophic backtracking in regex pattern `MPP_GUTENBERG_PATTERN` (`(.|\s)*?` replaced with `[\s\S]*?`)
- Page 2+ not accessible due to WordPress `redirect_canonical` running before the plugin injects `<!--nextpage-->` markers
- Improperly quoted `rel` attribute in navigation links (`rel=next` → `rel="next"`)
- "Advaced" typo in admin submenu registration

### Added

- SEO `rel=prev` / `rel=next` links in `<head>` for multipage posts
- `sanitize_text_field` callbacks on all `register_setting()` calls
- `sanitize_text_field()` / `wp_unslash()` on all `$_POST` values in settings save functions
- `wp_kses_post()` escaping for subpage titles in TOC and navigation output
- In-memory options caching (`mpp_get_option()`) to reduce repeated `get_option()` database queries
- Strict mode on all `in_array()` calls in update routines
- `README.md` with badges, requirements, usage documentation, and filters reference
- `CHANGELOG.md` following [Keep a Changelog](https://keepachangelog.com/) format

### Changed

- Replaced `$_REQUEST` with explicit `$_GET` / `$_POST` checks in `mpp_is_deactivation()`
- Updated minimum WordPress version from 3.9 to 5.0
- Tested with PHP 8.4
- Regenerated `.pot` translation template (73 strings, was outdated since v1.3)
- Default branch renamed from `master` to `main`

### Removed

- Dead code: commented-out widget/premium requires, unused premium menu items, tabs, and settings
- Italian comments and obsolete TODO annotations
- Closing `?>` tag in `admin-functions.php`
- `readme.txt` (replaced by `README.md`)
- Obsolete "To Do" sections from old readme

## [1.5.12] - 2021-06-16

### Changed

- Compatibility check

## [1.5.11] - 2021-02-16

### Fixed

- Gutenberg: setting a custom subtitle on the first page could produce an empty first page due to bad behavior when Classic Editor was enabled

## [1.5.10] - 2021-02-16

### Added

- Gutenberg subpage block with updated syntax

### Fixed

- Gutenberg: setting a custom subtitle on the first page could produce an empty first page in some conditions

## [1.5.9] - 2021-02-14

### Fixed

- Gutenberg: setting a custom subtitle on the first page could produce an empty first page in some conditions

## [1.5.8] - 2020-08-22

### Changed

- Minor change to the "Continue" option layout

## [1.5.7] - 2020-06-21

### Fixed

- Non-working case with the Multipage legacy shortcode introduced in 1.5.6

## [1.5.6] - 2020-07-30

### Changed

- Compatibility with WordPress 5.5
- Minor CSS enhancements

## [1.5.5] - 2020-06-21

### Fixed

- Function call error happening when updating Multipage

## [1.5.4] - 2020-04-25

### Added

- `mpp_the_content` filter to customize the multipage content output

## [1.5.3] - 2020-04-22

### Fixed

- Added missing JS file for the block editor

## [1.5.2] - 2020-03-09

### Changed

- Fully tested on WordPress 5.4
- Removed an `<h1>` tag inside the post navigation links

## [1.5.1] - 2019-12-13

### Fixed

- Removed the "hide" link on the table of contents
- Added missing files for the block editor

## [1.5.0] - 2019-12-12

### Added

- Block editor support: new "Subpage" Gutenberg block

### Changed

- Fully tested on WordPress 5.3 and PHP 7.4
- Minor enhancements

## [1.4.4] - 2019-05-15

### Changed

- Fully tested on WordPress 4.1

## [1.4.3] - 2019-01-17

### Fixed

- Multipage on posts with `future` and `private` status

## [1.4.2] - 2019-01-09

### Fixed

- Post preview on first-time saved drafts

### Changed

- Renamed "Multipage Plugin" to "Multipage"

## [1.4.1] - 2018-12-09

### Fixed

- CSS fix for TOC top and bottom on mobile devices

## [1.4.0] - 2018-11-18

> **Note:** Major release — please make sure to have a full website backup before updating.

### Added

- New settings pages with new options
- Two navigation types: "Continue" and "Next/Previous"

### Changed

- Completely rewritten codebase
- Processing speed improvements
- Fully tested on WordPress 4.9.8
- Default TOC position changed (upgrades keep old default)

### Fixed

- Document title now reports the subpage title on WordPress > 4.4

## [1.3.6] - 2017-01-02

### Changed

- Fully tested on WordPress 4.7

## [1.3.5] - 2016-09-17

### Changed

- Fully tested on WordPress 4.6

## [1.3.4] - 2016-04-10

### Added

- New setting: unhide the default WordPress pagination

## [1.3.3] - 2016-04-05

### Added

- Advanced setting: disable TinyMCE button (for WordPress < 3.9 compatibility)
- Advanced settings for title and content rewrite priority
- Settings link on the plugins page

## [1.3.2] - 2015-12-08

### Added

- New languages

## [1.3.1] - 2015-11-19

### Changed

- Fully tested on WordPress 4.4
- Updated minimum requirement to WordPress 3.9

## [1.3.0] - 2015-05-17

### Added

- "Subpage" button in the WordPress visual & HTML editors

### Changed

- Text domain updated to match plugin slug `sgr-nextpage-titles`

## [1.2.4] - 2015-05-04

### Changed

- Fully tested on WordPress 4.2

### Fixed

- Compatibility with latest WordPress SEO versions

## [1.2.3]

### Changed

- Fully tested on WordPress 4.1

### Fixed

- `multipage_subtitle`, `multipage_navigation`, `multipage_content` filters not working

## [1.2.2]

### Fixed

- Overflow pages now redirect to the first page, consistent with default WordPress behavior

## [1.2.1]

### Added

- `rel` attributes on navigation links

### Changed

- Minor CSS changes
- New WordPress 4.0 plugin icons and banner

### Fixed

- Check existence of `$post` variable that could generate errors in some conditions

## [1.2.0]

### Added

- `toc` option on the nextpage shortcode to auto-scroll to the table of contents

## [1.1.4]

### Changed

- Tag title now works with WordPress SEO by Yoast (`%%page%%` variable)

## [1.1.3]

### Fixed

- Tag title on non-English WordPress installations

## [1.1.2]

### Fixed

- Settings page

## [1.1.1]

### Fixed

- Incompatibility with servers running PHP < 5.3

## [1.1.0]

### Added

- Tag title now reports the subpage title instead of the page number
- Three new filters: `multipage_subtitle`, `multipage_navigation`, `multipage_content`

### Changed

- Renamed to "Multipage" (CSS filenames changed to `multipage.css` / `multipage.min.css`)

## [1.0.1]

### Fixed

- Load default values even if settings were never saved

## [1.0.0]

### Added

- Option to hide comments on all subpages except the first
- TOC options: hide header, comments link, first page only, label choices, position, visibility

### Changed

- Completely rewritten core code for improved performance
- Settings menu renamed to "Multipage"

### Fixed

- Multipage posts display correctly on non-`is_single()` pages without `<!--more-->`

## [0.94]

### Fixed

- 404 error on the last page when there is no intro title

## [0.93]

### Added

- Settings page under "Settings" for summary appearance options

## [0.92]

### Fixed

- Returns 404 error if the requested page number doesn't exist

## [0.91]

### Added

- German language support

### Changed

- Tested with WordPress 3.8

## [0.90]

### Added

- RTL support
- Custom CSS auto-load from theme directory

### Fixed

- Page navigation in previews

## [0.85]

### Fixed

- Permalink bug with structures without a trailing slash

## [0.82]

### Fixed

- Removed a deprecated function that generated errors in some conditions

## [0.8]

### Added

- Initial code for configuration page (not active yet)

### Fixed

- 404 error caused by pretty URLs — now uses native page numbers

## [0.7]

### Added

- Custom intro title via nextpage shortcode on the first line

### Fixed

- Summary incorrectly displayed on loop pages

## [0.6]

### Changed

- Almost completely rewritten to use WordPress core nextpage functionality
- Subpage URLs now use `subpage-` prefix (due to attachment page conflicts)
- Works with all post types (pretty URLs on `post` only)

### Fixed

- Many bug fixes

## [0.55]

### Fixed

- Empty page no longer returns notices in debug mode
- Blocked usage on non-post post types (permalinks not yet supported)

## [0.50]

### Added

- Summary linked as a page
- `rel=next` / `rel=prev` links in `<head>`
- Previous/next page links at the bottom

### Changed

- Rewritten to request parts via page number

### Fixed

- Incorrect page title now returns 404

## [0.38]

### Added

- Previous/next page links at the bottom
- Language file support and Italian translation

## [0.30]

### Added

- Subpage title now part of the page title (head & HTML)
- Translation loading code

### Changed

- No longer needed to flush rewrite rules after activation

## [0.22]

- Initial beta release