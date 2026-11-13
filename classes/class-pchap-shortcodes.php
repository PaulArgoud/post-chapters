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
 * Paginova shortcodes.
 *
 * @since 1.1
 */
class PChap_Shortcodes {

	/**
	 * Register shortcode handlers
	 *
	 * @since 1.1
	 */
	public static function init() {
		add_shortcode( 'nextpage', array( 'PChap_Shortcodes', 'nextpage' ) );
	}

	/**
	 * Render the [nextpage] shortcode.
	 *
	 * On multipage views the shortcodes are removed before rendering (the content is
	 * split into subpages beforehand). The handler only runs where the content was not
	 * split (feeds, REST API, excerpts, any other do_shortcode() caller): it renders
	 * nothing, so no stray page break marker or attribute text leaks into the output.
	 *
	 * @since 0.6
	 * @since 1.7.0 Returns an empty string instead of a `<!--nextpage-->` marker.
	 *
	 * @param array|string $attributes Shortcode attributes. No effect.
	 * @param string|null  $content    Shortcode content. No effect.
	 * @return string
	 */
	public static function nextpage( $attributes, $content = null ) {
		unset( $attributes, $content );

		return '';
	}
}
