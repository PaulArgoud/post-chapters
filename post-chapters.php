<?php
/**
 * Plugin Name:       Post Chapters
 * Plugin URI:        https://github.com/PaulArgoud/post-chapters
 * Description:       Split your long WordPress posts into chapters, each on its own page with its own title, and add an automatic table of contents. Supports Gutenberg blocks, classic editor shortcodes, SEO-friendly markup, and customizable navigation.
 * Author:            Paul ARGOUD, based on Multipage by SGr33n (aka Sergio De Falco, Envire Web Solutions).
 * Version:           2.0.0
 * Author URI:        https://paul.argoud.net
 * Text Domain:       post-chapters
 * Domain Path:       /languages/
 * License:           GPL v3
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Requires at least: 6.3
 * Requires PHP:      7.4
 * Update URI:        https://github.com/PaulArgoud/post-chapters
 *
 * @package Post Chapters
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

define( 'PCHAP_VERSION', '2.0.0' );
define( 'PCHAP__PLUGIN_FILE', __FILE__ );

/**
 * Whether Multipage, the plugin Post Chapters replaces, is active on this site.
 *
 * Both plugins handle the same [nextpage] shortcode, Subpage block and options,
 * so Post Chapters stays idle while Multipage is active. The check reads the
 * list of active plugins, not the loaded classes only: plugins load in
 * alphabetical order, and post-chapters/ loads before sgr-nextpage-titles/.
 *
 * @since 2.0.0
 *
 * @return bool
 */
function pchap_is_multipage_active() {
	if ( class_exists( 'Multipage', false ) ) {
		return true;
	}

	$active = (array) get_option( 'active_plugins', array() );
	if ( is_multisite() ) {
		$active = array_merge( $active, array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ) );
	}

	foreach ( $active as $plugin ) {
		if ( is_string( $plugin ) && 'sgr-nextpage-titles.php' === basename( $plugin ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Admin notice shown while Multipage keeps Post Chapters idle.
 *
 * @since 2.0.0
 */
function pchap_multipage_active_notice() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	// The plugin is idle: its text domain is not loaded yet.
	load_plugin_textdomain( 'post-chapters', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

	printf(
		'<div class="notice notice-warning"><p><strong>%1$s</strong> %2$s</p><p>%3$s</p></div>',
		esc_html__( 'Post Chapters is not running yet.', 'post-chapters' ),
		esc_html__( 'Deactivate Multipage: Post Chapters replaces it and takes over its settings and content.', 'post-chapters' ),
		esc_html__( 'To remove Multipage afterwards, delete its folder from the server rather than from the Plugins screen: Multipage 1.5.15 and later erase, when deleted from the Plugins screen, the settings and the chapter index that Post Chapters uses.', 'post-chapters' )
	);
}

if ( pchap_is_multipage_active() ) {
	add_action( 'admin_notices', 'pchap_multipage_active_notice' );
	add_action( 'network_admin_notices', 'pchap_multipage_active_notice' );
	return;
}

/**
 * The main function.
 *
 * @return PChap Post Chapters instance.
 */
function pchap() {
	return PChap::instance();
}

require_once plugin_dir_path( __FILE__ ) . 'class-pchap.php';

// Start.
pchap();
