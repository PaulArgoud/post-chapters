<?php
/**
 * Rank Math integration.
 *
 * @package Post Chapters
 * @since 2.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Don't access directly.
}

/**
 * Rank Math: %page% variable, social titles, rel prev/next.
 *
 * @since 1.7.0 As methods of PChap.
 * @since 2.1.0
 */
class PChap_SEO_Rank_Math extends PChap_SEO_Integration {

	/**
	 * Whether Rank Math is active.
	 *
	 * @return bool
	 */
	public function is_active() {
		return defined( 'RANK_MATH_VERSION' );
	}

	/**
	 * Register the hooks.
	 */
	public function register_hooks() {
		add_filter( 'rank_math/replacements', array( $this, 'filter_replacements' ), 10, 2 );
		add_filter( 'rank_math/frontend/disable_adjacent_rel_links', array( $this, 'filter_disable_adjacent_rel_links' ) );
		add_filter( 'rank_math/opengraph/facebook/og_title', array( $this, 'filter_title' ), 10, 1 );
		add_filter( 'rank_math/opengraph/twitter/twitter_title', array( $this, 'filter_title' ), 10, 1 );
	}

	/**
	 * Replace %page% ("{sep} Page X of Y") with "{sep} {subpage title}".
	 *
	 * @param array $replacements Replacement values keyed by %var%.
	 * @param mixed $args         The object the replacements are computed for.
	 * @return array
	 */
	public function filter_replacements( $replacements, $args = null ) {
		if ( ! is_array( $replacements ) || ! array_key_exists( '%page%', $replacements ) ) {
			return $replacements;
		}

		$text = $this->seo->get_subpage_title( $args );
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
	 * Disable the rel prev/next of Rank Math on the multipage post (PChap_SEO::print_rel_links() prints them).
	 *
	 * @param bool $disable Whether to disable the links.
	 * @return bool
	 */
	public function filter_disable_adjacent_rel_links( $disable ) {
		return $this->pchap->is_multipage_view() ? true : $disable;
	}

	/**
	 * Open Graph and Twitter titles: the default title template of Rank Math has no
	 * page variable, so the chapter title is added.
	 *
	 * @param string $title The title.
	 * @return string
	 */
	public function filter_title( $title ) {
		return $this->seo->ensure_chapter_title( $title );
	}
}
