<?php
/**
 * The SEO Framework integration.
 *
 * @package Paginova
 * @since 2.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Don't access directly.
}

/**
 * The SEO Framework: Open Graph and Twitter titles, rel prev/next.
 *
 * The document title is handled by PChap_SEO::filter_pre_document_title().
 *
 * @since 2.1.0
 */
class PChap_SEO_Framework extends PChap_SEO_Integration {

	/**
	 * Whether The SEO Framework is active.
	 *
	 * @return bool
	 */
	public function is_active() {
		return defined( 'THE_SEO_FRAMEWORK_VERSION' );
	}

	/**
	 * Register the hooks.
	 */
	public function register_hooks() {
		add_filter( 'the_seo_framework_meta_render_data', array( $this, 'filter_meta_render_data' ), 10, 1 );
	}

	/**
	 * Tags printed in the head: no rel prev/next of the plugin (PChap_SEO::print_rel_links() prints
	 * them), and the chapter title in the Open Graph and Twitter titles instead of "Page N".
	 *
	 * @param array $tags Render data of the meta tags, by id.
	 * @return array
	 */
	public function filter_meta_render_data( $tags ) {
		if ( ! is_array( $tags ) || ! $this->pchap->is_multipage_view() ) {
			return $tags;
		}

		unset( $tags['prev'], $tags['next'] );

		foreach ( array( 'og:title', 'twitter:title' ) as $id ) {
			if ( isset( $tags[ $id ]['attributes']['content'] ) && is_string( $tags[ $id ]['attributes']['content'] ) ) {
				$tags[ $id ]['attributes']['content'] = $this->seo->ensure_chapter_title( $tags[ $id ]['attributes']['content'] );
			}
		}

		return $tags;
	}
}
