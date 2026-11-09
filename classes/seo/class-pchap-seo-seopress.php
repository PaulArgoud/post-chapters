<?php
/**
 * SEOPress integration.
 *
 * @package Post Chapters
 * @since 2.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Don't access directly.
}

/**
 * SEOPress: %%page%% variable, titles, canonical.
 *
 * @since 1.7.0 As methods of PChap.
 * @since 2.1.0
 */
class PChap_SEO_SEOPress extends PChap_SEO_Integration {

	/**
	 * Whether SEOPress is active.
	 *
	 * @return bool
	 */
	public function is_active() {
		return defined( 'SEOPRESS_VERSION' );
	}

	/**
	 * Register the hooks.
	 */
	public function register_hooks() {
		add_filter( 'seopress_context_paged', array( $this, 'filter_context_paged' ) );
		add_filter( 'seopress_get_tag_page_value', array( $this, 'filter_tag_page_value' ), 10, 1 );
		add_filter( 'seopress_titles_title', array( $this, 'filter_title' ), 10, 2 );
		add_filter( 'seopress_social_og_title', array( $this, 'filter_title' ), 10, 1 );
		add_filter( 'seopress_social_twitter_card_title', array( $this, 'filter_title' ), 10, 1 );
		add_filter( 'seopress_titles_canonical', array( $this, 'filter_canonical' ), 10, 1 );
	}

	/**
	 * Legacy dynamic variables: %%page%% on singular subpages.
	 *
	 * SEOPress only fills %%page%% for paginated archives, so it is empty on subpages.
	 *
	 * @param string $value " {sep} Page X of Y" or empty.
	 * @return string
	 */
	public function filter_context_paged( $value ) {
		if ( (int) get_query_var( 'paged' ) > 1 ) {
			return $value;
		}

		$text = $this->seo->get_subpage_title();
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
	 * Tags engine: %%page%% on singular subpages.
	 *
	 * @param string $value "Page X of Y" or empty.
	 * @return string
	 */
	public function filter_tag_page_value( $value ) {
		if ( is_string( $value ) && '' !== $value ) {
			return $value;
		}

		$text = $this->seo->get_subpage_title();

		return '' !== $text ? esc_attr( $text ) : $value;
	}

	/**
	 * Titles of the chapter (document, Open Graph, Twitter).
	 *
	 * SEOPress filters the title template, before it replaces its variables. The default
	 * template has no %%page%% variable, so the chapter title goes after %%post_title%%.
	 *
	 * @param string $title   The title, or its template.
	 * @param mixed  $context Optional. What the title is built for.
	 * @return string
	 */
	public function filter_title( $title, $context = null ) {
		$chapter = $this->seo->get_subpage_title( $context );

		if ( ! is_string( $title ) || '' === $title || '' === $chapter || false !== strpos( $title, '%%page%%' ) || false !== strpos( $title, $chapter ) ) {
			return $title;
		}

		$variable = '%%post_title%%';
		$position = strpos( $title, $variable );
		if ( false !== $position ) {
			$addition = $variable . ' %%sep%% ' . str_replace( '%%', '%', $chapter );

			return substr_replace( $title, $addition, $position, strlen( $variable ) );
		}

		// Another template: its variables are SEOPress's business. A title without variables is text.
		if ( false !== strpos( $title, '%%' ) ) {
			return $title;
		}

		return $this->seo->ensure_chapter_title( $title, false, $context );
	}

	/**
	 * Canonical of subpages >= 2: the subpage, not the post (a canonical set by the user is kept).
	 *
	 * SEOPress filters the whole `<link rel="canonical" href="...">` tag.
	 *
	 * @param string $tag The canonical link tag.
	 * @return string
	 */
	public function filter_canonical( $tag ) {
		if ( ! is_string( $tag ) || ! $this->seo->is_subpage_view() || ! preg_match( '/href="([^"]*)"/', $tag, $match ) ) {
			return $tag;
		}

		$url     = html_entity_decode( $match[1], ENT_QUOTES, 'UTF-8' );
		$chapter = $this->seo->maybe_chapter_url( $url );
		if ( $chapter === $url ) {
			return $tag;
		}

		return str_replace( $match[0], 'href="' . htmlspecialchars( urldecode( $chapter ), ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401 ) . '"', $tag );
	}
}
