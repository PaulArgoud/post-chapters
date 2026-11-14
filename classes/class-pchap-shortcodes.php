<?php
/**
 * Paginova Component Shortcodes.
 *
 * @package Paginova
 * @subpackage Shortcodes
 * @since 1.5
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Paginova chapter markers ([nextpage]) outside split views.
 *
 * `[nextpage]` is a content format, like the `<!--nextpage-->` break of core:
 * the parser reads it from the post content and splits the post on it. It is
 * not registered as a shortcode. Where the content is not split (feeds, REST
 * API, excerpts, archives), the markers are removed so that no stray marker or
 * title text leaks into the output.
 *
 * @since 1.1
 * @since 2.3.0 No longer registers a `nextpage` shortcode.
 */
class PChap_Shortcodes {

	/**
	 * Hook the removal of the chapter markers.
	 *
	 * @since 1.1
	 * @since 2.3.0 Hooks filters instead of registering a shortcode.
	 */
	public static function init() {
		// Before do_blocks (9), wpautop (10) and do_shortcode (11).
		add_filter( 'the_content', array( 'PChap_Shortcodes', 'remove_markers' ), 8 );
		// The Chapter block rendered on its own (render_block(), do_blocks()).
		add_filter( 'render_block_multipage/subpage', array( 'PChap_Shortcodes', 'remove_markers' ) );
		add_filter( 'strip_shortcodes_tagnames', array( 'PChap_Shortcodes', 'strip_shortcodes_tagnames' ) );
	}

	/**
	 * Remove the chapter markers from a content.
	 *
	 * Uses the shortcode syntax of core: an escaped marker (`[[nextpage]]`) is
	 * displayed literally, as `[nextpage]`.
	 *
	 * @since 2.3.0
	 *
	 * @param string $content Content.
	 * @return string
	 */
	public static function remove_markers( $content ) {
		if ( ! is_string( $content ) || false === strpos( $content, '[nextpage' ) ) {
			return $content;
		}

		$result = preg_replace_callback( '/' . get_shortcode_regex( array( 'nextpage' ) ) . '/', 'strip_shortcode_tag', $content );

		return is_string( $result ) ? $result : $content;
	}

	/**
	 * Let strip_shortcodes() remove the chapter markers too (excerpts).
	 *
	 * @since 2.3.0
	 *
	 * @param string[] $tags_to_remove Shortcode tags strip_shortcodes() removes.
	 * @return string[]
	 */
	public static function strip_shortcodes_tagnames( $tags_to_remove ) {
		$tags_to_remove   = (array) $tags_to_remove;
		$tags_to_remove[] = 'nextpage';

		return array_unique( $tags_to_remove );
	}

	/**
	 * Render the [nextpage] shortcode.
	 *
	 * @since 0.6
	 * @since 1.7.0 Returns an empty string instead of a `<!--nextpage-->` marker.
	 * @deprecated 2.3.0 The `nextpage` shortcode is no longer registered, see PChap_Shortcodes::remove_markers().
	 *
	 * @param array|string $attributes Shortcode attributes. No effect.
	 * @param string|null  $content    Shortcode content. No effect.
	 * @return string
	 */
	public static function nextpage( $attributes, $content = null ) {
		_deprecated_function( __METHOD__, '2.3.0', 'PChap_Shortcodes::remove_markers()' );
		unset( $attributes, $content );

		return '';
	}
}
