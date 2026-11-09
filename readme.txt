=== Post Chapters ===
Contributors: paulargoud
Tags: chapters, table of contents, pagination, nextpage, long posts
Requires at least: 6.3
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 2.0.0
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Split long posts into chapters, each on its own page with its own title, an automatic table of contents and navigation. Block and classic editors.

== Description ==

Post Chapters splits a long post into chapters. Each chapter is a page of its own with its own title, and a table of contents links them together, so readers find the section they want and every chapter counts as a page view.

= Features =

* **Chapter block** for the block editor. It works in the iframed editor of recent WordPress versions.
* **`[nextpage title="…"]` shortcode** for the classic editor, with a **Chapter** button in the Visual editor, the Text editor and the Classic block.
* **Reliable splitting**: Chapter blocks, shortcodes and core page breaks can be mixed. Every chapter is balanced HTML, even when a shortcode sits inside a paragraph or a heading, so the table of contents can never end up inside a heading.
* **Table of contents** with a configurable position (top left, top right, top, bottom or hidden), row labels, header and an optional link to the comments.
* **Navigation** between chapters: "Continue / Back to" or "Previous / Next" links.
* **Comments** on all chapters, on the first one only or on the last one only, in classic and block themes.
* **SEO-friendly**: the chapter title appears in the browser title, `rel=prev` / `rel=next` links are added, and Yoast SEO, Rank Math and SEOPress show the chapter title instead of "Page X of Y".
* **Block and classic themes**: the table of contents and the navigation are added once, only to the post being read, never to excerpts, related posts or Query Loop blocks.
* **Your own styles**: the plugin loads a minimal stylesheet that your theme can replace.
* **RTL support** and translations in French, German, Italian and Spanish.
* **Developer friendly**: filters for every piece of output, WP-CLI commands (`wp post-chapters status`, `wp post-chapters rebuild`).

= Usage =

In the block editor, add a **Chapter** block where a new chapter starts and type its title.

In the classic editor, click the **Chapter** button, or type the shortcode where a new chapter starts:

`[nextpage title="My chapter title"]`

The content before the first chapter becomes the intro page. A chapter without a title is titled "Chapter N".

= Credits =

Post Chapters is the continuation of Multipage, created by Sergio De Falco (SGr33n, Envire Web Solutions), who developed it up to version 1.5.12. Paul Argoud maintained it from 1.5.13 and renamed it Post Chapters in 2.0.0. Development happens on [GitHub](https://github.com/PaulArgoud/post-chapters).

== Installation ==

1. Upload the `post-chapters` folder to `/wp-content/plugins/`, or upload the zip under **Plugins > Add New > Upload Plugin**.
2. Activate **Post Chapters** in the **Plugins** menu.
3. Configure the plugin under **Settings > Post Chapters**.
4. Add Chapter blocks or `[nextpage title="…"]` shortcodes to your posts.

== Frequently Asked Questions ==

= I use Multipage. How do I switch? =

Post Chapters reuses the content, the settings and the index of Multipage. Activate Post Chapters (it stays idle and shows a notice while Multipage is active), then deactivate Multipage. To remove Multipage, delete its `sgr-nextpage-titles` folder over FTP or SSH rather than with **Delete** on the Plugins screen: Multipage 1.5.15 and later erase, when deleted from the Plugins screen, the settings and the index that Post Chapters uses.

= Do I need to change my existing posts? =

No. Existing Subpage blocks (now called Chapter blocks) and `[nextpage]` shortcodes keep working.

= How do I customize the look of the table of contents? =

Copy `css/post-chapters.css` from the plugin into the `css/` folder of your theme (or child theme) and edit it there. Use the name `post-chapters.min.css`, unless `SCRIPT_DEBUG` is enabled, and `post-chapters-rtl.min.css` for right-to-left languages. Post Chapters looks in the child theme first, then in the parent theme, then uses its own stylesheet; the `multipage*.css` names of Multipage are still recognized. You can also override the `.mpp-toc-container` and `.mpp-post-navigation` classes in your theme stylesheet.

= Can I put the shortcode inside a heading or a paragraph? =

Yes. The elements open around the shortcode are closed at the end of the chapter and reopened on the next one, and elements left empty by the cut are removed.

= Does it work with SEO plugins? =

Yes. With Yoast SEO, Rank Math or SEOPress, the "page" variable of your title templates shows the chapter title on chapters 2 and up. With Yoast SEO, each chapter also declares its own canonical URL.

= What happens with a chapter number that does not exist? =

WordPress redirects the visitor to the first page of the post, as it does for posts split with the standard page break.

== Screenshots ==

1. The settings page.
2. The block editor with a Chapter block.
3. How the post will look like.

== Changelog ==

= 2.0.0 =
Release Date: November 2026

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

The full history is in [CHANGELOG.md](https://github.com/PaulArgoud/post-chapters/blob/main/CHANGELOG.md).

== Upgrade Notice ==

= 2.0.0 =
Multipage is now Post Chapters. Content and settings are kept. Deactivate Multipage after activating Post Chapters, and delete its folder rather than using Delete on the Plugins screen.
