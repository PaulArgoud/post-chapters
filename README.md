# Multipage for WordPress

[![WordPress Plugin Version](https://img.shields.io/badge/version-1.7.1-blue.svg)](https://github.com/PaulArgoud/sgr-nextpage-titles/releases/tag/1.7.1)
[![CI](https://github.com/PaulArgoud/sgr-nextpage-titles/actions/workflows/ci.yml/badge.svg)](https://github.com/PaulArgoud/sgr-nextpage-titles/actions/workflows/ci.yml)
[![License: GPL v3](https://img.shields.io/badge/license-GPLv3-green.svg)](https://www.gnu.org/licenses/gpl-3.0)
[![WordPress](https://img.shields.io/badge/WordPress-6.3%2B-0073aa.svg)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-7.4%E2%80%938.4-777BB4.svg)](https://www.php.net/)
[![Tested up to](https://img.shields.io/badge/tested%20up%20to-WordPress%207.1.4-0073aa.svg)](https://wordpress.org/)

Split your WordPress posts into multiple subpages, each with its own title and a table of contents.

## Features

- **Subpage block** for the block editor (works in the iframed editor; posts saved by earlier versions stay valid)
- **`[nextpage title="..."]` shortcode** for the classic editor, with a **Subpage** button in the Visual editor (TinyMCE), the Text editor (Quicktags) and the Classic block
- **Reliable splitting**: blocks, shortcodes and core page breaks can be mixed; every subpage is valid, balanced HTML, even when a shortcode sits inside a paragraph or a heading
- **Table of contents** with configurable position, labels, header visibility and an optional comments link
- **Navigation**: "Continue / Back to" or "Previous / Next" links between subpages
- **Comments** on all subpages, the first one or the last one, in classic and block themes
- **SEO-friendly**: subpage titles in `<title>`, `rel=prev` / `rel=next` links, integrations with Yoast SEO (title variable, canonical URL of each subpage), Rank Math and SEOPress (title variable)
- **Block and classic themes**: the table of contents and the navigation are added once, only to the post being viewed (never to excerpts, related posts or Query Loop blocks)
- **Custom CSS**: override the plugin styles from your theme
- **RTL support** (frontend and admin)
- **Internationalized**: French, German, Italian and Spanish included
- **Subpage index tools**: a "Rebuild subpage index" button and the `wp multipage` WP-CLI commands

## Requirements

| Requirement | Minimum | Tested up to |
|-------------|---------|--------------|
| WordPress   | 6.3     | 7.1.4        |
| PHP         | 7.4     | 8.4          |

## Installation

### From GitHub

1. Download `sgr-nextpage-titles.zip` from the [latest release](https://github.com/PaulArgoud/sgr-nextpage-titles/releases/latest) (use this archive, not GitHub's "Source code" archives, which contain the development files and a differently named folder)
2. In WordPress, go to **Plugins > Add New > Upload Plugin** and upload the zip, or unzip it so that the plugin sits in `/wp-content/plugins/sgr-nextpage-titles/`
3. Activate **Multipage** through the **Plugins** menu in WordPress
4. Configure the plugin under **Settings > Multipage**

### WordPress.org

The [WordPress.org listing](https://wordpress.org/plugins/sgr-nextpage-titles/) is still the original author's version 1.5.12 (2021), so searching for **Multipage** in **Plugins > Add New** installs that older version, not 1.7.1. Install from the GitHub release above.

The GitHub version declares `Update URI: https://github.com/PaulArgoud/sgr-nextpage-titles` in its header, so WordPress never replaces it with the WordPress.org version of the same slug. Updates come from new GitHub releases: download the new zip and upload it again (WordPress offers to replace the installed version).

### Updating to 1.7.0

No content change is needed. After the update, the subpage index of every post is rebuilt in the background (WP-Cron). On large sites you can also run `wp multipage rebuild`, or click **Rebuild subpage index** under **Settings > Multipage > Advanced**. See the [changelog](CHANGELOG.md) for the behaviour changes.

## Usage

### Block editor

Add a **Subpage** block where a new subpage starts and type its title. Press Enter at the end of the title to continue writing below it.

### Classic editor

Use the **Subpage** button of the toolbar, or insert the shortcode anywhere in your post content:

```
[nextpage title="My subpage title"]
```

An optional `slug` attribute sets the subpage slug: `[nextpage title="My subpage title" slug="my-slug"]`.

The content before the first subpage becomes the intro page (titled "Intro", translated). A subpage without a title is titled "Page N".

## Settings

Navigate to **Settings > Multipage** to configure:

| Setting | Description |
|---------|-------------|
| **Intro** | Hide the title of the automatic intro page |
| **Display comments on** | All pages, the first page only, or the last page only |
| **Navigation type** | "Continue or back to intro", "Next and previous", or hidden |
| **Disable the standard pagination** | Hide the WordPress page numbers (`wp_link_pages()`) |
| **Table of contents** | Only on the first page, position (top left, top right, top, bottom, hidden), row labels, header visibility |
| **Comments link** | Add a link to the comments inside the table of contents |

The **Advanced** tab holds the title and content rewrite priorities, the option to disable the TinyMCE Subpage button, and the **Rebuild subpage index** action.

## Custom CSS

Multipage loads a minimal stylesheet on multipage posts only. To override it, add one of these files to your theme:

- `css/multipage.min.css` (or `css/multipage.css` when `SCRIPT_DEBUG` is enabled)
- `css/multipage-rtl.min.css` / `css/multipage-rtl.css` for RTL languages

The plugin checks the child theme first, then the parent theme, then falls back to its own styles.

## Filters & Hooks

| Filter | Description |
|--------|-------------|
| `mpp_the_content` | Modify the multipage content output (title + table of contents + content) |
| `mpp_page_title_template` | Change the subpage title HTML template (default: `<h2>%s</h2>`) |
| `mpp_link_pages` / `mpp_link_pages_args` / `mpp_link_pages_link` | Navigation HTML, arguments and individual links |
| `mpp_toc` / `mpp_toc_args` / `mpp_toc_pages_row` | Table of contents HTML, arguments and individual rows |
| `mpp_untitled_subpage_title` | Fallback title of untitled subpages (default: "Page N") |
| `mpp_subpage_title_allowed_html` | Inline HTML allowed in subpage titles |
| `mpp_comment_block_names` | Comment blocks hidden on subpages without comments (block themes) |
| `mpp_get_admin_tabs` | Add or remove admin settings tabs |

See [ARCHITECTURE.md](ARCHITECTURE.md) for the complete hooks reference.

## WP-CLI

```bash
wp multipage status                 # State of the subpage index and of the background rebuild
wp multipage rebuild                # Rebuild the subpage index of every post
wp multipage rebuild --dry-run      # Report what would change, without writing
```

## Languages

Translation files are located in `/languages/`. Available languages:

- English (default)
- French (fr_FR)
- German (de_DE)
- Italian (it_IT)
- Spanish (es_ES)

For these four languages the bundled files are always used, even when a language pack is installed: the packs that [translate.wordpress.org](https://translate.wordpress.org/projects/wp-plugins/sgr-nextpage-titles/) delivers for the `sgr-nextpage-titles` slug translate the original plugin and lack the strings of this version. Other languages use the installed language pack, if any. The `mpp_prefer_bundled_translations` filter (`add_filter( 'mpp_prefer_bundled_translations', '__return_false' );`) gives the language packs precedence back. To contribute a translation, see [CONTRIBUTING.md](CONTRIBUTING.md#translations).

## Development

The plugin itself needs no build step. The development tools are installed with Composer and the tests run in [wp-env](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/) (Docker and Node.js 22.13+ required). Clone the repository into a folder named `sgr-nextpage-titles`.

```bash
composer install          # PHPCS, WPCS, PHPCompatibilityWP, PHPUnit (dev only)
composer lint             # php -l on every PHP file
composer phpcs            # WordPress Coding Standards (composer phpcbf to fix)
npx wp-env start          # WordPress 7.1.4 / PHP 8.4 at http://localhost:8888
composer test:wp-env      # PHPUnit suite inside wp-env
bin/check-i18n.sh         # Translation files in sync with the code (WP-CLI + gettext)
bin/build-zip.sh          # Release archive: build/sgr-nextpage-titles.zip
```

`composer test` runs PHPUnit directly when the WordPress test library is available (`WP_TESTS_DIR`). See [CONTRIBUTING.md](CONTRIBUTING.md) for the full workflow and [ARCHITECTURE.md](ARCHITECTURE.md) for the internals.

## History

This plugin was originally created by Sergio De Falco (SGr33n). The [original codebase up to version 1.3.1](https://github.com/wp-plugins/sgr-nextpage-titles) served as the foundation for this fork. From there, the plugin was maintained and extended through [version 1.5.9 on the WordPress.org SVN repository](https://plugins.svn.wordpress.org/sgr-nextpage-titles/tags/). Starting from [version 1.5.12 published on WordPress.org](https://wordpress.org/plugins/sgr-nextpage-titles/), Paul Argoud took over active development, bringing the plugin up to modern WordPress and PHP standards with continued improvements.

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for a detailed history of changes.

## License

This plugin is licensed under the [GNU General Public License v3.0](https://www.gnu.org/licenses/gpl-3.0.html).
