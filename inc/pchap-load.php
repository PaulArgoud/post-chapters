<?php
/**
 * Paginova loader: Multipage check, main function and start.
 *
 * Required by paginova.php once it knows that Post Chapters 2.x, which declares
 * the same functions, is not active.
 *
 * @package Paginova
 * @since 2.2.0 Moved from the main plugin file.
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Whether Multipage, the plugin Paginova replaces, is active on this site.
 *
 * Both plugins handle the same [nextpage] shortcode, Subpage block and options,
 * so Paginova stays idle while Multipage is active. The check reads the
 * list of active plugins, not the loaded classes only: plugins load in
 * alphabetical order, and paginova/ loads before sgr-nextpage-titles/.
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
		// Like wp_get_active_and_valid_plugins(), ignore an entry whose file is gone
		// (e.g. a Multipage folder deleted over FTP while still active).
		if ( is_string( $plugin ) && 'sgr-nextpage-titles.php' === basename( $plugin ) && file_exists( WP_PLUGIN_DIR . '/' . $plugin ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Admin notice shown while Multipage keeps Paginova idle.
 *
 * @since 2.0.0
 */
function pchap_multipage_active_notice() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	pchap_load_idle_textdomain();

	printf(
		'<div class="notice notice-warning"><p><strong>%1$s</strong> %2$s</p><p>%3$s</p></div>',
		esc_html__( 'Paginova is not running yet.', 'paginova' ),
		esc_html__( 'Deactivate Multipage: Paginova replaces it and takes over its settings and content.', 'paginova' ),
		esc_html__( 'To remove Multipage afterwards, delete its folder from the server rather than from the Plugins screen: Multipage 1.5.15 and later erase, when deleted from the Plugins screen, the settings and the chapter index that Paginova uses.', 'paginova' )
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
 * @return PChap Paginova instance.
 */
function pchap() {
	return PChap::instance();
}

require_once plugin_dir_path( PCHAP__PLUGIN_FILE ) . 'class-pchap.php';

// Start.
pchap();
