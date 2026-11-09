<?php
/**
 * Post Chapters SEO: document title, rel prev/next links and SEO plugin integrations.
 *
 * @package Post Chapters
 * @since 2.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Don't access directly.
}

/**
 * What browsers, search engines and SEO plugins see of a chapter.
 *
 * Reads the request state of the PChap instance; its hooks are registered by
 * PChap::pchap_post() on valid multipage views only. Available as pchap()->seo.
 *
 * - Core: the "Page N" part of the document title becomes the chapter title, and
 *   the rel prev/next links are printed.
 * - Any SEO plugin: the final document title always carries the chapter title
 *   (ensure_chapter_title()), whatever the title template of the plugin is.
 * - Yoast SEO, Rank Math, SEOPress, All in One SEO and The SEO Framework: one
 *   PChap_SEO_Integration object each, registered only when the plugin is active.
 *
 * @since 2.1.0
 */
class PChap_SEO {

	/**
	 * The main plugin instance.
	 *
	 * @var PChap
	 */
	private $pchap;

	/**
	 * The SEO plugin integrations, by key (yoast, rank_math, seopress, aioseo, seo_framework).
	 *
	 * @var PChap_SEO_Integration[]
	 */
	private $integrations = array();

	/**
	 * Constructor.
	 *
	 * @since 2.1.0
	 *
	 * @param PChap $pchap The main plugin instance.
	 */
	public function __construct( PChap $pchap ) {
		$this->pchap        = $pchap;
		$this->integrations = array(
			'yoast'         => new PChap_SEO_Yoast( $pchap, $this ),
			'rank_math'     => new PChap_SEO_Rank_Math( $pchap, $this ),
			'seopress'      => new PChap_SEO_SEOPress( $pchap, $this ),
			'aioseo'        => new PChap_SEO_AIOSEO( $pchap, $this ),
			'seo_framework' => new PChap_SEO_Framework( $pchap, $this ),
		);
	}

	/**
	 * The integration of an SEO plugin.
	 *
	 * @since 2.1.0
	 *
	 * @param string $key yoast, rank_math, seopress, aioseo or seo_framework.
	 * @return PChap_SEO_Integration|null
	 */
	public function get_integration( $key ) {
		return isset( $this->integrations[ $key ] ) ? $this->integrations[ $key ] : null;
	}

	/**
	 * Register the hooks of the current multipage view.
	 *
	 * @since 2.1.0
	 */
	public function register_hooks() {
		// Change the document title only if it's not the first page.
		if ( $this->pchap->current_page > 1 ) {
			add_filter( 'document_title_parts', array( $this, 'filter_document_title_parts' ), pchap_get_rewrite_title_priority(), 1 );
		}

		// Last word on the title of any SEO plugin. Added once the plugins are done with it:
		// The SEO Framework removes every pre_get_document_title filter on template_redirect (20).
		add_action( 'template_redirect', array( $this, 'add_title_filter' ), PHP_INT_MAX );

		add_action( 'wp_head', array( $this, 'print_rel_links' ) );

		/**
		 * Filters the SEO plugin integrations of a multipage view.
		 *
		 * An integration only registers its hooks when its plugin is active. Add an
		 * object extending PChap_SEO_Integration to support another SEO plugin.
		 *
		 * @since 2.1.0
		 *
		 * @param PChap_SEO_Integration[] $integrations Integrations by key.
		 */
		$integrations = (array) pchap_apply_filters( 'pchap_seo_integrations', $this->integrations );

		foreach ( $integrations as $integration ) {
			if ( $integration instanceof PChap_SEO_Integration && $integration->is_active() ) {
				$integration->register_hooks();
			}
		}
	}

	/**
	 * Hook the last word on the document title.
	 *
	 * @since 2.1.0
	 */
	public function add_title_filter() {
		add_filter( 'pre_get_document_title', array( $this, 'filter_pre_document_title' ), PHP_INT_MAX, 1 );
	}

	/**
	 * Whether the current request is a chapter after the first one, rendered by the plugin.
	 *
	 * @since 2.1.0
	 *
	 * @return bool
	 */
	public function is_subpage_view() {
		return $this->pchap->is_multipage_view() && $this->pchap->get_current_page() >= 2;
	}

	/**
	 * Output rel=prev and rel=next links in the <head>.
	 *
	 * @since 1.5.13 As PChap::pchap_rel_links().
	 * @since 2.1.0
	 */
	public function print_rel_links() {
		if ( ! $this->pchap->is_multipage_view() || ! is_singular() ) {
			return;
		}

		$current_page = $this->pchap->get_current_page();
		$post_id      = $this->pchap->post_id;

		if ( $current_page > 1 ) {
			echo '<link rel="prev" href="' . esc_url( pchap_link_page_url( $current_page - 1, '', $post_id ) ) . '" />' . "\n";
		}

		if ( $current_page < (int) $this->pchap->max_num_pages ) {
			echo '<link rel="next" href="' . esc_url( pchap_link_page_url( $current_page + 1, '', $post_id ) ) . '" />' . "\n";
		}
	}

	/**
	 * Replace the core "Page N" part of the document title with the subpage title
	 * (plain text) on subpages >= 2.
	 *
	 * @since 1.4 As PChap::pchap_document_title_parts().
	 * @since 2.1.0
	 *
	 * @param array $title The WordPress document title parts.
	 * @return array
	 */
	public function filter_document_title_parts( $title ) {
		if ( ! is_array( $title ) ) {
			return $title;
		}

		$text = $this->get_subpage_title();
		if ( '' !== $text ) {
			$title['page'] = $text;
		}

		return $title;
	}

	/**
	 * Make sure the title an SEO plugin gives to WordPress carries the chapter title.
	 *
	 * @since 2.1.0
	 *
	 * @param string $title The title ('' when no plugin sets one).
	 * @return string
	 */
	public function filter_pre_document_title( $title ) {
		if ( ! is_string( $title ) || '' === $title || ! $this->is_subpage_view() ) {
			return $title;
		}

		// The title is printed as is: it holds HTML.
		return $this->ensure_chapter_title( $title, true );
	}

	/**
	 * Plain-text title of the current subpage, on subpages >= 2 of the multipage post.
	 *
	 * @since 1.7.0 As the private PChap::get_seo_subpage_title().
	 * @since 2.1.0
	 *
	 * @param mixed $context Optional. Post object/array an SEO plugin is building a title for.
	 * @return string Empty string when nothing should be replaced.
	 */
	public function get_subpage_title( $context = null ) {
		if ( ! $this->is_subpage_view() ) {
			return '';
		}

		$context_id = 0;
		if ( is_object( $context ) && isset( $context->ID ) ) {
			$context_id = (int) $context->ID;
		} elseif ( is_array( $context ) && isset( $context['ID'] ) ) {
			$context_id = (int) $context['ID'];
		}

		if ( $context_id && $context_id !== $this->pchap->post_id ) {
			return '';
		}

		return pchap_get_subpage_title_text( $this->pchap->page_title, $this->pchap->get_current_title_format() );
	}

	/**
	 * URL of the current chapter, when a URL given by an SEO plugin is the one of the post.
	 *
	 * SEO plugins give every chapter the permalink of the post. Any other URL (a
	 * canonical set by the user, another post) is kept.
	 *
	 * @since 2.1.0
	 *
	 * @param string $url A URL built by an SEO plugin.
	 * @return string The URL of the chapter, or $url.
	 */
	public function maybe_chapter_url( $url ) {
		if ( ! is_string( $url ) || '' === $url || ! $this->is_subpage_view() || ! is_singular() ) {
			return $url;
		}

		$permalink = get_permalink( $this->pchap->post_id );
		if ( ! $permalink || untrailingslashit( $url ) !== untrailingslashit( $permalink ) ) {
			return $url;
		}

		$chapter_url = pchap_link_page_url( $this->pchap->get_current_page(), '', $this->pchap->post_id );

		return '' !== $chapter_url ? $chapter_url : $url;
	}

	/**
	 * Add the chapter title to the title of a chapter built by an SEO plugin.
	 *
	 * Without it, every chapter of a post would share the same title as soon as the title
	 * template of the plugin has no "page" variable (the default of Rank Math and
	 * SEOPress), or would end with "Page N" (All in One SEO, The SEO Framework). The chapter
	 * title goes after the title of the post, with the separator of the plugin; a "Page N"
	 * part is removed. A title that already has the chapter title is left as it is.
	 *
	 * @since 2.1.0
	 *
	 * @param string $title   Title.
	 * @param bool   $html    Whether $title is HTML (printed as is) rather than plain text.
	 * @param mixed  $context Optional. Post object/array the title is built for.
	 * @return string
	 */
	public function ensure_chapter_title( $title, $html = false, $context = null ) {
		$chapter = $this->get_subpage_title( $context );

		if ( ! is_string( $title ) || '' === $title || '' === $chapter ) {
			return $title;
		}

		if ( false !== strpos( $this->to_plain_text( $title ), $chapter ) ) {
			return $title;
		}

		$addition = $html ? esc_html( $chapter ) : $chapter;
		$post     = get_post( $this->pchap->post_id );
		$found    = $post instanceof WP_Post ? $this->find_post_title( $title, $post->post_title ) : null;

		if ( null !== $found ) {
			$before = substr( $title, 0, $found[0] + $found[1] );
			$after  = (string) substr( $title, $found[0] + $found[1] );
		} else {
			$before = $title;
			$after  = '';
		}

		$sep = $this->detect_separator( $after, $html );

		return $this->strip_page_number( $before . ' ' . $sep . ' ' . $addition . $after );
	}

	/**
	 * Remove the "Page N" part (and the separator before it) of a title or description.
	 *
	 * @since 2.1.0
	 *
	 * @param string $text Text.
	 * @return string
	 */
	public function strip_page_number( $text ) {
		if ( ! is_string( $text ) || '' === $text ) {
			return $text;
		}

		$number = $this->pchap->get_current_page();
		$tokens = array(
			/* translators: %s: page number. */
			sprintf( __( 'Page %s', 'default' ), $number ), // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- The string of WordPress core, as the SEO plugins print it.
			sprintf( 'Page %s', $number ),
		);

		foreach ( array_unique( $tokens ) as $token ) {
			$pattern = '/(?:[\s\x{00A0}]|&nbsp;)*(?:[-\x{2013}\x{2014}|\x{00B7}\x{2022}\x{00BB}:]|&#8211;|&#8212;|&ndash;|&mdash;)?(?:[\s\x{00A0}]|&nbsp;)*' . preg_quote( $token, '/' ) . '(?!\w)/u';
			$result  = preg_replace( $pattern, '', $text, 1 );

			if ( is_string( $result ) && $result !== $text ) {
				return $result;
			}
		}

		return $text;
	}

	/**
	 * Text of a title, without tags and entities.
	 *
	 * @param string $text Title.
	 * @return string
	 */
	private function to_plain_text( $text ) {
		$text = html_entity_decode( wp_strip_all_tags( $text ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );

		return str_replace( "\xc2\xa0", ' ', $text );
	}

	/**
	 * Position of the title of the post in a title.
	 *
	 * @param string $haystack   Title built by an SEO plugin.
	 * @param string $post_title Title of the post.
	 * @return int[]|null Array( byte offset, byte length ), or null.
	 */
	private function find_post_title( $haystack, $post_title ) {
		$needles = array(
			$post_title,
			esc_html( $post_title ),
			wptexturize( $post_title ),
			esc_html( wptexturize( $post_title ) ),
			wp_specialchars_decode( $post_title ),
		);

		foreach ( array_unique( $needles ) as $needle ) {
			if ( '' === $needle ) {
				continue;
			}

			$position = strpos( $haystack, $needle );
			if ( false !== $position ) {
				return array( $position, strlen( $needle ) );
			}
		}

		return null;
	}

	/**
	 * The separator that follows the title of the post, or the one of the document title.
	 *
	 * @param string $after Text after the title of the post.
	 * @param bool   $html  Whether the text is HTML.
	 * @return string
	 */
	private function detect_separator( $after, $html ) {
		if ( preg_match( '/^[\s\x{00A0}]*(&#?\w+;|[^\w\s\x{00A0}]{1,3})[\s\x{00A0}]/u', $after, $match ) ) {
			return $match[1];
		}

		/** This filter is documented in wp-includes/general-template.php */
		$sep = (string) apply_filters( 'document_title_separator', '-' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress core hook.

		return $html ? esc_html( $sep ) : $sep;
	}
}
