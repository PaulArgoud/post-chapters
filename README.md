# Post Chapters for WordPress

[![Version](https://img.shields.io/badge/version-2.0.0-blue.svg)](https://github.com/PaulArgoud/post-chapters/releases/tag/2.0.0)
[![CI](https://github.com/PaulArgoud/post-chapters/actions/workflows/ci.yml/badge.svg)](https://github.com/PaulArgoud/post-chapters/actions/workflows/ci.yml)
[![License: GPL v3](https://img.shields.io/badge/license-GPLv3-green.svg)](https://www.gnu.org/licenses/gpl-3.0)
[![WordPress](https://img.shields.io/badge/WordPress-6.3%2B-0073aa.svg)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-7.4%E2%80%938.4-777BB4.svg)](https://www.php.net/)
[![Tested up to](https://img.shields.io/badge/tested%20up%20to-WordPress%207.1.4-0073aa.svg)](https://wordpress.org/)

Split your long WordPress posts into chapters, each on its own page with its own title, and add an automatic table of contents.

Post Chapters is the continuation of **Multipage** (`sgr-nextpage-titles`), originally created by Sergio De Falco. It keeps the content, the settings and the shortcode of Multipage: see [Coming from Multipage](#coming-from-multipage).

## Features

- **Chapter block** for the block editor (works in the iframed editor; posts saved by Multipage stay valid)
- **`[nextpage title="..."]` shortcode** for the classic editor, with a **Chapter** button in the Visual editor (TinyMCE), the Text editor (Quicktags) and the Classic block
- **Reliable splitting**: blocks, shortcodes and core page breaks can be mixed; every chapter is valid, balanced HTML, even when a shortcode sits inside a paragraph or a heading
- **Table of contents** with configurable position, labels, header visibility and an optional comments link
- **Navigation**: "Continue / Back to" or "Previous / Next" links between chapters
- **Comments** on all chapters, the first one or the last one, in classic and block themes
- **SEO-friendly**: chapter titles in `<title>`, `rel=prev` / `rel=next` links, integrations with Yoast SEO (title variable, canonical URL of each chapter), Rank Math and SEOPress (title variable)
- **Block and classic themes**: the table of contents and the navigation are added once, only to the post being viewed (never to excerpts, related posts or Query Loop blocks)
- **Custom CSS**: override the plugin styles from your theme
- **RTL support** (frontend and admin)
- **Internationalized**: French, German, Italian and Spanish included
- **Chapter index tools**: a "Rebuild chapter index" button and the `wp post-chapters` WP-CLI commands

## Requirements

| Requirement | Minimum | Tested up to |
|-------------|---------|--------------|
| WordPress   | 6.3     | 7.1.4        |
| PHP         | 7.4     | 8.4          |

## Installation

1. Download `post-chapters.zip` from the [latest release](https://github.com/PaulArgoud/post-chapters/releases/latest) (use this archive, not GitHub's "Source code" archives, which contain the development files and a differently named folder)
2. In WordPress, go to **Plugins > Add New > Upload Plugin** and upload the zip, or unzip it so that the plugin sits in `/wp-content/plugins/post-chapters/`
3. Activate **Post Chapters** through the **Plugins** menu
4. Configure it under **Settings > Post Chapters**

Post Chapters is not on WordPress.org yet. Its header declares `Update URI: https://github.com/PaulArgoud/post-chapters`, so WordPress never replaces it with a plugin of the same slug from WordPress.org. To update, download the new zip and upload it again (WordPress offers to replace the installed version).

## Coming from Multipage

Post Chapters reads the same content and settings as Multipage (1.5.12 from WordPress.org, or the 1.5.13 to 1.7.1 releases of this repository): the `[nextpage]` shortcodes, the Subpage blocks (`multipage/subpage`), the options and the subpage index (`_mpp_data`) are reused as they are.

1. Install and activate Post Chapters. While Multipage is active, Post Chapters stays idle and shows a notice
2. **Deactivate** Multipage. Post Chapters takes over on the next page load and moves a pending index rebuild to its own background task
3. Remove Multipage by **deleting its folder** (`wp-content/plugins/sgr-nextpage-titles/`) over FTP or SSH. Do not use **Delete** on the Plugins screen: Multipage 1.5.15 and later then run their uninstall routine, which erases the settings and the index that Post Chapters uses

What changes for visitors and editors:

- The block is called **Chapter** in the inserter (still `multipage/subpage` in the content), and the editor buttons say **Chapter**
- An untitled page is titled "Chapter N" instead of "Page N", and the "page #" row labels of the table of contents read "Chapter #"
- The settings pages moved to **Settings > Post Chapters** (`options-general.php?page=post-chapters`)
- The `wp multipage` WP-CLI command is now `wp post-chapters` (`wp multipage` still works)

What changes for developers (see the [changelog](CHANGELOG.md#200)):

- Functions, classes and constants use the `pchap` prefix: `mpp_toc()` is `pchap_toc()`, `Multipage` is `PChap`, `multipage()` is `pchap()`, `MPP_VERSION` is `PCHAP_VERSION`. The old names are gone (they would collide with Multipage)
- Hooks use the `pchap_` prefix. Callbacks attached to the old `mpp_*` names still run, as deprecated hooks
- Text domain `post-chapters`; theme stylesheet `css/post-chapters.css` (`css/multipage.css` still works)

## Usage

### Block editor

Add a **Chapter** block where a new chapter starts and type its title. Press Enter at the end of the title to continue writing below it.

### Classic editor

Use the **Chapter** button of the toolbar, or insert the shortcode anywhere in your post content:

```
[nextpage title="My chapter title"]
```

An optional `slug` attribute sets the chapter slug: `[nextpage title="My chapter title" slug="my-slug"]`.

The content before the first chapter becomes the intro page (titled "Intro", translated). A chapter without a title is titled "Chapter N".

## Settings

Navigate to **Settings > Post Chapters** to configure:

| Setting | Description |
|---------|-------------|
| **Intro** | Hide the title of the automatic intro page |
| **Display comments on** | All pages, the first page only, or the last page only |
| **Navigation type** | "Continue or back to intro", "Next and previous", or hidden |
| **Disable the standard pagination** | Hide the WordPress page numbers (`wp_link_pages()`) |
| **Table of contents** | Only on the first page, position (top left, top right, top, bottom, hidden), row labels, header visibility |
| **Comments link** | Add a link to the comments inside the table of contents |

The **Advanced** tab holds the title and content rewrite priorities, the option to disable the TinyMCE Chapter button, and the **Rebuild chapter index** action.

## Custom CSS

Post Chapters loads a minimal stylesheet on split posts only. To override it, add one of these files to your theme:

- `css/post-chapters.min.css` (or `css/post-chapters.css` when `SCRIPT_DEBUG` is enabled)
- `css/post-chapters-rtl.min.css` / `css/post-chapters-rtl.css` for RTL languages

The plugin checks the child theme first, then the parent theme, then falls back to its own styles. In each theme, the `css/multipage*.css` names of Multipage are still recognized. The HTML classes (`.mpp-toc-container`, `.mpp-post-navigation`...) did not change.

## Filters & Hooks

| Filter | Description |
|--------|-------------|
| `pchap_the_content` | Modify the content output (title + table of contents + content) |
| `pchap_page_title_template` | Change the chapter title HTML template (default: `<h2>%s</h2>`) |
| `pchap_link_pages` / `pchap_link_pages_args` / `pchap_link_pages_link` | Navigation HTML, arguments and individual links |
| `pchap_toc` / `pchap_toc_args` / `pchap_toc_pages_row` | Table of contents HTML, arguments and individual rows |
| `pchap_untitled_subpage_title` | Fallback title of untitled chapters (default: "Chapter N") |
| `pchap_subpage_title_allowed_html` | Inline HTML allowed in chapter titles |
| `pchap_comment_block_names` | Comment blocks hidden on chapters without comments (block themes) |
| `pchap_get_admin_tabs` | Add or remove admin settings tabs |

Every hook was called `mpp_*` in Multipage; the old names still work but are deprecated. See [ARCHITECTURE.md](ARCHITECTURE.md) for the complete hooks reference.

## WP-CLI

```bash
wp post-chapters status                 # State of the chapter index and of the background rebuild
wp post-chapters rebuild                # Rebuild the chapter index of every post
wp post-chapters rebuild --dry-run      # Report what would change, without writing
```

## Languages

Translation files are located in `/languages/`. Available languages:

- English (default)
- French (fr_FR)
- German (de_DE)
- Italian (it_IT)
- Spanish (es_ES)

For these four languages the bundled files are used even when a language pack is installed in `wp-content/languages/plugins/`, since a pack can lag behind the strings of a release. Other languages use the installed language pack, if any. The `pchap_prefer_bundled_translations` filter (`add_filter( 'pchap_prefer_bundled_translations', '__return_false' );`) gives the language packs precedence. To contribute a translation, see [CONTRIBUTING.md](CONTRIBUTING.md#translations).

## Development

The plugin itself needs no build step. The development tools are installed with Composer and the tests run in [wp-env](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/) (Docker and Node.js 22.13+ required). Clone the repository into a folder named `post-chapters`.

```bash
composer install          # PHPCS, WPCS, PHPCompatibilityWP, PHPUnit (dev only)
composer lint             # php -l on every PHP file
composer phpcs            # WordPress Coding Standards (composer phpcbf to fix)
npx @wordpress/env@12 start                               # WordPress 7.1.4 / PHP 8.4 at http://localhost:8888
npx @wordpress/env@12 start --config=.wp-env.test.json    # Test environment at http://localhost:8889
composer test:wp-env      # PHPUnit suite inside the test environment
bin/check-i18n.sh         # Translation files in sync with the code (WP-CLI + gettext)
bin/build-zip.sh          # Release archive: build/post-chapters.zip
```

`composer test` runs PHPUnit directly when the WordPress test library is available (`WP_TESTS_DIR`). See [CONTRIBUTING.md](CONTRIBUTING.md) for the full workflow and [ARCHITECTURE.md](ARCHITECTURE.md) for the internals.

## History

Post Chapters started as **Multipage** (`sgr-nextpage-titles`), created by Sergio De Falco (SGr33n). The [original code up to version 1.3.1](https://github.com/wp-plugins/sgr-nextpage-titles) and the releases up to [1.5.12 on WordPress.org](https://wordpress.org/plugins/sgr-nextpage-titles/) (2021) are his work. Paul Argoud then took over development on GitHub (1.5.13 to 1.7.1), bringing the plugin up to modern WordPress and PHP standards, and renamed it Post Chapters in 2.0.0. The WordPress.org listing of Multipage remains in the hands of its original author.

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for a detailed history of changes.

## License

This plugin is licensed under the [GNU General Public License v3.0](https://www.gnu.org/licenses/gpl-3.0.html). It is based on Multipage, © Sergio De Falco, also released under the GPL v3.
