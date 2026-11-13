<?php
/**
 * Plugin Name:       Paginova – Chapters for Long Posts
 * Plugin URI:        https://github.com/PaulArgoud/paginova
 * Description:       Split long posts into chapters, each on its own page with its own URL and title, with a table of contents and chapter navigation. Works with the block editor and the classic editor. Formerly Post Chapters, successor of Multipage.
 * Author:            Paul ARGOUD, based on Multipage by SGr33n (aka Sergio De Falco, Envire Web Solutions).
 * Version:           2.2.0
 * Author URI:        https://paul.argoud.net
 * Text Domain:       paginova
 * Domain Path:       /languages/
 * License:           GPL v3
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Requires at least: 6.3
 * Requires PHP:      7.4
 * Update URI:        https://github.com/PaulArgoud/paginova
 *
 * @package Paginova
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Whether Post Chapters 2.x, the former name of Paginova, is active on this site.
 *
 * Post Chapters declares the same functions, classes and constants: Paginova
 * stays idle while it is active, before declaring anything Post Chapters 2.x
 * also declares. The check reads the list of active plugins: paginova/ loads
 * before post-chapters/.
 *
 * @since 2.2.0
 *
 * @return bool
 */
function pchap_is_post_chapters_active() {
	$active = (array) get_option( 'active_plugins', array() );
	if ( is_multisite() ) {
		$active = array_merge( $active, array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ) );
	}

	foreach ( $active as $plugin ) {
		if ( is_string( $plugin ) && 'post-chapters.php' === basename( $plugin ) && file_exists( WP_PLUGIN_DIR . '/' . $plugin ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Load the bundled translations for the notices shown while Paginova is idle.
 *
 * The plugin text domain is only set up when the plugin runs. Without bundled
 * translations (WordPress.org build), WordPress loads the language pack just in time.
 *
 * @since 2.2.0
 */
function pchap_load_idle_textdomain() {
	$mofile = __DIR__ . '/languages/paginova-' . determine_locale() . '.mo';

	if ( ! is_textdomain_loaded( 'paginova' ) && is_readable( $mofile ) ) {
		load_textdomain( 'paginova', $mofile );
	}
}

/**
 * Admin notice shown while Post Chapters 2.x keeps Paginova idle.
 *
 * @since 2.2.0
 */
function pchap_post_chapters_active_notice() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	pchap_load_idle_textdomain();

	printf(
		'<div class="notice notice-warning"><p><strong>%1$s</strong> %2$s</p><p>%3$s</p></div>',
		esc_html__( 'Paginova is not running yet.', 'paginova' ),
		esc_html__( 'Deactivate Post Chapters: Paginova is its new name and takes over its settings and content.', 'paginova' ),
		esc_html__( 'To remove Post Chapters afterwards, delete its folder from the server rather than from the Plugins screen: deleting it from the Plugins screen erases the settings and the chapter index that Paginova uses.', 'paginova' )
	);
}

// Post Chapters 2.x already loaded (e.g. as a must-use plugin), or active.
if ( function_exists( 'pchap' ) || defined( 'PCHAP_VERSION' ) || pchap_is_post_chapters_active() ) {
	add_action( 'admin_notices', 'pchap_post_chapters_active_notice' );
	add_action( 'network_admin_notices', 'pchap_post_chapters_active_notice' );
	return;
}

define( 'PCHAP_VERSION', '2.2.0' );
define( 'PCHAP__PLUGIN_FILE', __FILE__ );

// Everything Post Chapters 2.x also declares lives in this file, compiled only
// now: PHP declares the functions of a file when it compiles it, even below a return.
require_once __DIR__ . '/inc/pchap-load.php';
