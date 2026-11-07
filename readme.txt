=== Multipage ===
Contributors: envire
Tags: multi-page, table-of-contents, nextpage, subpages, seo
Requires at least: 6.3
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.7.0
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Split long posts into subpages, each with its own title, an automatic table of contents and navigation. Block and classic editors.

== Description ==

Multipage splits a long post into several subpages. Each subpage has its own title, and a table of contents links them together, so readers find the section they want and every subpage counts as a page view.

= Features =

* **Subpage block** for the block editor. It works in the iframed editor of recent WordPress versions, and posts saved with earlier versions of Multipage stay valid.
* **`[nextpage title="…"]` shortcode** for the classic editor, with a **Subpage** button in the Visual editor, the Text editor and the Classic block.
* **Reliable splitting**: Subpage blocks, shortcodes and core page breaks can be mixed. Every subpage is balanced HTML, even when a shortcode sits inside a paragraph or a heading, so the table of contents can never end up inside a heading.
* **Table of contents** with a configurable position (top left, top right, top, bottom or hidden), row labels, header and an optional link to the comments.
* **Navigation** between subpages: "Continue / Back to" or "Previous / Next" links.
* **Comments** on all subpages, on the first one only or on the last one only, in classic and block themes.
* **SEO-friendly**: the subpage title appears in the browser title, `rel=prev` / `rel=next` links are added, and Yoast SEO, Rank Math and SEOPress show the subpage title instead of "Page X of Y".
* **Block and classic themes**: the table of contents and the navigation are added once, only to the post being read, never to excerpts, related posts or Query Loop blocks.
* **Your own styles**: the plugin loads a minimal stylesheet that your theme can replace.
* **RTL support** and translations in French, German, Italian and Spanish.
* **Developer friendly**: filters for every piece of output, WP-CLI commands (`wp multipage status`, `wp multipage rebuild`).

= Usage =

In the block editor, add a **Subpage** block where a new subpage starts and type its title.

In the classic editor, click the **Subpage** button, or type the shortcode where a new subpage starts:

`[nextpage title="My subpage title"]`

The content before the first subpage becomes the intro page. A subpage without a title is titled "Page N".

= Credits =

Multipage was created by Sergio De Falco (SGr33n, Envire Web Solutions), who developed it up to version 1.5.12. Since version 1.5.13 it has been maintained by Paul Argoud, with the original author credited in the code. Development happens on [GitHub](https://github.com/PaulArgoud/sgr-nextpage-titles).

== Installation ==

1. In WordPress, go to **Plugins > Add New**, search for **Multipage**, then click **Install Now** and **Activate**.
2. Configure the plugin under **Settings > Multipage**.
3. Add Subpage blocks or `[nextpage title="…"]` shortcodes to your posts.

To install manually, upload the `sgr-nextpage-titles` folder to `/wp-content/plugins/`, then activate **Multipage** in the **Plugins** menu.

== Frequently Asked Questions ==

= Do I need to change my existing posts when I update to 1.7.0? =

No. Existing Subpage blocks and `[nextpage]` shortcodes keep working. After the update, the subpage index of every post is rebuilt in the background. On a large site you can also run `wp multipage rebuild`, or click **Rebuild subpage index** under **Settings > Multipage > Advanced**.

= How do I customize the look of the table of contents? =

Copy `css/multipage.css` from the plugin into the `css/` folder of your theme (or child theme) and edit it there. Use the name `multipage.min.css`, unless `SCRIPT_DEBUG` is enabled, and `multipage-rtl.min.css` for right-to-left languages. Multipage looks in the child theme first, then in the parent theme, then uses its own stylesheet. You can also override the `.mpp-toc-container` and `.mpp-post-navigation` classes in your theme stylesheet.

= Can I put the shortcode inside a heading or a paragraph? =

Yes. The elements open around the shortcode are closed at the end of the subpage and reopened on the next one, and elements left empty by the cut are removed.

= Does it work with SEO plugins? =

Yes. With Yoast SEO, Rank Math or SEOPress, the "page" variable of your title templates shows the subpage title on subpages 2 and up. With Yoast SEO, each subpage also declares its own canonical URL.

= What happens with a subpage number that does not exist? =

WordPress redirects the visitor to the first page of the post, as it does for posts split with the standard page break.

== Screenshots ==

1. The settings page.
2. The block editor with a subpage block.
3. How the post will look like.

== Changelog ==

= 1.7.0 =
Release Date: November 7th, 2026

Major rewrite. Existing content needs no change.

* Compatibility:
	* Tested up to WordPress 7.1 and PHP 8.4. Requires WordPress 6.3 or later and PHP 7.4 or later.
	* The Subpage block was rebuilt for the current block editor (iframed editor). Blocks saved with earlier versions stay valid.
* Bug fixes:
	* A shortcode inside a paragraph, a heading or a `div` no longer leaves unclosed tags, which could put the table of contents and the navigation inside a heading.
	* Titles and subpages can no longer get out of step: untitled subpages, mixed blocks, shortcodes and core page breaks, and the intro page are handled consistently.
	* The table of contents is no longer added to other posts, to excerpts, or twice on the same post.
	* "Display comments on: first page" now works on `/post/1/`, and the comments setting now also works in block themes.
	* Titles are escaped consistently, and links work with plain permalinks.
	* The bundled translations are now loaded, and the French, German, Italian and Spanish translations are complete.
	* The Subpage buttons of the classic editor handle quotes and special characters in titles.
* Enhancements:
	* Yoast SEO, Rank Math and SEOPress integrations; with Yoast SEO, each subpage declares its own canonical URL.
	* Settings are validated, and a "Rebuild subpage index" button and the `wp multipage` WP-CLI commands are available.
	* The table of contents styles no longer depend on the surrounding text size, and RTL stylesheets were added.
	* No more HTML comments in the public page source.

= 1.5.13 – 1.6.0 =
Security and reliability fixes, released on GitHub: escaping of subpage titles and editor strings, PHP 8.2 compatibility, 404 handling of subpages, `rel=next` link on the first page, capability checks when saving the settings.

= 1.5.12 =
Release Date: Jun, 16th, 2021

* Enhancements:
	* Compatibility check.

The full history, including older versions and developer notes, is in [CHANGELOG.md](https://github.com/PaulArgoud/sgr-nextpage-titles/blob/main/CHANGELOG.md).

== Upgrade Notice ==

= 1.7.0 =
Major rewrite, compatible with WordPress 7.1. Requires WordPress 6.3 or later. Existing content keeps working; the subpage index is rebuilt in the background after the update.
