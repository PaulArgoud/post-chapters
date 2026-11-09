<?php
/**
 * Yoast SEO integration.
 *
 * @package Post Chapters
 * @since 2.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Don't access directly.
}

/**
 * Yoast SEO: %%page%% variable, canonical, Open Graph URL, schema WebPage URL, rel prev/next.
 *
 * @since 1.7.0 As methods of PChap.
 * @since 2.1.0
 */
class PChap_SEO_Yoast extends PChap_SEO_Integration {

	/**
	 * Whether Yoast SEO is active.
	 *
	 * @return bool
	 */
	public function is_active() {
		return defined( 'WPSEO_VERSION' );
	}

	/**
	 * Register the hooks.
	 */
	public function register_hooks() {
		add_filter( 'wpseo_replacements', array( $this, 'filter_replacements' ), 10, 2 );
		add_filter( 'wpseo_adjacent_rel_url', array( $this, 'filter_adjacent_rel_url' ), 10, 1 );
		add_filter( 'wpseo_canonical', array( $this, 'filter_canonical' ), 10, 2 );
		add_filter( 'wpseo_opengraph_url', array( $this, 'filter_opengraph_url' ), 10, 1 );
		add_filter( 'wpseo_schema_webpage', array( $this, 'filter_schema_webpage' ), 10, 1 );
	}

	/**
	 * Replace %%page%% ("{sep} Page X of Y") with "{sep} {subpage title}".
	 *
	 * @param array $replacements Replacement values keyed by %%var%%.
	 * @param mixed $args         The object the replacements are computed for.
	 * @return array
	 */
	public function filter_replacements( $replacements, $args = null ) {
		if ( ! is_array( $replacements ) || ! isset( $replacements['%%page%%'] ) ) {
			return $replacements;
		}

		$text = $this->seo->get_subpage_title( $args );
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
	 * Drop the rel prev/next of Yoast on the multipage post (PChap_SEO::print_rel_links() prints them).
	 *
	 * @param string $url The adjacent URL.
	 * @return string
	 */
	public function filter_adjacent_rel_url( $url ) {
		return $this->pchap->is_multipage_view() ? '' : $url;
	}

	/**
	 * Canonical URL of subpages >= 2.
	 *
	 * Yoast only paginates the canonical of posts whose saved content holds core
	 * `<!--nextpage-->` markers, so every subpage of a block or shortcode post would
	 * declare the first page as its canonical (and be dropped from search results).
	 * The default canonical (the post permalink) becomes the subpage URL, like core
	 * rel_canonical() does; a canonical set by the user in Yoast is kept.
	 *
	 * @param string $canonical    The canonical URL.
	 * @param mixed  $presentation Optional. The Yoast indexable presentation.
	 * @return string
	 */
	public function filter_canonical( $canonical, $presentation = null ) {
		// A canonical set by the user for this post.
		if ( is_object( $presentation ) && isset( $presentation->model ) && is_object( $presentation->model ) && ! empty( $presentation->model->canonical ) ) {
			return $canonical;
		}

		return $this->seo->maybe_chapter_url( $canonical );
	}

	/**
	 * Open Graph URL (og:url) of subpages >= 2: the subpage, not the post.
	 *
	 * @param string $url The Open Graph URL.
	 * @return string
	 */
	public function filter_opengraph_url( $url ) {
		return $this->seo->maybe_chapter_url( $url );
	}

	/**
	 * Schema WebPage `url` of subpages >= 2: the subpage, not the post. The `@id`s of
	 * the graph pieces are kept, so the pieces still reference each other.
	 *
	 * @param array $data The WebPage piece.
	 * @return array
	 */
	public function filter_schema_webpage( $data ) {
		if ( is_array( $data ) && isset( $data['url'] ) ) {
			$data['url'] = $this->seo->maybe_chapter_url( $data['url'] );
		}

		return $data;
	}
}
