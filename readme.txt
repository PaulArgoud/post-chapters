=== Paginova – Chapters for Long Posts ===
Contributors: nightangel
Tags: chapters, pagination, nextpage, page break, long posts
Requires at least: 6.3
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 2.3.0
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Split long posts into chapters, each on its own page with its own URL and title, plus a table of contents and navigation. Block and classic editors.

== Description ==

Paginova splits a long post into chapters. Each chapter is a page of its own, with its own URL and title, and a table of contents links them together, so readers find the section they want and every chapter counts as a page view.

A table of contents plugin links to the sections of a single long page; Paginova builds on the page breaks of WordPress (`<!--nextpage-->`) and turns them into titled chapters that search engines, SEO plugins and comments handle as pages of their own. Formerly named Post Chapters, it is the successor of Multipage and takes over its content and settings.

= Features =

* **Chapter block** for the block editor. It works in the iframed editor of recent WordPress versions. A heading or a page break turns into a chapter with the block switcher.
* **Chapters panel** in the editor sidebar: the chapters of the post (click to go to one), and a button that turns all the H2, H3 or H4 headings of a post into chapters, in one undoable step.
* **`[nextpage title="…"]` shortcode** for the classic editor, with a **Chapter** button in the Visual editor, the Text editor and the Classic block.
* **Reliable splitting**: Chapter blocks, shortcodes and core page breaks can be mixed. Every chapter is balanced HTML, even when a shortcode sits inside a paragraph or a heading, so the table of contents can never end up inside a heading.
* **Table of contents** with a configurable position (top left, top right, top, bottom or hidden), row labels, header and an optional link to the comments.
* **Navigation** between chapters: "Continue / Back to" or "Previous / Next" links.
* **Comments** on all chapters, on the first one only or on the last one only, in classic and block themes.
* **SEO-friendly**: the chapter title appears in the browser title, `rel=prev` / `rel=next` links are added, and Yoast SEO, Rank Math, SEOPress, All in One SEO and The SEO Framework show the chapter title, canonical URL and robots of each chapter instead of "Page X of Y".
* **Tested with** Yoast SEO, Rank Math, SEOPress, All in One SEO, The SEO Framework, Redis Object Cache, the WordPress core sitemap and custom post types (see the FAQ for versions).
* **Block and classic themes**: the table of contents and the navigation are added once, only to the post being read, never to excerpts, related posts or Query Loop blocks.
* **Your own styles**: the plugin loads a minimal stylesheet that your theme can replace.
* **RTL support** and translations in French, German, Italian and Spanish.
* **Developer friendly**: filters for every piece of output, WP-CLI commands (`wp paginova status`, `wp paginova rebuild`).

= Usage =

In the block editor, add a **Chapter** block where a new chapter starts and type its title. To split a post that already has headings, open the **Chapters** panel in the sidebar and click **Turn headings into chapters**.

In the classic editor, click the **Chapter** button, or type the shortcode where a new chapter starts:

`[nextpage title="My chapter title"]`

The content before the first chapter becomes the intro page. A chapter without a title is titled "Chapter N".

= Credits =

Paginova is the continuation of Multipage, created by Sergio De Falco (SGr33n, Envire Web Solutions), who developed it up to version 1.5.12. Paul Argoud ([paul.argoud.net](https://paul.argoud.net/)) maintained it from 1.5.13, renamed it Post Chapters in 2.0.0 and Paginova in 2.2.0. Development happens on [GitHub](https://github.com/PaulArgoud/paginova).

== Installation ==

1. Upload the `paginova` folder to `/wp-content/plugins/`, or upload the zip under **Plugins > Add New > Upload Plugin**.
2. Activate **Paginova** in the **Plugins** menu.
3. Configure the plugin under **Settings > Paginova**.
4. Add Chapter blocks or `[nextpage title="…"]` shortcodes to your posts.

== Frequently Asked Questions ==

= I use Multipage. How do I switch? =

Paginova reuses the content, the settings and the index of Multipage. Activate Paginova (it stays idle and shows a notice while Multipage is active), then deactivate Multipage. To remove Multipage, delete its `sgr-nextpage-titles` folder over FTP or SSH rather than with **Delete** on the Plugins screen: Multipage 1.5.15 and later erase, when deleted from the Plugins screen, the settings and the index that Paginova uses.

= I use Post Chapters. How do I switch? =

Paginova is the new name of Post Chapters, and reuses its content and settings. Activate Paginova (it stays idle and shows a notice while Post Chapters is active), then deactivate Post Chapters. To remove Post Chapters, delete its `post-chapters` folder over FTP or SSH rather than with **Delete** on the Plugins screen, which erases the settings and the index that Paginova uses.

= Do I need to change my existing posts? =

No. Existing Subpage blocks (now called Chapter blocks) and `[nextpage]` shortcodes keep working.

= How do I customize the look of the table of contents? =

Copy `css/paginova.css` from the plugin into the `css/` folder of your theme (or child theme) and edit it there. Use the name `paginova.min.css`, unless `SCRIPT_DEBUG` is enabled, and `paginova-rtl.min.css` for right-to-left languages. Paginova looks in the child theme first, then in the parent theme, then uses its own stylesheet; the `post-chapters*.css` names of Post Chapters and the `multipage*.css` names of Multipage are still recognized. You can also override the `.mpp-toc-container` and `.mpp-post-navigation` classes in your theme stylesheet.

= Can I put the shortcode inside a heading or a paragraph? =

Yes. The elements open around the shortcode are closed at the end of the chapter and reopened on the next one, and elements left empty by the cut are removed.

= Does it work with SEO plugins? =

Yes: Yoast SEO, Rank Math, SEOPress, All in One SEO and The SEO Framework. On chapters 2 and up, the title carries the chapter title (even when your title template has no page variable), the canonical URL and the Open Graph URL are the chapter's, and a chapter is not set to noindex as a "paginated page" (All in One SEO does that by default). The plugin also works with the WordPress core sitemap, which lists the post URLs.

= Which plugins is it tested with? =

On WordPress 7.1.4 with Twenty Twenty-Five, in November 2026: Yoast SEO 28.8, Rank Math 1.0.281, SEOPress 10.4, All in One SEO 5.0.5, The SEO Framework 5.2.0 and Redis Object Cache 3.0.0 (with Redis 7). With each SEO plugin, a chapter has its own title, canonical URL and Open Graph URL, one pair of `rel=prev` / `rel=next` links, and is not set to `noindex` as a "paginated page". The WordPress core sitemap works as before. Custom post types, flat or hierarchical, work like posts. Elementor 4.4.0 works with its Canvas and Full Width templates for posts written in the block or classic editor; posts built with the Elementor editor are not split (see the next question). Divi, Beaver Builder, Bricks, Elementor Pro theme templates and e-commerce templates that render the post content themselves are not tested. If a plugin misbehaves with a chapter, please open an issue on [GitHub](https://github.com/PaulArgoud/paginova/issues).

= Does it work with custom post types? =

Yes, with any public post type that supports the editor: chapters work as they do for posts, with the URL base of the post type. The Chapter block needs the block editor (`show_in_rest`); in the classic editor, use the **Chapter** button or the shortcode.

= Does it work with page builders? =

A page builder such as Elementor replaces the content of the posts it builds with its own layout, on every page, so the chapters of such a post would all show the whole post. Paginova therefore leaves posts built with Elementor, Divi, Beaver Builder or Bricks alone: write the chapters in the block editor or the classic editor. Only Elementor is tested; the others are recognized by their post meta. A post written in the block or classic editor and displayed with a builder template (for example Elementor's Canvas) is split as usual.

= Is it fast? =

With WordPress 6.8 or later, the next chapter is prefetched when a visitor hovers a navigation or table of contents link, so turning the page is almost instant (not for logged in users, as for all speculative loading in WordPress). The stylesheet is printed inline, one request less. On a post without chapters, the plugin only looks for a chapter marker. A split post is parsed once and the result is kept in the object cache (tested with Redis), and the stylesheet is only loaded on split posts.

= What happens with a chapter number that does not exist? =

WordPress redirects the visitor to the first page of the post, as it does for posts split with the standard page break.

== Screenshots ==

1. The settings page.
2. The block editor with a Chapter block.
3. A post split into chapters, with its table of contents.
4. The Chapters panel of the editor: the chapters of the post, and the button that turns its headings into chapters.

== Changelog ==

= 2.3.0 =
Release Date: November 14th, 2026

Existing content needs no change; the settings are kept.

* The settings and the chapter index are stored under Paginova names (`pchap-*` options, `_pchap_data` meta), moved automatically from the `mpp-*` names of Multipage and Post Chapters.
* `[nextpage]` is no longer registered as a shortcode: chapter markers are removed from feeds, excerpts and the REST API by content filters.
* Fixed: the **Rebuild chapter index** button did nothing.
* Uninstalling Paginova no longer removes the data of Multipage or Post Chapters.

= 2.2.0 =
Release Date: November 13th, 2026

Post Chapters becomes Paginova. Existing content and settings need no change.

* New name, slug (`paginova`) and text domain; settings under **Settings > Paginova**; the WP-CLI command is `wp paginova` (`wp post-chapters` and `wp multipage` still work).
* Paginova stays idle, with a notice, while Post Chapters 2.x is active.
* A theme stylesheet named `css/paginova.css` comes first; the `post-chapters*.css` and `multipage*.css` names still work.
* The filtered HTML of the chapter title, the table of contents and the navigation is escaped with `wp_kses_post()`.

= 2.1.0 =
Release Date: November 9th, 2026

* SEO: chapters are now handled correctly by Yoast SEO, Rank Math, SEOPress, All in One SEO and The SEO Framework, checked on the real plugins. All in One SEO no longer sets chapters to noindex, SEOPress and Yoast give each chapter its own canonical and Open Graph URL, and every chapter has its own title in every plugin.
* New: with WordPress 6.8 or later, the next chapter is prefetched when a visitor hovers a link, so turning the page is almost instant.
* New: a Chapters panel in the editor sidebar lists the chapters and turns the headings of a post into chapters in one undoable step; headings and page breaks can be switched to Chapter blocks.
* Fixed: deleting the plugin now leaves nothing behind, including entries of a persistent object cache (Redis, Memcached).
* Fixed: posts built with Elementor, Divi, Beaver Builder or Bricks are no longer split (they showed the whole post on every chapter).
* Tested with Redis Object Cache, Elementor, the WordPress core sitemap and custom post types.
* For developers: new filters `pchap_seo_integrations`, `pchap_speculation_rule`, `pchap_speculation_mode` and `pchap_is_builder_post`; the SEO and comments code moved to `pchap()->seo` and `pchap()->comments` (the old `PChap` methods still work, deprecated).

= 2.0.0 =
Release Date: November 9th, 2026

Multipage becomes Post Chapters. Existing content and settings need no change.

* Changes:
	* New name, slug (`post-chapters`) and text domain. The block, the editor buttons and the settings now speak of chapters; an untitled chapter is titled "Chapter N".
	* Settings moved to **Settings > Post Chapters**; the WP-CLI command is `wp post-chapters` (`wp multipage` still works).
	* Post Chapters stays idle, with a notice, while Multipage is active.
* For developers:
	* Functions, classes and constants use the `pchap` prefix (`pchap_toc()`, `PChap`, `pchap()`, `PCHAP_VERSION`); hooks use the `pchap_` prefix, and callbacks attached to the old `mpp_*` hooks still run, as deprecated hooks.
	* The `[nextpage]` shortcode, the `multipage/subpage` block name, the options, the `_mpp_data` meta and the `.mpp-*` CSS classes are unchanged.

= 1.7.1 =
Release Date: November 8th, 2026

* Bundled translations used even when an old language pack is installed; parse cache keyed on the parser code.

= 1.7.0 =
Release Date: November 7th, 2026

* Major rewrite of Multipage for WordPress 7.1: rebuilt block, reliable splitting, SEO integrations, validated settings, index rebuild tools.

= 1.5.13 – 1.6.0 =
Security and reliability fixes of Multipage, released on GitHub.

= 1.5.12 and earlier =
Multipage by Sergio De Falco, on WordPress.org.

The full history is in [CHANGELOG.md](https://github.com/PaulArgoud/paginova/blob/main/CHANGELOG.md).

== Upgrade Notice ==

= 2.3.0 =
Settings are moved to new option names and the chapter index is rebuilt in the background. Code reading the `mpp-*` options or the `_mpp_data` meta directly must use the `pchap-*` and `_pchap_data` names.

= 2.2.0 =
Post Chapters is now Paginova. Content and settings are kept. Deactivate Post Chapters after activating Paginova, and delete its folder rather than using Delete on the Plugins screen.

= 2.1.0 =
Fixes for All in One SEO (chapters were noindex), SEOPress and Yoast (wrong canonical and URLs), a clean uninstall with a persistent object cache, and posts built with Elementor. New Chapters panel in the editor.

= 2.0.0 =
Multipage is now Post Chapters. Content and settings are kept. Deactivate Multipage after activating Post Chapters, and delete its folder rather than using Delete on the Plugins screen.
