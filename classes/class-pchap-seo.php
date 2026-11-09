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
 * Titles and links of the subpages for browsers, search engines and SEO plugins
 * (Yoast SEO, Rank Math, SEOPress).
 *
 * Reads the request state of the PChap instance; its hooks are registered by
 * PChap::pchap_post() on valid multipage views only. Available as pchap()->seo.
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
	 * Constructor.
	 *
	 * @since 2.1.0
	 *
	 * @param PChap $pchap The main plugin instance.
	 */
	public function __construct( PChap $pchap ) {
		$this->pchap = $pchap;
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

		add_action( 'wp_head', array( $this, 'print_rel_links' ) );

		// Yoast SEO: %%page%% replacement variable and its own rel prev/next.
		if ( defined( 'WPSEO_VERSION' ) ) {
			add_filter( 'wpseo_replacements', array( $this, 'filter_wpseo_replacements' ), 10, 2 );
			add_filter( 'wpseo_adjacent_rel_url', array( $this, 'filter_seo_adjacent_rel_url' ), 10, 1 );
			add_filter( 'wpseo_canonical', array( $this, 'filter_wpseo_canonical' ), 10, 2 );
		}

		// Rank Math: %page% replacement variable and its own rel prev/next.
		if ( defined( 'RANK_MATH_VERSION' ) ) {
			add_filter( 'rank_math/replacements', array( $this, 'filter_rank_math_replacements' ), 10, 2 );
			add_filter( 'rank_math/frontend/disable_adjacent_rel_links', array( $this, 'filter_disable_seo_adjacent_rel_links' ) );
		}

		// SEOPress: %%page%% (legacy variables and Tags engine).
		if ( defined( 'SEOPRESS_VERSION' ) ) {
			add_filter( 'seopress_context_paged', array( $this, 'filter_seopress_context_paged' ) );
			add_filter( 'seopress_get_tag_page_value', array( $this, 'filter_seopress_tag_page_value' ), 10, 1 );
		}
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
	 * Plain-text title of the current subpage, on subpages >= 2 of the multipage post.
	 *
	 * @since 1.7.0 As the private PChap::get_seo_subpage_title().
	 * @since 2.1.0
	 *
	 * @param mixed $context Optional. Post object/array an SEO plugin is building a title for.
	 * @return string Empty string when nothing should be replaced.
	 */
	public function get_subpage_title( $context = null ) {
		if ( ! $this->pchap->is_multipage_view() || $this->pchap->get_current_page() < 2 ) {
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
	 * Yoast SEO: replace %%page%% ("{sep} Page X of Y") with "{sep} {subpage title}".
	 *
	 * @since 1.7.0
	 *
	 * @param array $replacements Replacement values keyed by %%var%%.
	 * @param mixed $args         The object the replacements are computed for.
	 * @return array
	 */
	public function filter_wpseo_replacements( $replacements, $args = null ) {
		if ( ! is_array( $replacements ) || ! isset( $replacements['%%page%%'] ) ) {
			return $replacements;
		}

		$text = $this->get_subpage_title( $args );
		if ( '' === $text ) {
			return $replacements;
		}

		$sep = '';
		if ( isset( $replacements['%%sep%%'] ) && is_string( $replacements['%%sep%%'] ) ) {
			$sep = $replacements['%%sep%%'];
		} elseif ( function_exists( 'YoastSEO' ) ) {
			$yoast = YoastSEO();
			if ( isset( $yoast->helpers->options ) && method_exists( $yoast->helpers->options, 'get_title_separator' ) ) {
				$sep = (string) $yoast->helpers->options->get_title_separator();
			}
		}

		$replacements['%%page%%'] = ( '' !== $sep ? $sep . ' ' : '' ) . $text;

		return $replacements;
	}

	/**
	 * Rank Math: replace %page% ("{sep} Page X of Y") with "{sep} {subpage title}".
	 *
	 * @since 1.7.0
	 *
	 * @param array $replacements Replacement values keyed by %var%.
	 * @param mixed $args         The object the replacements are computed for.
	 * @return array
	 */
	public function filter_rank_math_replacements( $replacements, $args = null ) {
		if ( ! is_array( $replacements ) || ! array_key_exists( '%page%', $replacements ) ) {
			return $replacements;
		}

		$text = $this->get_subpage_title( $args );
		if ( '' === $text ) {
			return $replacements;
		}

		$sep = '';
		if ( isset( $replacements['%sep%'] ) && is_string( $replacements['%sep%'] ) ) {
			$sep = $replacements['%sep%'];
		} elseif ( class_exists( '\RankMath\Helper' ) && is_callable( array( '\RankMath\Helper', 'get_settings' ) ) ) {
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound, WordPress.NamingConventions.ValidHookName.UseUnderscores -- Rank Math's own hook, applied like Rank Math does.
			$sep = (string) apply_filters( 'rank_math/settings/title_separator', \RankMath\Helper::get_settings( 'titles.title_separator' ) );
			$sep = htmlentities( $sep, ENT_COMPAT, 'UTF-8', false );
		}

		$replacements['%page%'] = ( '' !== $sep ? $sep . ' ' : '' ) . $text;

		return $replacements;
	}

	/**
	 * SEOPress (legacy dynamic variables): %%page%% on singular subpages.
	 *
	 * SEOPress only fills %%page%% for paginated archives, so it is empty on subpages.
	 *
	 * @since 1.7.0
	 *
	 * @param string $value " {sep} Page X of Y" or empty.
	 * @return string
	 */
	public function filter_seopress_context_paged( $value ) {
		if ( (int) get_query_var( 'paged' ) > 1 ) {
			return $value;
		}

		$text = $this->get_subpage_title();
		if ( '' === $text ) {
			return $value;
		}

		$sep = '-';
		if ( function_exists( 'seopress_get_service' ) ) {
			$option = seopress_get_service( 'TitleOption' );
			if ( is_object( $option ) && method_exists( $option, 'getSeparator' ) && $option->getSeparator() ) {
				$sep = htmlspecialchars( (string) $option->getSeparator(), ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401 );
			}
		}

		return ' ' . $sep . ' ' . esc_attr( $text );
	}

	/**
	 * SEOPress (Tags engine): %%page%% on singular subpages.
	 *
	 * @since 1.7.0
	 *
	 * @param string $value "Page X of Y" or empty.
	 * @return string
	 */
	public function filter_seopress_tag_page_value( $value ) {
		if ( is_string( $value ) && '' !== $value ) {
			return $value;
		}

		$text = $this->get_subpage_title();

		return '' !== $text ? esc_attr( $text ) : $value;
	}

	/**
	 * Yoast SEO: drop its rel prev/next on the multipage post (print_rel_links() prints them).
	 *
	 * @since 1.7.0
	 *
	 * @param string $url The adjacent URL.
	 * @return string
	 */
	public function filter_seo_adjacent_rel_url( $url ) {
		return $this->pchap->is_multipage_view() ? '' : $url;
	}

	/**
	 * Yoast SEO: canonical URL of subpages >= 2.
	 *
	 * Yoast only paginates the canonical of posts whose saved content holds core
	 * `<!--nextpage-->` markers, so every subpage of a block or shortcode post would
	 * declare the first page as its canonical (and be dropped from search results).
	 * The default canonical (the post permalink) becomes the subpage URL, like core
	 * rel_canonical() does; a canonical set by the user in Yoast is kept.
	 *
	 * @since 1.7.0
	 *
	 * @param string $canonical    The canonical URL.
	 * @param mixed  $presentation Optional. The Yoast indexable presentation.
	 * @return string
	 */
	public function filter_wpseo_canonical( $canonical, $presentation = null ) {
		if ( ! is_string( $canonical ) || '' === $canonical || ! $this->pchap->is_multipage_view() || $this->pchap->get_current_page() < 2 || ! is_singular() ) {
			return $canonical;
		}

		// A canonical set by the user for this post.
		if ( is_object( $presentation ) && isset( $presentation->model ) && is_object( $presentation->model ) && ! empty( $presentation->model->canonical ) ) {
			return $canonical;
		}

		$permalink = get_permalink( $this->pchap->post_id );
		if ( ! $permalink || untrailingslashit( $canonical ) !== untrailingslashit( $permalink ) ) {
			return $canonical;
		}

		$url = pchap_link_page_url( $this->pchap->get_current_page(), '', $this->pchap->post_id );

		return '' !== $url ? $url : $canonical;
	}

	/**
	 * Rank Math: disable its rel prev/next on the multipage post (print_rel_links() prints them).
	 *
	 * @since 1.7.0
	 *
	 * @param bool $disable Whether to disable the links.
	 * @return bool
	 */
	public function filter_disable_seo_adjacent_rel_links( $disable ) {
		return $this->pchap->is_multipage_view() ? true : $disable;
	}
}
