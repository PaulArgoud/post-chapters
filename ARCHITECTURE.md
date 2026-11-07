# Architecture

This document describes the internal structure and data flow of the Multipage plugin (version 1.7.0).

## File Tree

```
sgr-nextpage-titles/
│
├── sgr-nextpage-titles.php          # Plugin entry point: header, MPP_VERSION / MPP__PLUGIN_FILE, loads class-mpp.php
├── class-mpp.php                    # Main Multipage class (singleton): bootstrap, request setup,
│                                    #   frontend rendering, SEO integrations, 404/redirect handling
├── uninstall.php                    # Cleanup on deletion (every site of a multisite network)
├── index.php                        # "Silence is golden" (one in every shipped folder)
│
├── blocks/
│   └── subpage/                     # "Subpage" block (multipage/subpage), no build step
│       ├── block.json               # Block metadata (apiVersion 3, "title" attribute)
│       ├── index.js                 # Editor script: plain ES5 using the wp.* globals
│       ├── index.asset.php          # Script dependencies and version (MPP_VERSION)
│       └── editor.css               # Editor style (loaded inside the iframed editor)
│
├── classes/
│   ├── class-mpp-admin.php          # MPP_Admin: admin menus, Settings API registration,
│   │                                #   TinyMCE / Quicktags / Classic block integration, admin CSS
│   └── class-mpp-shortcodes.php     # Multipage_Plugin_Shortcodes: [nextpage] shortcode handler
│
├── inc/
│   ├── mpp-admin.php                # mpp_admin(): creates the MPP_Admin instance (admin only)
│   ├── mpp-blocks.php               # mpp_register_blocks() on init, bundled JS translations fallback
│   ├── mpp-cli.php                  # MPP_CLI_Command: "wp multipage rebuild|status" (WP-CLI only)
│   ├── mpp-functions.php            # Version getters, text domain loading
│   ├── mpp-options.php              # Option definitions, validation, sanitization, cached getters
│   ├── mpp-parser.php               # Multipage_Parser: splits the content into subpages
│   ├── mpp-shortcodes.php           # Initializes Multipage_Plugin_Shortcodes
│   ├── mpp-template.php             # Title helpers, navigation (mpp_link_pages), TOC (mpp_toc), page URLs
│   ├── mpp-update.php               # Install/upgrade routines, subpage index rebuild (batches,
│   │                                #   lock, WP-Cron), activation/deactivation hooks
│   └── admin/
│       ├── admin-actions.php        # admin_init → mpp_admin_init → updater, settings, rebuild action
│       ├── admin-functions.php      # Admin URL helpers, tabs, Classic Editor detection
│       ├── admin-settings.php       # Main settings screen (field callbacks and render helpers)
│       ├── admin-advanced-settings.php  # Advanced screen and "Rebuild subpage index" action
│       └── tinymce/
│           ├── languages.php        # Deprecated, never loaded (see MPP_Admin::mpp_wp_mce_translation())
│           ├── js/plugin[.min].js   # TinyMCE "Subpage" button and placeholders
│           ├── css/multipage[.min].css  # TinyMCE content styles (placeholder image)
│           └── images/              # Placeholder images (1x and 2x)
│
├── admin/
│   └── css/multipage-admin[-rtl][.min].css  # Admin styles (LTR / RTL)
│
├── css/
│   └── multipage[-rtl][.min].css    # Frontend styles (LTR / RTL)
│
├── languages/
│   ├── sgr-nextpage-titles.pot                       # Translation template (wp i18n make-pot)
│   ├── sgr-nextpage-titles-{locale}.po               # Translation sources (not shipped)
│   ├── sgr-nextpage-titles-{locale}.mo               # Compiled translations
│   ├── sgr-nextpage-titles-{locale}.l10n.php         # PHP translations (WordPress 6.5+, wp i18n make-php)
│   └── sgr-nextpage-titles-{locale}-{md5}.json       # JS translations of blocks/subpage/index.js
│                                                     #   (md5 of "blocks/subpage/index.js", wp i18n make-json)
│
├── tests/                           # PHPUnit integration suite (WordPress test library, wp-env)
│   ├── bootstrap.php                # Locates the WP test library, loads the plugin on muplugins_loaded
│   ├── includes/
│   │   ├── class-mpp-unittestcase.php   # Mpp_UnitTestCase: resets the singleton, request helpers
│   │   └── class-mpp-test-corpus.php    # Mpp_Test_Corpus: real-world contents with expected pages
│   └── test-*.php                   # Parser, split HTML, request invariant, rendering, titles,
│                                    #   request/SEO, comments, options, admin notices, save_post,
│                                    #   rebuild, upgrade, blocks, shortcode, uninstall
│
├── bin/
│   ├── build-zip.sh                 # Builds build/sgr-nextpage-titles.zip (git-known files minus .distignore)
│   ├── check-i18n.sh                # Fails when the translation files are out of sync with the code
│   └── php-lint.php                 # php -l on every PHP file (composer lint)
│
├── .github/workflows/ci.yml         # CI: lint (PHP 7.4–8.4), PHPCS, i18n, PHPUnit (wp-env), zip
├── composer.json / composer.lock    # Dev-only tools: PHPCS + WPCS, PHPCompatibilityWP, PHPUnit
├── phpcs.xml.dist                   # Coding standards ruleset
├── phpunit.xml.dist                 # PHPUnit configuration (strict: notices/deprecations fail)
├── .wp-env.json                     # wp-env: WordPress 7.1.4, PHP 8.4, WP_DEBUG / SCRIPT_DEBUG
└── .distignore / .gitattributes     # Files kept out of the release zip / git archive
```

## Boot Sequence

```
1. WordPress loads sgr-nextpage-titles.php
   │
   ├── Defines MPP_VERSION ('1.7.0') and MPP__PLUGIN_FILE
   ├── require class-mpp.php
   └── multipage() → Multipage::instance()
       │
       ├── constants()       → MPP_PLUGIN_DIR, MPP_PLUGIN_URL, MPP_PATTERN, MPP_GUTENBERG_PATTERN
       │                       (the two patterns are kept for backward compatibility only)
       ├── setup_globals()   → $version, $db_version (1700), paths
       ├── includes()        → versions() (detects a legacy 1.3 install), then:
       │   ├── if is_admin(): add_action( 'init', 'mpp_admin' ) → new MPP_Admin
       │   ├── classes/class-mpp-admin.php, classes/class-mpp-shortcodes.php
       │   └── inc/mpp-{admin,functions,options,parser,shortcodes,template,update,blocks,cli}.php
       │       (mpp-cli.php returns immediately unless WP_CLI is running)
       ├── setup_actions()   → activation/deactivation hooks; unless the plugin is being
       │                       deactivated: save_post, pre_handle_404 (100), redirect_canonical (10)
       └── frontend_init()   → add_action( 'wp', mpp_post )
```

Hooks registered at file load: `init` → `mpp_load_multipage_textdomain()` and `mpp_register_blocks()`; `load_script_translation_file` → `mpp_load_bundled_script_translation_file()`; `added_option` / `updated_option` / `deleted_option` → `mpp_maybe_clear_options_cache()`; `mpp_rebuild_postmeta_batch` (WP-Cron) → `mpp_cron_rebuild_postmeta()`. No string is translated before `init`.

## Request Lifecycle (Frontend)

The live post content is the single source of truth: every request parses it with `Multipage_Parser::parse()`. The `_mpp_data` post meta is never read by the frontend.

### `Multipage::setup()`

`setup( $query = null )` loads the multipage data of the **main query** and is idempotent (keyed by post ID, raw page number and post object), so it can be called from several hooks and still works when another plugin short-circuits one of them.

- Bails out for admin, non-singular, 404, feed, embed, trackback and robots requests
- Parses `$post->post_content` through `parse_content()`, which caches the result in the object cache (group `multipage`, key `parse-{MPP_VERSION}-{md5(content)}`, one day) and skips the parse when the content holds no plugin marker
- Sets `$post_id`, `$pages`, `$page` (raw `page` query var, `0` when absent), `$current_page` (1-based: `/post/` and `/post/1/` are both page 1), `$max_num_pages`, `$mpp_data` (`slug => display title`, with the translated "Intro" and "Page N" fallbacks), `$mpp_index`, `$mpp_pagename`, `$page_title`
- Returns whether the post is a multipage post; the requested subpage may still be out of range

### 1. `pre_handle_404` (priority 100)

`Multipage::mpp_pre_handle_404()` calls `setup()`. A valid subpage returns `true`, so WordPress does not count the `<!--nextpage-->` markers of the raw content (a block or shortcode post has none). An out-of-range subpage gets `set_404()`, `status_header( 404 )` and `nocache_headers()`; core `redirect_canonical()` then redirects it to the permalink, as it does for posts split with core page breaks. When another plugin already short-circuited the filter, the data is still loaded and its value is returned unchanged.

### 2. `redirect_canonical` (priority 10)

`Multipage::mpp_redirect_canonical()` cancels redirects of valid subpages 2 and up, except a redirect to the canonical URL of the same subpage. The core redirect of `/post/1/` to `/post/` is kept.

### 3. `wp` action

`Multipage::mpp_post()` runs once, for a valid subpage of the main query's post:

```
mpp_post()
├── setup()
├── $post->post_content = Multipage_Parser::build_paginated_content( $this->pages )
│     (pages joined with <!--nextpage-->, or with the core/nextpage block for block content,
│      so that WP_Query::generate_postdata() returns exactly the parsed pages)
├── content_pagination           → filter_content_pagination() (same pages for any WP_Post instance)
├── if "Disable the standard pagination":
│     wp_link_pages_args → hide_standard_pagination(), wp_link_pages → filter_wp_link_pages()
├── if comments are hidden on this subpage (comments_allowed_on_current_page()):
│     comments_template → hide_comments(), pre_render_block → filter_comment_blocks()
├── if current page > 1: document_title_parts → mpp_document_title_parts() (title priority option)
├── the_content:
│     open_content_run() (PHP_INT_MIN), mpp_the_content() (content priority option),
│     close_content_run() (PHP_INT_MAX)
├── pre_render_block / render_block_core/post-content → track the core/post-content block
├── wp_enqueue_scripts → enqueue_styles()
├── wp_head → mpp_rel_links()
└── register_seo_integrations() (Yoast SEO, Rank Math, SEOPress, only when active)
```

### 4. Rendering: `the_content`

`Multipage::mpp_the_content()` first removes the internal empty-page placeholder, then decorates the content only when `should_filter_content()` allows it:

- the request is a valid multipage view, not admin, not a feed, singular;
- not inside `get_the_excerpt`, `the_excerpt` or `wp_head`, and not a `the_content` run started by `wp_trim_excerpt()`;
- `get_the_ID()` is the multipage post, `is_main_query()` is true, and the call is inside the loop or inside a `core/post-content` block (block themes, templates injected by plugins);
- the post does not require a password;
- the run is the outermost `the_content` run (shortcodes that filter their inner content with `the_content` are not decorated).

A re-entrancy flag, the hashes of the produced outputs and the `open_content_run()` / `close_content_run()` pair make sure that content already decorated (e.g. `the_content` applied again to rendered HTML, or to the Post Content block output) is never decorated twice.

`render_content()` assembles the output:

```
render_content($content)
├── Title: mpp_page_title_template ('<h2>%s</h2>') + mpp_get_subpage_title_html()
│     (skipped by "Hide intro title" when page 1 is the automatic intro)
├── Navigation: mpp_link_pages() ("continue" or "next-previous"; none when hidden)
├── Table of contents: mpp_toc() (hidden, or only on page 1 when configured)
├── title + TOC + content (or title + content + TOC for the "bottom" position)
├── apply_filters( 'mpp_the_content', $output, $page_title, $toc, $content )
└── + navigation
```

### 5. `wp_head`

`Multipage::mpp_rel_links()` prints `<link rel="prev">` and `<link rel="next">`. Yoast SEO and Rank Math are told not to print their own adjacent links.

### SEO integrations

| Plugin | Hooks | Effect |
|--------|-------|--------|
| WordPress core | `document_title_parts` | The "Page N" part of the document title becomes the subpage title (pages 2+) |
| Yoast SEO | `wpseo_replacements`, `wpseo_adjacent_rel_url`, `wpseo_canonical` | `%%page%%` = separator + subpage title; no Yoast rel prev/next; canonical of subpages 2+ is the subpage URL (a canonical set by the user is kept) |
| Rank Math | `rank_math/replacements`, `rank_math/frontend/disable_adjacent_rel_links` | `%page%` = separator + subpage title; no Rank Math rel prev/next |
| SEOPress | `seopress_context_paged`, `seopress_get_tag_page_value` | `%%page%%` filled on subpages (legacy variables and Tags engine) |

SEO plugins receive the plain-text title from `mpp_get_subpage_title_text()`.

## Content Parsing

`Multipage_Parser::parse( $content )` (in `inc/mpp-parser.php`) returns:

```php
array(
	'is_multipage' => bool,   // At least one page comes from a plugin marker.
	'has_intro'    => bool,   // The first page is the automatic intro.
	'pages'        => array(  // In document order.
		array(
			'slug'    => 'my-title',    // Unique.
			'title'   => 'My title',    // Raw; '' when untitled; '%%intro%%' for the intro.
			'content' => '...',         // Self-contained (balanced) HTML / block fragment.
			'kind'    => 'block',       // 'intro', 'block', 'shortcode' or 'core'.
		),
	),
)
```

```
Multipage_Parser::parse($content)
│
├── Bail out when the content contains neither "wp:multipage/subpage" nor "[nextpage"
├── tokenize_blocks()   → block delimiters (WP_Block_Parser regex), openers paired with closers
├── find_markers()      → one linear forward scan, in document order:
│   ├── multipage/subpage blocks (with or without JSON attributes, self-closing form);
│   │   the [nextpage] shortcode saved inside the block is part of the block
│   ├── [nextpage ...] shortcodes outside subpage blocks ([[nextpage]] is escaped)
│   ├── core page breaks (<!--nextpage--> and the core/nextpage block)
│   ├── ignores markers inside HTML comments, tags (attribute values), raw-text elements
│   │   (script, style, textarea...) and block delimiters
│   └── tracks the stack of open blocks and HTML elements at each marker
├── No plugin marker → not multipage (posts with core page breaks only keep core behaviour)
├── expand_markers()    → a shortcode alone in <p> or in a paragraph/shortcode/HTML block
│                          consumes that wrapper
├── build_segments()    → cuts the content: open blocks/elements are closed at the end of a page
│                          and reopened on the next one; elements the cut leaves empty (also with
│                          only &nbsp; or <br>) are dropped; whitespace next to the cut is trimmed
├── Pages:
│   ├── segment 0 is the intro when is_meaningful_content() (text, media, embeds, forms,
│   │   dynamic blocks); otherwise it is prepended to the first page unless disposable
│   ├── an untitled marker followed by content that is not meaningful is merged into the
│   │   previous page; only disposable leftovers (whitespace, comments, empty paragraphs) are dropped
│   └── a titled page without body gets Multipage_Parser::EMPTY_PAGE_PLACEHOLDER
└── Slugs: "slug" attribute, else sanitize_title( title ), else "page-N"; duplicates get -1, -2...
```

Titles come from the block JSON `title` attribute (a RichText string, HTML-encoded) or from the shortcode `title` attribute (raw text, parsed like `shortcode_parse_atts()` but without its rejection of an unclosed `<`). A missing, `false` or empty title gives an empty title.

Related methods:

- `Multipage_Parser::build_paginated_content( $pages, $use_block = null )` joins the page contents with `<!--nextpage-->`, or with `Multipage_Parser::BLOCK_PAGE_BREAK` (the `core/nextpage` block) when the pages hold block markup, so that the Post Content block still appends the standard page links when they are enabled
- `Multipage_Parser::multipage_return_array( $content )` returns `slug => title` (or `false`), derived from `parse()`: the format of the `_mpp_data` meta
- `Multipage_Parser::is_meaningful_content( $html )`, filterable with `mpp_parser_is_meaningful_content`
- `multipage_clean_content()` and `parse_nextpage_shortcode()` are kept for backward compatibility

## Subpage Titles

| Format | Used for | `mpp_get_subpage_title_html()` | `mpp_get_subpage_title_text()` |
|--------|----------|-------------------------------|-------------------------------|
| `html` | Block titles, translated fallbacks | `wp_kses()` with the inline allowlist (`mpp_get_subpage_title_allowed_html()`: strong, b, em, i, code, mark, sub, sup, s, span), `force_balance_tags()`, no double encoding | Tags stripped, entities decoded, whitespace collapsed (not escaped) |
| `text` | Titled `[nextpage]` shortcodes | Same, after encoding every `<` that does not start an allowed tag | Same, after the same encoding |

`Multipage::get_title_format( $index )` returns the format of a page. The intro is titled "Intro" and untitled pages "Page N" (`mpp_untitled_subpage_title`), both translated.

## Data Model

### Post Meta

| Key | Type | Description |
|-----|------|-------------|
| `_mpp_data` | `array` | Subpage index: `slug => title` (`%%intro%%` for the intro, `''` for an untitled page). Written on `save_post` (revisions and autosaves skipped, value slashed) and by the rebuild; deleted when the post is no longer split. Kept for third-party code: the frontend does not read it |

### Settings (wp_options)

Defined in `mpp_get_option_definitions()` (type, default, allowed values, Settings API group) and registered with `register_setting()` by `MPP_Admin::register_admin_settings()`.

| Option | Type | Default | Group | Description |
|--------|------|---------|-------|-------------|
| `mpp-hide-intro-title` | boolean | `false` | `mpp-settings` | Hide the automatic intro title |
| `mpp-comments-on-page` | string | `all` | `mpp-settings` | `all`, `first-page`, `last-page` |
| `mpp-continue-or-prev-next` | string | `continue` | `mpp-settings` | `continue`, `next-previous`, `hidden` |
| `mpp-disable-standard-pagination` | boolean | `true` | `mpp-settings` | Hide the WordPress page links |
| `mpp-toc-only-on-the-first-page` | boolean | `false` | `mpp-settings` | Show the TOC on page 1 only |
| `mpp-toc-position` | string | `top-right` | `mpp-settings` | `top-left`, `top-right`, `top`, `bottom`, `hidden` |
| `mpp-toc-row-labels` | string | `number` | `mpp-settings` | `number`, `page`, `hidden` |
| `mpp-hide-toc-header` | boolean | `false` | `mpp-settings` | Hide the "Contents" header |
| `mpp-comments-toc-link` | boolean | `false` | `mpp-settings` | Comments link in the TOC |
| `_mpp-rewrite-title-priority` | priority | `20` | `mpp-advanced-settings` | Priority of the document title filter (UI: 100, 50, 20, 10, 5; any integer is kept) |
| `_mpp-rewrite-content-priority` | priority | `20` | `mpp-advanced-settings` | Priority of the `the_content` filter |
| `mpp-disable-tinymce-buttons` | boolean | `false` | `mpp-advanced-settings` | Disable the TinyMCE Subpage button |

- Both screens post to `options.php`. `mpp_sanitize_option()` stores booleans as `1`/`0` (an unchecked checkbox is posted as `null`), and an invalid value keeps the current valid value and adds one settings error naming the field
- Getters (`mpp_hide_intro_title()`, `mpp_get_toc_position()`...) read through `mpp_get_option()` (in-memory cache, default applied per call) and `mpp_validate_option_value()`, so they always return a valid value. Legacy stored values (`''`, `'1'`, `'on'`, `'off'`) keep their meaning
- The cache is cleared automatically when a Multipage option is added, updated or deleted; `mpp_clear_options_cache()` is still available
- Third-party fields added to these screens with `add_settings_field()` must also call `register_setting( 'mpp-settings', ... )` (or `'mpp-advanced-settings'`)

### Internal options

| Option | Description |
|--------|-------------|
| `_mpp_db_version` | Database version (`1700` for 1.7.0, `1000` for 1.4–1.6) |
| `_mpp-postmeta-built` | Timestamp of the last complete index rebuild |
| `_mpp-rebuild-state` | Pending background rebuild: cursor (last post ID), counts, start time (not autoloaded) |
| `_mpp-rebuild-last` | Result of the last complete rebuild: time, processed, updated, deleted (not autoloaded) |
| `_mpp-rebuild-lock` | Rebuild lock: a timestamp and an owner token separated by a pipe (written with direct atomic queries) |
| `multipage` | Legacy 1.3 settings, converted then deleted by `mpp_update_to_1_4()` |

The object cache group `multipage` holds the parse results (see `Multipage::setup()`).

## Subpage Index Rebuild

`inc/mpp-update.php` rebuilds `_mpp_data` in batches of posts ordered by ID:

- **Candidates**: posts containing `[nextpage` or `wp:multipage/subpage`, or already holding `_mpp_data` (stale entries are deleted); revisions and auto-drafts are skipped. Each batch is one prepared query that selects `ID` and `post_content` directly
- **Batch**: `mpp_process_postmeta_batch( $after_id, $batch_size, $dry_run )`, 200 posts by default (`mpp_rebuild_postmeta_batch_size`). `mpp_run_postmeta_rebuild( $args )` runs batches within an optional time budget, resumes from saved totals and refreshes the lock after each batch
- **Lock**: `mpp_rebuild_acquire_lock()` (atomic `INSERT IGNORE`; a lock not refreshed for 5 minutes is taken over with a compare-and-set `UPDATE`), `mpp_rebuild_refresh_lock()`, `mpp_rebuild_release_lock()` (owner only), `mpp_is_rebuild_locked()`
- **Background (WP-Cron)**: `mpp_schedule_postmeta_rebuild()` saves `_mpp-rebuild-state` and schedules the single event `mpp_rebuild_postmeta_batch`. `mpp_cron_rebuild_postmeta()` takes the lock, schedules a watchdog, processes batches for 15 seconds (`mpp_rebuild_cron_time_limit`), then reschedules itself or completes (`mpp_complete_postmeta_rebuild()`). When locked, it retries a minute later
- **Admin**: the "Rebuild subpage index" form on the Advanced screen posts to `admin-post.php?action=mpp_rebuild_postmeta` (nonce + `manage_options`). `mpp_admin_rebuild_postmeta_handler()` processes batches for 15 seconds (`mpp_rebuild_admin_time_limit`), hands the rest to WP-Cron, and redirects with `mpp-rebuild=done|partial|locked`; the notice is added by `mpp_admin_rebuild_postmeta_notice()`
- **WP-CLI**: `wp multipage rebuild [--batch-size=<n>] [--dry-run]` and `wp multipage status [--format=table|json|yaml|csv]` (`MPP_CLI_Command`, `mpp_get_postmeta_index_stats()`)

### Install, upgrade, activation

- `mpp_setup_updater()` runs on `mpp_admin_init` (priority 1000). `mpp_get_installed_db_version()` combines the legacy 1.3 detection with `_mpp_db_version`
- Fresh install: schedules a background rebuild, fires `mpp_on_install`
- Update: `mpp_update_to_1_4()` (below 1000: converts the legacy settings, schedules a rebuild) and `mpp_update_to_1_7()` (below 1700: background rebuild), then fires `mpp_on_update`; `mpp_version_bump()` stores the new version
- `mpp_activation()` schedules a rebuild (posts edited while the plugin was inactive) and fires `mpp_activation`; `mpp_deactivation()` clears the cron hook and fires `mpp_deactivation`
- `uninstall.php` deletes every option above, the cron hook and all `_mpp_data` meta, on every site of a multisite network

## Hooks Reference

### Content and template filters

| Filter | Arguments | Location | Description |
|--------|-----------|----------|-------------|
| `mpp_the_content` | `$output, $page_title, $toc, $content` | `class-mpp.php` | Title + TOC + content (the navigation is appended afterwards) |
| `mpp_page_title_template` | `$template` | `class-mpp.php` | Subpage title template, `%s` = title HTML (default `<h2>%s</h2>`) |
| `mpp_untitled_subpage_title` | `$title, $number, $page` | `class-mpp.php` | Fallback title of an untitled subpage (default "Page N") |
| `mpp_comment_block_names` | `$block_names` | `class-mpp.php` | Blocks hidden on subpages without comments (`core/comments`, `core/post-comments`, `core/post-comments-form`, `core/comments-title`, `core/comments-pagination`, `core/comment-template`, `core/comments-query-loop`) |
| `mpp_subpage_title_allowed_html` | `$allowed` | `mpp-template.php` | Inline tags allowed in subpage titles (`wp_kses()` format) |
| `mpp_link_pages_args` | `$params` | `mpp-template.php` | Navigation arguments |
| `mpp_link_pages_link` | `$link, $page` / `$text, $link` | `mpp-template.php` | One navigation link ("Continue" / "Back to" passes the text and the link) |
| `mpp_link_pages` | `$output, $args` | `mpp-template.php` | Navigation HTML |
| `mpp_toc_args` | `$params` | `mpp-template.php` | TOC arguments |
| `mpp_toc_pages_row` | `$row, $title, $i` | `mpp-template.php` | One TOC row |
| `mpp_toc` | `$output, $args` | `mpp-template.php` | TOC HTML |
| `mpp_parser_is_meaningful_content` | `$meaningful, $html` | `mpp-parser.php` | Whether a fragment counts as content (intro detection, untitled pages) |

### Option filters

| Filter | Location | Description |
|--------|----------|-------------|
| `mpp_get_option_definitions` | `mpp-options.php` | Option definitions (e.g. extra allowed values); core options cannot be removed |
| `mpp_get_option_choice_labels` | `mpp-options.php` | Labels of the choices on the settings screens |
| `mpp_hideintro_title` | `mpp-options.php` | Value of `mpp_hide_intro_title()` |
| `mpp_get_comments_on_page` | `mpp-options.php` | Value of `mpp_get_comments_on_page()` |
| `mpp_get_continue_or_prev_next` | `mpp-options.php` | Value of `mpp_get_continue_or_prev_next()` |
| `mpp_disable_standard_pagination` | `mpp-options.php` | Value of `mpp_disable_standard_pagination()` |
| `mpp_toc_only_on_the_first_page` | `mpp-options.php` | Value of `mpp_toc_only_on_the_first_page()` |
| `mpp_get_toc_position` | `mpp-options.php` | Value of `mpp_get_toc_position()` |
| `mpp_get_toc_row_labels` | `mpp-options.php` | Value of `mpp_get_toc_row_labels()` |
| `mpp_hide_toc_header` | `mpp-options.php` | Value of `mpp_hide_toc_header()` |
| `mpp_comments_toc_link` | `mpp-options.php` | Value of `mpp_comments_toc_link()` |
| `mpp_get_rewrite_title_priority` | `mpp-options.php` | Value of `mpp_get_rewrite_title_priority()` |
| `mpp_get_rewrite_content_priority` | `mpp-options.php` | Value of `mpp_get_rewrite_content_priority()` |
| `mpp_disable_tinymce_buttons` | `mpp-options.php` | Value of `mpp_disable_tinymce_buttons()` |

### Rebuild filters

| Filter | Default | Location | Description |
|--------|---------|----------|-------------|
| `mpp_rebuild_postmeta_batch_size` | `200` | `mpp-update.php` | Posts per rebuild batch |
| `mpp_rebuild_cron_time_limit` | `15` | `mpp-update.php` | Seconds per background (WP-Cron) run |
| `mpp_rebuild_admin_time_limit` | `15` | `admin-advanced-settings.php` | Seconds spent by the "Rebuild subpage index" request before handing over to WP-Cron |

### Admin filters

| Filter | Location | Description |
|--------|----------|-------------|
| `mpp_get_admin_tabs` | `admin-functions.php` | Settings tabs |
| `mpp_admin_tabs` | `admin-functions.php` | Tabs about to be displayed |

### Actions

| Action | Location | Description |
|--------|----------|-------------|
| `mpp_admin_init` | `admin-actions.php` | Fired on `admin_init`; runs the updater (priority 1000), the settings registration and the rebuild notice |
| `mpp_register_admin_settings` | `admin-actions.php` | Settings registration (`MPP_Admin::register_admin_settings()`) |
| `mpp_admin_tabs` | `admin-functions.php` | After the settings tabs |
| `mpp_on_install` | `mpp-update.php` | Fresh install |
| `mpp_on_update` | `mpp-update.php` | Update from an older version |
| `mpp_activation` | `mpp-update.php` | Plugin activation |
| `mpp_deactivation` | `mpp-update.php` | Plugin deactivation |
| `mpp_rebuild_postmeta_batch` | `mpp-update.php` | WP-Cron event of the background rebuild (`mpp_cron_rebuild_postmeta()`) |

## Editors Integration

### Block editor

The "Subpage" block (`multipage/subpage`) lives in `blocks/subpage/` and needs no build step:

- `mpp_register_blocks()` (`inc/mpp-blocks.php`) runs on `init` for every request and calls `register_block_type( MPP_PLUGIN_DIR . 'blocks/subpage' )`, then `wp_set_script_translations()` on the editor script (handle `multipage-subpage-editor-script`), without a path so that language packs win; `mpp_load_bundled_script_translation_file()` returns the bundled `languages/*.json` file as the last resort
- `block.json`: `apiVersion` 3 (iframed editor), category `design`, attribute `title` (string, default `false`), supports `html` but no custom class name. `index.js` holds a copy of the metadata so that the block also registers on screens that do not bootstrap the server definitions (keep both in sync)
- Edit: a RichText `h2` without formatting; Enter at the end of the title inserts a paragraph after the block
- Save: `[nextpage title="..."]` (only `"` encoded), byte-identical to every previous version, inside the block comment: `<!-- wp:multipage/subpage {"title":"..."} -->[nextpage title="..."]<!-- /wp:multipage/subpage -->`. The parser also honors an optional `slug` attribute

### Classic editor (TinyMCE and Quicktags)

- Unless `mpp-disable-tinymce-buttons` is set: `mce_buttons`, `mce_external_plugins` (`inc/admin/tinymce/js/plugin[.min].js`), `mce_css`, `wp_mce_translation`, and `tiny_mce_before_init` (`MPP_Admin::mpp_classic_block_mce_css()`, which adds the placeholder styles to the Classic block of the block editor)
- The dialog escapes the title (`&`, `"`, `<`, `>`, `[`, `]`) and inserts nothing for an empty title. In the Visual editor, shortcodes are shown as placeholder images that store the exact shortcode value (`data-mpp-title`), so the Visual/Text round trip is lossless
- Text editor: `MPP_Admin::editor_add_quicktags()` attaches an inline script to the core `quicktags` script (button `eg_subpage`), with the same escaping

### Shortcode

`[nextpage title="My Title" slug="my-slug"]` is registered by `Multipage_Plugin_Shortcodes`. On multipage views the shortcodes are removed before rendering; where the content is not split (feeds, REST API, excerpts) the handler returns an empty string.

## CSS Loading

`Multipage::enqueue_styles()` runs on multipage views only. It looks for `css/multipage[-rtl][.min].css` (`-rtl` on RTL sites, `.min` unless `SCRIPT_DEBUG`) in this order:

1. Child theme
2. Parent theme
3. Plugin default (falls back to the LTR file if the RTL one is missing)

The styles use `rem` and reset the typography of `.mpp-toc-container` and `.mpp-post-navigation`. In the RTL file, the `top-left` / `top-right` TOC positions keep their physical side. The admin stylesheet is `admin/css/multipage-admin[-rtl][.min].css`.

## Translations

- `mpp_load_multipage_textdomain()` (`init`) calls `load_plugin_textdomain( 'sgr-nextpage-titles', false, '<plugin folder>/languages' )`. WordPress loads `WP_LANG_DIR/plugins` (language packs) first and the bundled files only as a fallback; WordPress 6.5+ reads the `.l10n.php` file before the `.mo`
- Block strings come from `block.json` (translated in PHP, contexts "block title", "block description", "block keyword") and from `index.js` (JSON file)
- See [CONTRIBUTING.md](CONTRIBUTING.md#translations) for the regeneration workflow and `bin/check-i18n.sh`

## Deprecated API

| Deprecated | Since | Replacement |
|------------|-------|-------------|
| `Multipage::multipage_return_array()` | 1.5.14 | `Multipage_Parser::multipage_return_array()` (triggers `_deprecated_function()` since 1.7.0) |
| `Multipage::mpp_the_title()` | 1.7.0 | No longer hooked; returns the title unchanged (see the SEO integrations) |
| `MPP_Admin::mpp_enqueue_block_editor_assets()` | 1.7.0 | `mpp_register_blocks()` |
| `mpp_admin_settings_save()`, `mpp_admin_advanced_settings_save()` | 1.7.0 | Settings API (`options.php`); no-ops |
| `mpp_admin_advanced_callback_build_mpp_postmeta_data()` | 1.7.0 | `mpp_admin_rebuild_postmeta_section()` |
| `inc/admin/tinymce/languages.php` | 1.5 | `MPP_Admin::mpp_wp_mce_translation()` (the file is never loaded) |
| `MPP_PATTERN`, `MPP_GUTENBERG_PATTERN` | 1.7.0 | `Multipage_Parser::parse()`. The constants are kept for backward compatibility (`MPP_PATTERN` is only used by the legacy `Multipage_Parser::parse_nextpage_shortcode()`) |

## Tests and Tooling

- PHPUnit 9.6 integration tests on the WordPress test library (`tests/`), run inside wp-env (`composer test:wp-env`). `Mpp_UnitTestCase` resets the `Multipage` singleton between simulated requests and provides helpers such as `go_to_subpage()` and `render_subpage()`; `Mpp_Test_Corpus` holds real-world contents with their expected pages
- `phpcs.xml.dist`: WordPress + PHPCompatibilityWP, PHP 7.4+, WordPress 6.3+, text domain `sgr-nextpage-titles`, prefixes `mpp_`, `_mpp_` and `multipage`
- CI (`.github/workflows/ci.yml`): PHP lint 7.4–8.4, PHPCS, i18n check, PHPUnit on WordPress 6.3 / PHP 7.4, 7.1.4 / PHP 7.4 and 7.1.4 / PHP 8.4, release zip

See [CONTRIBUTING.md](CONTRIBUTING.md) for the commands.
