# Architecture

This document describes the internal structure and data flow of the Paginova plugin (version 2.2.0, slug `paginova`), named Multipage (`sgr-nextpage-titles`) up to 1.7.1 and Post Chapters (`post-chapters`) from 2.0.0 to 2.1.0.

## File Tree

```
paginova/                            # Plugin folder (repository clone and release zip)
│
├── paginova.php                     # Plugin entry point: header (incl. Update URI), Post Chapters / Multipage guards,
│                                    #   PCHAP_VERSION / PCHAP__PLUGIN_FILE, loads class-pchap.php
├── class-pchap.php                    # Main PChap class (singleton): bootstrap, request setup,
│                                    #   frontend rendering, 404/redirect handling
├── uninstall.php                    # Cleanup on deletion (every site of a multisite network)
├── index.php                        # "Silence is golden" (one in every shipped folder)
├── readme.txt                       # WordPress.org directory readme (Stable tag, FAQ, changelog)
├── README.md / CHANGELOG.md / LICENSE  # GitHub readme, full history, GPL v3 (shipped)
├── .wordpress-org/                 # WordPress.org assets for SVN /assets: screenshots, banners, icons (not shipped)
│
├── blocks/
│   └── subpage/                     # "Subpage" block (multipage/subpage), no build step
│       ├── block.json               # Block metadata (apiVersion 3, "title" attribute)
│       ├── index.js                 # Editor script: plain ES5 using the wp.* globals
│       ├── index.asset.php          # Script dependencies and version (PCHAP_VERSION)
│       └── editor.css               # Editor style (loaded inside the iframed editor)
│
├── classes/
│   ├── class-pchap-admin.php          # PChap_Admin: admin menus, Settings API registration,
│   │                                #   TinyMCE / Quicktags / Classic block integration, admin CSS
│   ├── class-pchap-seo.php            # PChap_SEO (pchap()->seo): document title, rel prev/next, SEO plugins
│   ├── class-pchap-comments.php       # PChap_Comments (pchap()->comments): comments on all / first / last chapter
│   ├── class-pchap-speculation.php    # PChap_Speculation (pchap()->speculation): the chapter links are prefetched (WP 6.8+)
│   └── class-pchap-shortcodes.php     # PChap_Shortcodes: [nextpage] shortcode handler
│
├── inc/
│   ├── pchap-admin.php                # pchap_admin(): creates the PChap_Admin instance (admin only)
│   ├── pchap-blocks.php               # pchap_register_blocks() on init (block type, script translations)
│   ├── pchap-cli.php                  # PChap_CLI_Command: "wp paginova rebuild|status" (WP-CLI only)
│   ├── pchap-functions.php            # Version getters, pchap_apply_filters() / pchap_do_action()
│   ├── pchap-l10n.php                 # Bundled translations of /languages: text domain, .mo and JSON
│   │                                #   swaps (GitHub build only; left out of the WordPress.org build)
│   ├── pchap-options.php              # Option definitions, validation, sanitization, cached getters
│   ├── pchap-parser.php               # PChap_Parser: splits the content into subpages
│   ├── pchap-shortcodes.php           # Initializes PChap_Shortcodes
│   ├── pchap-template.php             # Title helpers, navigation (pchap_link_pages), TOC (pchap_toc), page URLs
│   ├── pchap-update.php               # Install/upgrade routines, subpage index rebuild (batches,
│   │                                #   lock, WP-Cron), activation/deactivation hooks
│   └── admin/
│       ├── admin-actions.php        # admin_init → pchap_admin_init → updater, settings, rebuild action
│       ├── admin-functions.php      # Admin URL helpers, tabs, Classic Editor detection
│       ├── admin-settings.php       # Main settings screen: pchap_get_settings_sections() / _fields() (one field per
│       │                            #   option), generic renderer, render helpers
│       ├── admin-advanced-settings.php  # Advanced screen and "Rebuild chapter index" action
│       └── tinymce/
│           ├── js/plugin[.min].js   # TinyMCE "Subpage" button and placeholders
│           ├── css/paginova[.min].css # TinyMCE content styles (placeholder image)
│           └── images/              # Placeholder images (1x and 2x)
│
├── admin/
│   └── css/paginova-admin[-rtl][.min].css  # Admin styles (LTR / RTL)
│
├── css/
│   └── paginova[-rtl][.min].css     # Frontend styles (LTR / RTL)
│
├── languages/                       # The WordPress.org build keeps only paginova.pot
│   ├── paginova.pot                 # Translation template (wp i18n make-pot)
│   ├── paginova-{locale}.po         # Translation sources (not shipped)
│   ├── paginova-{locale}.mo         # Compiled translations
│   ├── paginova-{locale}.l10n.php   # PHP translations (WordPress 6.5+, wp i18n make-php)
│   └── paginova-{locale}-{md5}.json  # JS translations of blocks/subpage/index.js
│                                    #   (md5 of "blocks/subpage/index.js", wp i18n make-json)
│
├── tests/                           # PHPUnit integration suite (WordPress test library, wp-env)
│   ├── bootstrap.php                # Locates the WP test library, loads the plugin on muplugins_loaded
│   ├── includes/
│   │   ├── class-pchap-unittestcase.php   # PChap_UnitTestCase: resets the singleton, request helpers
│   │   └── class-pchap-test-corpus.php    # PChap_Test_Corpus: real-world contents with expected pages
│   └── test-*.php                   # Parser, split HTML, request invariant, rendering, titles,
│                                    #   request/SEO, comments, options, admin notices, save_post,
│                                    #   rebuild, upgrade, blocks, shortcode, uninstall
│
├── bin/
│   ├── build-zip.sh                 # Builds build/paginova.zip (git-known files minus .distignore; --wporg: build/paginova-wporg.zip,
│   │                                #   without Update URI, inc/pchap-l10n.php and the languages/ files but the .pot)
│   ├── check-i18n.sh                # Fails when the translation files are out of sync with the code
│   └── php-lint.php                 # php -l on every PHP file (composer lint)
│
├── .github/workflows/ci.yml         # CI: lint (PHP 7.4–8.4), PHPCS, i18n, PHPUnit (wp-env), zip
├── composer.json / composer.lock    # Dev-only tools: PHPCS + WPCS, PHPCompatibilityWP, PHPUnit
├── phpcs.xml.dist                   # Coding standards ruleset
├── phpunit.xml.dist                 # PHPUnit configuration (strict: notices/deprecations fail)
├── .wp-env.json                     # wp-env (single environment, tests run in its cli container): WordPress 7.1.4, PHP 8.4
└── .distignore / .gitattributes     # Files kept out of the release zip / git archive
```

## Boot Sequence

```
1. WordPress loads paginova.php
   │
   ├── pchap_is_post_chapters_active()? → only an admin notice, nothing else is declared
   ├── Defines PCHAP_VERSION ('2.2.0') and PCHAP__PLUGIN_FILE
   ├── pchap_is_multipage_active()? → only an admin notice, nothing else is loaded
   ├── require class-pchap.php
   └── pchap() → PChap::instance()
       │
       ├── constants()       → PCHAP_PLUGIN_DIR, PCHAP_PLUGIN_URL, PCHAP_PATTERN, PCHAP_GUTENBERG_PATTERN
       │                       (the two patterns are kept for backward compatibility only)
       ├── setup_globals()   → $version, $db_version (2000), paths
       ├── includes()        → versions() (detects a legacy 1.3 install), then:
       │   ├── if is_admin(): add_action( 'init', 'pchap_admin' ) → new PChap_Admin
       │   ├── classes/class-pchap-{admin,shortcodes,seo,comments}.php (then new PChap_SEO / PChap_Comments)
       │   ├── inc/pchap-{admin,functions,options,parser,shortcodes,template,update,blocks,cli}.php
       │   │   (pchap-cli.php returns immediately unless WP_CLI is running)
       │   └── inc/pchap-l10n.php, only if the file exists (absent from the WordPress.org build)
       ├── setup_actions()   → activation/deactivation hooks; unless the plugin is being
       │                       deactivated: save_post, pre_handle_404 (100), redirect_canonical (10)
       └── frontend_init()   → add_action( 'wp', pchap_post )
```

Hooks registered at file load: `init` → `pchap_register_blocks()`; with `inc/pchap-l10n.php`: `init` → `pchap_load_textdomain()`, `load_textdomain_mofile` → `pchap_load_bundled_textdomain_mofile()`, `load_script_translation_file` → `pchap_load_bundled_script_translation_file()`; `added_option` / `updated_option` / `deleted_option` → `pchap_maybe_clear_options_cache()`; `pchap_rebuild_postmeta_batch` (WP-Cron) → `pchap_cron_rebuild_postmeta()`. No string is translated before `init`.

**Coexistence with Post Chapters.** Post Chapters 2.x is the same plugin under its former slug: it declares the same functions, classes and constants, so the two cannot run together. `pchap_is_post_chapters_active()` is the first thing `paginova.php` runs, before it declares anything Post Chapters also declares (`paginova/` loads before `post-chapters/`): it returns true when `pchap()` or `PCHAP_VERSION` already exists, or when the active plugins (site and network) list an existing file named `post-chapters.php`. Paginova then only hooks `pchap_post_chapters_active_notice()` to `admin_notices` / `network_admin_notices` and returns. The idle notices load the bundled `.mo` file with `pchap_load_idle_textdomain()`, when it is readable, since the plugin text domain is not set up. Post Chapters must be removed by deleting its folder: its `uninstall.php` deletes the `mpp-*` options and `_mpp_data`, which Paginova uses.

**Coexistence with Multipage.** Multipage handles the same shortcode, block and options. `pchap_is_multipage_active()` reads the active plugins (site and network) for an existing file named `sgr-nextpage-titles.php` (an entry whose file was deleted is ignored, as WordPress does), and checks the `Multipage` class: the class alone is not enough, because `paginova/` loads before `sgr-nextpage-titles/`. When Multipage is active, `paginova.php` only hooks `pchap_multipage_active_notice()` to `admin_notices` / `network_admin_notices` and returns. Since every class, function and constant was renamed in 2.0.0, both plugins can be installed (and even active) at the same time without a fatal error.

**Hooks.** Every hook of the plugin is fired through `pchap_apply_filters()` / `pchap_do_action()` (`inc/pchap-functions.php`): they run the `pchap_*` hook, then, when callbacks are attached to it, the matching `mpp_*` name of Multipage through `apply_filters_deprecated()` / `do_action_deprecated()` (deprecated since 2.0.0, same arguments, after the `pchap_*` callbacks).

The header declares `Update URI: https://github.com/PaulArgoud/paginova`. Since WordPress 5.8, the WordPress.org update API ignores plugins whose Update URI points elsewhere, so a WordPress.org plugin of the same slug can never overwrite this version. Builds published to WordPress.org must drop that line (Plugin Check reports it as an error): `bin/build-zip.sh --wporg` does it.

## Request Lifecycle (Frontend)

The live post content is the single source of truth: every request parses it with `PChap_Parser::parse()`. The `_mpp_data` post meta is never read by the frontend.

### `PChap::setup()`

`setup( $query = null )` loads the multipage data of the **main query** and is idempotent (keyed by post ID, raw page number and post object), so it can be called from several hooks and still works when another plugin short-circuits one of them.

- Bails out for admin, non-singular, 404, feed, embed, trackback and robots requests
- Parses `$post->post_content` through `parse_content()`, which caches the result in the object cache (group `paginova`, key `parse-{PCHAP_VERSION}-{parser hash}-{md5(content)}`, one day; the parser hash is the first 12 characters of `md5_file( inc/pchap-parser.php )`, so a persistent cache such as Redis never serves a parse made by another build of the same version) and skips the parse when the content holds no plugin marker
- Steps aside (returns false) for a post that a page builder renders (`PChap::is_builder_post()`: post meta of Elementor, Divi, Beaver Builder and Bricks; filter `pchap_is_builder_post`): the builder replaces the content by its layout on every page, so the chapters would show the whole post. Checked only after the parse found a chapter marker, so posts without chapters cost nothing more
- Sets `$post_id`, `$pages`, `$page` (raw `page` query var, `0` when absent), `$current_page` (1-based: `/post/` and `/post/1/` are both page 1), `$max_num_pages`, `$pchap_data` (`slug => display title`, with the translated "Intro" and "Page N" fallbacks), `$pchap_index`, `$pchap_pagename`, `$page_title`
- Returns whether the post is a multipage post; the requested subpage may still be out of range

### 1. `pre_handle_404` (priority 100)

`PChap::pchap_pre_handle_404()` calls `setup()`. A valid subpage returns `true`, so WordPress does not count the `<!--nextpage-->` markers of the raw content (a block or shortcode post has none). An out-of-range subpage gets `set_404()`, `status_header( 404 )` and `nocache_headers()`; core `redirect_canonical()` then redirects it to the permalink, as it does for posts split with core page breaks. When another plugin already short-circuited the filter, the data is still loaded and its value is returned unchanged.

### 2. `redirect_canonical` (priority 10)

`PChap::pchap_redirect_canonical()` cancels redirects of valid subpages 2 and up, except a redirect to the canonical URL of the same subpage. The core redirect of `/post/1/` to `/post/` is kept.

### 3. `wp` action

`PChap::pchap_post()` runs once, for a valid subpage of the main query's post:

```
pchap_post()
├── setup()
├── $post->post_content = PChap_Parser::build_paginated_content( $this->pages )
│     (pages joined with <!--nextpage-->, or with the core/nextpage block for block content,
│      so that WP_Query::generate_postdata() returns exactly the parsed pages)
├── content_pagination           → filter_content_pagination() (same pages for any WP_Post instance)
├── if "Disable the standard pagination":
│     wp_link_pages_args → hide_standard_pagination(), wp_link_pages → filter_wp_link_pages()
├── if comments are hidden on this subpage (comments_allowed_on_current_page()):
│     comments_template → hide_comments(), pre_render_block → filter_comment_blocks()
├── if current page > 1: document_title_parts → pchap_document_title_parts() (title priority option)
├── the_content:
│     open_content_run() (PHP_INT_MIN), pchap_the_content() (content priority option),
│     close_content_run() (PHP_INT_MAX)
├── pre_render_block / render_block_core/post-content → track the core/post-content block
├── wp_enqueue_scripts → enqueue_styles()
├── comments->register_hooks() → comments_template, pre_render_block (when the chapter shows no comments)
├── seo->register_hooks() → document_title_parts, wp_head (rel links), the SEO plugin integrations (only when active)
└── speculation->register_hooks() → wp_load_speculation_rules (WordPress 6.8+)
```

### 4. Rendering: `the_content`

`PChap::pchap_the_content()` first removes the internal empty-page placeholder, then decorates the content only when `should_filter_content()` allows it:

- the request is a valid multipage view, not admin, not a feed, singular;
- not inside `get_the_excerpt`, `the_excerpt` or `wp_head`, and not a `the_content` run started by `wp_trim_excerpt()`;
- `get_the_ID()` is the multipage post, `is_main_query()` is true, and the call is inside the loop or inside a `core/post-content` block (block themes, templates injected by plugins);
- the post does not require a password;
- the run is the outermost `the_content` run (shortcodes that filter their inner content with `the_content` are not decorated).

A re-entrancy flag, the hashes of the produced outputs and the `open_content_run()` / `close_content_run()` pair make sure that content already decorated (e.g. `the_content` applied again to rendered HTML, or to the Post Content block output) is never decorated twice.

`render_content()` assembles the output:

```
render_content($content)
├── Title: pchap_page_title_template ('<h2>%s</h2>') + pchap_get_subpage_title_html(), through wp_kses_post()
│     (skipped by "Hide intro title" when page 1 is the automatic intro)
├── Navigation: pchap_link_pages() ("continue" or "next-previous"; none when hidden)
├── Table of contents: pchap_toc() (hidden, or only on page 1 when configured)
├── title + TOC + content (or title + content + TOC for the "bottom" position)
├── apply_filters( 'pchap_the_content', $output, $page_title, $toc, $content )
└── + navigation
```

The HTML built by the plugin is escaped after its filters: the chapter title template output, and the filtered output of `pchap_toc()` (`pchap_toc`) and `pchap_link_pages()` (`pchap_link_pages`), go through `wp_kses_post()`. The post content itself is never escaped, as in core: kses would strip the embeds, iframes and scripts that authors with the `unfiltered_html` capability may publish.

### 5. `wp_head`

`PChap_SEO::print_rel_links()` prints `<link rel="prev">` and `<link rel="next">`. Yoast SEO and Rank Math are told not to print their own adjacent links.

### SEO integrations

`PChap_SEO` (`pchap()->seo`, `classes/class-pchap-seo.php`) does what is common to all SEO plugins, and one `PChap_SEO_Integration` object per plugin (`classes/seo/`, `pchap()->seo->get_integration( 'yoast' )`) registers its hooks only when the plugin is active. The `pchap_seo_integrations` filter adds integrations.

Common to every SEO plugin:

- `document_title_parts`: the "Page N" part of the core document title becomes the chapter title (pages 2+)
- `pre_get_document_title` (added on `template_redirect`, last, because The SEO Framework removes every filter of that hook on `template_redirect` 20): `ensure_chapter_title()` puts the chapter title after the post title, with the separator of the plugin, when the plugin's title has none (it removes the "Page N" part). Rank Math and SEOPress have no page variable in their default title template, so without it every chapter would share the same `<title>`
- `wp_head`: `<link rel="prev">` and `<link rel="next">`; the plugins that print their own are told not to

What each integration changes, checked on the real plugins by `bin/check-seo.sh`:

| Plugin | Hooks | Effect |
|--------|-------|--------|
| Yoast SEO | `wpseo_replacements`, `wpseo_adjacent_rel_url`, `wpseo_canonical`, `wpseo_opengraph_url`, `wpseo_schema_webpage` | `%%page%%` = separator + chapter title; no Yoast rel prev/next; canonical, `og:url` and schema WebPage `url` of chapters 2+ are the chapter URL (a canonical set by the user is kept; the schema `@id`s are kept) |
| Rank Math | `rank_math/replacements`, `rank_math/frontend/disable_adjacent_rel_links`, `rank_math/opengraph/{facebook,twitter}/*_title` | `%page%` = separator + chapter title; no Rank Math rel prev/next; the chapter title in the Open Graph and Twitter titles |
| SEOPress | `seopress_context_paged`, `seopress_get_tag_page_value`, `seopress_titles_title`, `seopress_social_og_title`, `seopress_social_twitter_card_title`, `seopress_titles_canonical` | `%%page%%` filled on chapters (legacy variables and Tags engine); the chapter title goes after `%%post_title%%` in the title templates (SEOPress filters the template, before its variables); the canonical tag (SEOPress filters the whole tag) is the chapter URL |
| All in One SEO | `aioseo_title`, `aioseo_description`, `aioseo_robots_meta`, `aioseo_prev_link`, `aioseo_next_link`, `aioseo_schema_output` | The chapter title replaces the "Page N" suffix of the paged format, descriptions lose it; chapters get the robots of the post instead of AIOSEO's default "noindex paginated pages" (the settings of the post, its post type or the global ones are read like `Meta\Robots` does, and left alone when unreadable); no AIOSEO rel prev/next; schema WebPage `url` is the chapter URL |
| The SEO Framework | `the_seo_framework_meta_render_data` | No rel prev/next of its own; the chapter title in the Open Graph and Twitter titles (its `<title>` is handled by the common filter) |

SEO plugins receive the plain-text title from `pchap_get_subpage_title_text()`.

The WordPress core sitemap (`wp-sitemap.xml`) lists the URL of each post, never the chapters, like the SEO plugin sitemaps and like posts split with core page breaks. Chapters are reached through the table of contents and the rel links.

## Content Parsing

`PChap_Parser::parse( $content )` (in `inc/pchap-parser.php`) returns:

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
PChap_Parser::parse($content)
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
│   └── a titled page without body gets PChap_Parser::EMPTY_PAGE_PLACEHOLDER
└── Slugs: "slug" attribute, else sanitize_title( title ), else "page-N"; duplicates get -1, -2...
```

Titles come from the block JSON `title` attribute (a RichText string, HTML-encoded) or from the shortcode `title` attribute (raw text, parsed like `shortcode_parse_atts()` but without its rejection of an unclosed `<`). A missing, `false` or empty title gives an empty title.

Related methods:

- `PChap_Parser::build_paginated_content( $pages, $use_block = null )` joins the page contents with `<!--nextpage-->`, or with `PChap_Parser::BLOCK_PAGE_BREAK` (the `core/nextpage` block) when the pages hold block markup, so that the Post Content block still appends the standard page links when they are enabled
- `PChap_Parser::multipage_return_array( $content )` returns `slug => title` (or `false`), derived from `parse()`: the format of the `_mpp_data` meta
- `PChap_Parser::is_meaningful_content( $html )`, filterable with `pchap_parser_is_meaningful_content`
- `multipage_clean_content()` and `parse_nextpage_shortcode()` are kept for backward compatibility

## Subpage Titles

| Format | Used for | `pchap_get_subpage_title_html()` | `pchap_get_subpage_title_text()` |
|--------|----------|-------------------------------|-------------------------------|
| `html` | Block titles, translated fallbacks | `wp_kses()` with the inline allowlist (`pchap_get_subpage_title_allowed_html()`: strong, b, em, i, code, mark, sub, sup, s, span), `force_balance_tags()`, no double encoding | Tags stripped, entities decoded, whitespace collapsed (not escaped) |
| `text` | Titled `[nextpage]` shortcodes | Same, after encoding every `<` that does not start an allowed tag | Same, after the same encoding |

`PChap::get_title_format( $index )` returns the format of a page. The intro is titled "Intro" and untitled pages "Chapter N" (`pchap_untitled_subpage_title`), both translated.

## Data Model

### Post Meta

| Key | Type | Description |
|-----|------|-------------|
| `_mpp_data` | `array` | Subpage index: `slug => title` (`%%intro%%` for the intro, `''` for an untitled page). Written on `save_post` (revisions and autosaves skipped, value slashed) and by the rebuild; deleted when the post is no longer split. Kept for third-party code: the frontend does not read it |

### Settings (wp_options)

Defined in `pchap_get_option_definitions()` (type, default, allowed values, Settings API group) and registered with `register_setting()` by `PChap_Admin::register_admin_settings()`.

| Option | Type | Default | Group | Description |
|--------|------|---------|-------|-------------|
| `mpp-hide-intro-title` | boolean | `false` | `paginova` | Hide the automatic intro title |
| `mpp-comments-on-page` | string | `all` | `paginova` | `all`, `first-page`, `last-page` |
| `mpp-continue-or-prev-next` | string | `continue` | `paginova` | `continue`, `next-previous`, `hidden` |
| `mpp-disable-standard-pagination` | boolean | `true` | `paginova` | Hide the WordPress page links |
| `mpp-toc-only-on-the-first-page` | boolean | `false` | `paginova` | Show the TOC on page 1 only |
| `mpp-toc-position` | string | `top-right` | `paginova` | `top-left`, `top-right`, `top`, `bottom`, `hidden` |
| `mpp-toc-row-labels` | string | `number` | `paginova` | `number`, `page`, `hidden` |
| `mpp-hide-toc-header` | boolean | `false` | `paginova` | Hide the "Contents" header |
| `mpp-comments-toc-link` | boolean | `false` | `paginova` | Comments link in the TOC |
| `_mpp-rewrite-title-priority` | priority | `20` | `paginova-advanced` | Priority of the document title filter (UI: 100, 50, 20, 10, 5; any integer is kept) |
| `_mpp-rewrite-content-priority` | priority | `20` | `paginova-advanced` | Priority of the `the_content` filter |
| `mpp-disable-tinymce-buttons` | boolean | `false` | `paginova-advanced` | Disable the TinyMCE Chapter button |

- Both screens post to `options.php`. `pchap_sanitize_option()` stores booleans as `1`/`0` (an unchecked checkbox is posted as `null`), and an invalid value keeps the current valid value and adds one settings error naming the field
- Getters (`pchap_hide_intro_title()`, `pchap_get_toc_position()`...) read through `pchap_get_option()` (in-memory cache, default applied per call) and `pchap_validate_option_value()`, so they always return a valid value. Legacy stored values (`''`, `'1'`, `'on'`, `'off'`) keep their meaning
- The cache is cleared automatically when a Paginova option is added, updated or deleted; `pchap_clear_options_cache()` is still available
- Third-party fields added to these screens with `add_settings_field()` must also call `register_setting( 'paginova', ... )` (or `'paginova-advanced'`; `post-chapters` / `post-chapters-advanced` before 2.2.0)

### Internal options

| Option | Description |
|--------|-------------|
| `_mpp_db_version` | Database version (`1700` for 1.7.0, `1000` for 1.4–1.6) |
| `_mpp-postmeta-built` | Timestamp of the last complete index rebuild |
| `_mpp-rebuild-state` | Pending background rebuild: cursor (last post ID), counts, start time (not autoloaded) |
| `_mpp-rebuild-last` | Result of the last complete rebuild: time, processed, updated, deleted (not autoloaded) |
| `_mpp-rebuild-lock` | Rebuild lock: a timestamp and an owner token separated by a pipe (written with direct atomic queries) |
| `multipage` | Legacy 1.3 settings, converted then deleted by `pchap_update_to_1_4()` |

The object cache group `paginova` holds the parse results (see `PChap::setup()`).

## Subpage Index Rebuild

`inc/pchap-update.php` rebuilds `_mpp_data` in batches of posts ordered by ID:

- **Candidates**: posts containing `[nextpage` or `wp:multipage/subpage`, or already holding `_mpp_data` (stale entries are deleted); revisions and auto-drafts are skipped. Each batch is one prepared query that selects `ID` and `post_content` directly
- **Batch**: `pchap_process_postmeta_batch( $after_id, $batch_size, $dry_run )`, 200 posts by default (`pchap_rebuild_postmeta_batch_size`). `pchap_run_postmeta_rebuild( $args )` runs batches within an optional time budget, resumes from saved totals and refreshes the lock after each batch
- **Lock**: `pchap_rebuild_acquire_lock()` (atomic `INSERT IGNORE`; a lock not refreshed for 5 minutes is taken over with a compare-and-set `UPDATE`), `pchap_rebuild_refresh_lock()`, `pchap_rebuild_release_lock()` (owner only), `pchap_is_rebuild_locked()`
- **Background (WP-Cron)**: `pchap_schedule_postmeta_rebuild()` saves `_mpp-rebuild-state` and schedules the single event `pchap_rebuild_postmeta_batch`. `pchap_cron_rebuild_postmeta()` takes the lock, schedules a watchdog, processes batches for 15 seconds (`pchap_rebuild_cron_time_limit`), then reschedules itself or completes (`pchap_complete_postmeta_rebuild()`). When locked, it retries a minute later
- **Admin**: the "Rebuild chapter index" form on the Advanced screen posts to `admin-post.php?action=pchap_rebuild_postmeta` (nonce + `manage_options`). `pchap_admin_rebuild_postmeta_handler()` processes batches for 15 seconds (`pchap_rebuild_admin_time_limit`), hands the rest to WP-Cron, and redirects with `mpp-rebuild=done|partial|locked`; the notice is added by `pchap_admin_rebuild_postmeta_notice()`
- **WP-CLI**: `wp paginova rebuild [--batch-size=<n>] [--dry-run]` and `wp paginova status [--format=table|json|yaml|csv]` (`wp post-chapters` and `wp multipage` are aliases) (`PChap_CLI_Command`, `pchap_get_postmeta_index_stats()`)

### Install, upgrade, activation

- `pchap_setup_updater()` runs on `pchap_admin_init` (priority 1000). `pchap_get_installed_db_version()` combines the legacy 1.3 detection with `_mpp_db_version`
- Fresh install: schedules a background rebuild, fires `pchap_on_install`
- Update: `pchap_update_to_1_4()` (below 1000: converts the legacy settings, schedules a rebuild), `pchap_update_to_1_7()` (below 1700: background rebuild) and `pchap_update_to_2_0()` (below 2000: clears the `mpp_rebuild_postmeta_batch` event of Multipage and schedules `pchap_rebuild_postmeta_batch` when a rebuild is pending), then fires `pchap_on_update`; `pchap_version_bump()` stores the new version
- `pchap_activation()` schedules a rebuild (posts edited while the plugin was inactive) and fires `pchap_activation`; `pchap_deactivation()` clears the cron hook and fires `pchap_deactivation`
- `uninstall.php` deletes every option above, the cron hook (and its Multipage name) and all `_mpp_data` meta, on every site of a multisite network. These are the names Multipage and Post Chapters use: deleting any of the three plugins from the Plugins screen erases the data of all of them

## Hooks Reference

### Content and template filters

| Filter | Arguments | Location | Description |
|--------|-----------|----------|-------------|
| `pchap_the_content` | `$output, $page_title, $toc, $content` | `class-pchap.php` | Title + TOC + content (the navigation is appended afterwards) |
| `pchap_page_title_template` | `$template` | `class-pchap.php` | Subpage title template, `%s` = title HTML (default `<h2>%s</h2>`) |
| `pchap_untitled_subpage_title` | `$title, $number, $page` | `class-pchap.php` | Fallback title of an untitled subpage (default "Chapter N") |
| `pchap_is_builder_post` | `$is_builder, $post_id` | `class-pchap.php` | Whether a page builder renders the content of the post, which the plugin then does not split (Elementor, Divi, Beaver Builder, Bricks by their post meta); return false to split it anyway |
| `pchap_speculation_rule` | `$rule` | `class-pchap-speculation.php` | Speculation rule of the chapter links (`source`, `where`, `eagerness`, default `moderate`: on hover); return an empty value for no rule |
| `pchap_speculation_mode` | `$mode` | `class-pchap-speculation.php` | `prefetch` (default) or `prerender` |
| `pchap_seo_integrations` | `$integrations` | `class-pchap-seo.php` | SEO plugin integrations (objects extending `PChap_SEO_Integration`) |
| `pchap_comment_block_names` | `$block_names` | `class-pchap.php` | Blocks hidden on subpages without comments (`core/comments`, `core/post-comments`, `core/post-comments-form`, `core/comments-title`, `core/comments-pagination`, `core/comment-template`, `core/comments-query-loop`) |
| `pchap_subpage_title_allowed_html` | `$allowed` | `pchap-template.php` | Inline tags allowed in subpage titles (`wp_kses()` format) |
| `pchap_link_pages_args` | `$params` | `pchap-template.php` | Navigation arguments |
| `pchap_link_pages_link` | `$link, $page` / `$text, $link` | `pchap-template.php` | One navigation link ("Continue" / "Back to" passes the text and the link) |
| `pchap_link_pages` | `$output, $args` | `pchap-template.php` | Navigation HTML |
| `pchap_toc_args` | `$params` | `pchap-template.php` | TOC arguments |
| `pchap_toc_pages_row` | `$row, $title, $i` | `pchap-template.php` | One TOC row |
| `pchap_toc` | `$output, $args` | `pchap-template.php` | TOC HTML |
| `pchap_parser_is_meaningful_content` | `$meaningful, $html` | `pchap-parser.php` | Whether a fragment counts as content (intro detection, untitled pages) |

### Option filters

| Filter | Location | Description |
|--------|----------|-------------|
| `pchap_get_option_definitions` | `pchap-options.php` | Option definitions (e.g. extra allowed values); core options cannot be removed |
| `pchap_get_option_choice_labels` | `pchap-options.php` | Labels of the choices on the settings screens |
| `pchap_hideintro_title` | `pchap-options.php` | Value of `pchap_hide_intro_title()` |
| `pchap_get_comments_on_page` | `pchap-options.php` | Value of `pchap_get_comments_on_page()` |
| `pchap_get_continue_or_prev_next` | `pchap-options.php` | Value of `pchap_get_continue_or_prev_next()` |
| `pchap_disable_standard_pagination` | `pchap-options.php` | Value of `pchap_disable_standard_pagination()` |
| `pchap_toc_only_on_the_first_page` | `pchap-options.php` | Value of `pchap_toc_only_on_the_first_page()` |
| `pchap_get_toc_position` | `pchap-options.php` | Value of `pchap_get_toc_position()` |
| `pchap_get_toc_row_labels` | `pchap-options.php` | Value of `pchap_get_toc_row_labels()` |
| `pchap_hide_toc_header` | `pchap-options.php` | Value of `pchap_hide_toc_header()` |
| `pchap_comments_toc_link` | `pchap-options.php` | Value of `pchap_comments_toc_link()` |
| `pchap_get_rewrite_title_priority` | `pchap-options.php` | Value of `pchap_get_rewrite_title_priority()` |
| `pchap_get_rewrite_content_priority` | `pchap-options.php` | Value of `pchap_get_rewrite_content_priority()` |
| `pchap_disable_tinymce_buttons` | `pchap-options.php` | Value of `pchap_disable_tinymce_buttons()` |

### Rebuild filters

| Filter | Default | Location | Description |
|--------|---------|----------|-------------|
| `pchap_rebuild_postmeta_batch_size` | `200` | `pchap-update.php` | Posts per rebuild batch |
| `pchap_rebuild_cron_time_limit` | `15` | `pchap-update.php` | Seconds per background (WP-Cron) run |
| `pchap_rebuild_admin_time_limit` | `15` | `admin-advanced-settings.php` | Seconds spent by the "Rebuild chapter index" request before handing over to WP-Cron |

### Admin filters

| Filter | Location | Description |
|--------|----------|-------------|
| `pchap_get_admin_tabs` | `admin-functions.php` | Settings tabs |
| `pchap_admin_tabs` | `admin-functions.php` | Tabs about to be displayed |

### Actions

| Action | Location | Description |
|--------|----------|-------------|
| `pchap_admin_init` | `admin-actions.php` | Fired on `admin_init`; runs the updater (priority 1000), the settings registration and the rebuild notice |
| `pchap_register_admin_settings` | `admin-actions.php` | Settings registration (`PChap_Admin::register_admin_settings()`) |
| `pchap_admin_tabs` | `admin-functions.php` | After the settings tabs |
| `pchap_on_install` | `pchap-update.php` | Fresh install |
| `pchap_on_update` | `pchap-update.php` | Update from an older version |
| `pchap_activation` | `pchap-update.php` | Plugin activation |
| `pchap_deactivation` | `pchap-update.php` | Plugin deactivation |
| `pchap_rebuild_postmeta_batch` | `pchap-update.php` | WP-Cron event of the background rebuild (`pchap_cron_rebuild_postmeta()`) |

## Editors Integration

### Block editor

The "Subpage" block (`multipage/subpage`) lives in `blocks/subpage/` and needs no build step:

- `pchap_register_blocks()` (`inc/pchap-blocks.php`) runs on `init` for every request and calls `register_block_type( PCHAP_PLUGIN_DIR . 'blocks/subpage' )`, then `wp_set_script_translations()` on the editor script (handle `multipage-subpage-editor-script`), without a path; `pchap_load_bundled_script_translation_file()` (`load_script_translation_file`) replaces a JSON file of `WP_LANG_DIR/plugins` with the bundled `languages/*.json` file of the same locale, and returns the bundled file as the last resort when core found none
- `block.json`: `apiVersion` 3 (iframed editor), category `design`, attribute `title` (string, default `false`), supports `html` but no custom class name. `index.js` holds a copy of the metadata so that the block also registers on screens that do not bootstrap the server definitions (keep both in sync)
- Edit: a RichText `h2` without formatting; Enter at the end of the title inserts a paragraph after the block
- Transforms (client only, `save()` is not concerned): `core/heading` and `core/nextpage` become a Chapter block, a Chapter block becomes a `core/heading` (level 2)
- Chapters panel: the same script registers a plugin (`wp.plugins.registerPlugin`, on `wp.domReady`) that renders a `PluginDocumentSettingPanel` (`wp.editor`, `wp.editPost` before WordPress 6.6) in the document sidebar. It lists the Chapter blocks at any depth (click: `selectBlock()` and scroll in the canvas) and has a button that replaces each top-level heading of the chosen level by a Chapter block **where it stands** (`replaceBlock()` one by one, the later changes marked not persistent so one undo restores them all; `replaceBlocks()` with non-adjacent blocks would gather the chapters at the place of the first). The script depends on `wp-plugins`, `wp-data`, `wp-components`, `wp-editor` and `wp-dom-ready` (`index.asset.php`); the Site Editor has no document panel, so nothing shows there
- Save: `[nextpage title="..."]` (only `"` encoded), byte-identical to every previous version, inside the block comment: `<!-- wp:multipage/subpage {"title":"..."} -->[nextpage title="..."]<!-- /wp:multipage/subpage -->`. The parser also honors an optional `slug` attribute

### Classic editor (TinyMCE and Quicktags)

- Unless `mpp-disable-tinymce-buttons` is set: `mce_buttons`, `mce_external_plugins` (`inc/admin/tinymce/js/plugin[.min].js`), `mce_css`, `wp_mce_translation`, and `tiny_mce_before_init` (`PChap_Admin::pchap_classic_block_mce_css()`, which adds the placeholder styles to the Classic block of the block editor)
- The dialog escapes the title (`&`, `"`, `<`, `>`, `[`, `]`) and inserts nothing for an empty title. In the Visual editor, shortcodes are shown as placeholder images that store the exact shortcode value (`data-mpp-title`), so the Visual/Text round trip is lossless
- Text editor: `PChap_Admin::editor_add_quicktags()` attaches an inline script to the core `quicktags` script (button `eg_subpage`), with the same escaping

### Shortcode

`[nextpage title="My Title" slug="my-slug"]` is registered by `PChap_Shortcodes`. On multipage views the shortcodes are removed before rendering; where the content is not split (feeds, REST API, excerpts) the handler returns an empty string.

## CSS Loading

`PChap::enqueue_styles()` runs on multipage views only. It looks for `css/paginova[-rtl][.min].css` (`-rtl` on RTL sites, `.min` unless `SCRIPT_DEBUG`) in this order:

1. Child theme: `css/paginova*.css`, then the Post Chapters 2.x name `css/post-chapters*.css`, then the Multipage name `css/multipage*.css`
2. Parent theme: same three names
3. Plugin default (falls back to the LTR file if the RTL one is missing)

The handle is `{theme}-paginova` for a theme file, `paginova` for the plugin file.

The styles use `rem` and reset the typography of `.mpp-toc-container` and `.mpp-post-navigation`. In the RTL file, the `top-left` / `top-right` TOC positions keep their physical side. The admin stylesheet is `admin/css/paginova-admin[-rtl][.min].css` (handle `paginova-admin`).

## Translations

- Text domain `paginova`. The code that loads the bundled files of `/languages` (`pchap_load_textdomain()`, `pchap_prefer_bundled_translations()`, `pchap_get_bundled_translation_file()`, `pchap_load_bundled_textdomain_mofile()`, `pchap_load_bundled_script_translation_file()`) lives in `inc/pchap-l10n.php`, required only when the file exists. The GitHub build ships it with `/languages`; the WordPress.org build (`bin/build-zip.sh --wporg`) leaves it out with every file of `languages/` except `paginova.pot`, and WordPress then loads the translate.wordpress.org language packs just in time
- `pchap_load_textdomain()` (`init`) calls `load_plugin_textdomain( 'paginova', false, '<plugin folder>/languages' )`. WordPress resolves `WP_LANG_DIR/plugins` (language packs) first and the bundled files only as a fallback; WordPress 6.5+ reads the `.l10n.php` file before the `.mo`
- A language pack can lag behind the strings of a release (and, under the old `sgr-nextpage-titles` slug, the packs belonged to the original plugin). `pchap_load_bundled_textdomain_mofile()` (`load_textdomain_mofile`, applied by `load_textdomain()` before it derives the `.l10n.php` path) replaces a `.mo` path located directly in `WP_LANG_DIR/plugins` with the bundled file of the same locale when it exists, so the bundled `.l10n.php`/`.mo` are loaded. This also covers a pack deleted while still listed in the registry file cache (`translation_files` group, one hour). Other locations (e.g. Loco Translate) and locales without a bundled file are untouched; the `pchap_prefer_bundled_translations` filter turns the replacement off
- Block strings come from `block.json` (translated in PHP, contexts "block title", "block description", "block keyword") and from `index.js` (JSON file)
- See [CONTRIBUTING.md](CONTRIBUTING.md#translations) for the regeneration workflow and `bin/check-i18n.sh`

## Deprecated API

| Deprecated | Since | Replacement |
|------------|-------|-------------|
| `PChap::multipage_return_array()` | 1.5.14 | `PChap_Parser::multipage_return_array()` (triggers `_deprecated_function()` since 1.7.0) |
| `PChap::pchap_the_title()` | 1.7.0 | No longer hooked; returns the title unchanged (see the SEO integrations) |
| `PChap::pchap_rel_links()`, `pchap_document_title_parts()`, `filter_wpseo_*()`, `filter_rank_math_replacements()`, `filter_seopress_*()`, `filter_seo_adjacent_rel_url()`, `filter_disable_seo_adjacent_rel_links()` | 2.1.0 | The same methods of `pchap()->seo` (`print_rel_links()`, `filter_document_title_parts()`...); the old ones warn and delegate |
| `PChap::comments_allowed_on_current_page()`, `hide_comments()`, `filter_comment_blocks()` | 2.1.0 | `pchap()->comments->allowed_on_current_page()`, `filter_comments_template()`, `filter_comment_blocks()` |
| `pchap_admin_settings_callback_*()` field callbacks, `pchap_admin_advanced_callback_{rewrite_title_priority,rewrite_content_priority,disable_tinymce_buttons}()` | 2.1.0 | `pchap_admin_render_option_field()`, driven by `pchap_get_settings_fields()` |
| `PChap_Admin::pchap_enqueue_block_editor_assets()` | 1.7.0 | `pchap_register_blocks()` |
| `pchap_admin_settings_save()`, `pchap_admin_advanced_settings_save()` | 1.7.0 | Settings API (`options.php`); no-ops |
| `pchap_admin_advanced_callback_build_mpp_postmeta_data()` | 1.7.0 | `pchap_admin_rebuild_postmeta_section()` |
| `inc/admin/tinymce/languages.php` | 1.5 | `PChap_Admin::pchap_wp_mce_translation()` (the file, never loaded, was deleted in 2.2.0) |
| `PCHAP_PATTERN`, `PCHAP_GUTENBERG_PATTERN` | 1.7.0 | `PChap_Parser::parse()`. The constants are kept for backward compatibility (`PCHAP_PATTERN` is only used by the legacy `PChap_Parser::parse_nextpage_shortcode()`) |

## Tests and Tooling

- PHPUnit 9.6 integration tests on the WordPress test library (`tests/`), run inside wp-env (`composer test:wp-env`). `PChap_UnitTestCase` resets the `PChap` singleton between simulated requests and provides helpers such as `go_to_subpage()` and `render_subpage()`; `PChap_Test_Corpus` holds real-world contents with their expected pages
- `phpcs.xml.dist`: WordPress + PHPCompatibilityWP, PHP 7.4+, WordPress 6.3+, text domain `paginova`, prefixes `pchap` and `pchap_`
- CI (`.github/workflows/ci.yml`): PHP lint 7.4–8.4, PHPCS, i18n check, PHPUnit on WordPress 6.3 / PHP 7.4, 7.1.4 / PHP 7.4 and 7.1.4 / PHP 8.4, release zip

See [CONTRIBUTING.md](CONTRIBUTING.md) for the commands.
