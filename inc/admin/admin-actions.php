<?php
/**
 * Post Chapters Admin Actions.
 *
 * @package Post Chapters
 * @since 1.4
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

add_action( 'admin_init', 'pchap_admin_init' );

// Hook on to admin_init.
add_action( 'pchap_admin_init', 'pchap_setup_updater', 1000 );
add_action( 'pchap_admin_init', 'pchap_register_admin_settings' );
add_action( 'pchap_admin_init', 'pchap_admin_rebuild_postmeta_notice' );

// Subpage index rebuild action (admin-post.php).
add_action( 'admin_post_mpp_rebuild_postmeta', 'pchap_admin_rebuild_postmeta_handler' );
add_filter( 'removable_query_args', 'pchap_admin_removable_query_args' );

/**
 * Piggy back admin_init action.
 *
 * @since 1.4
 */
function pchap_admin_init() {

	/**
	 * Fires inside the pchap_admin_init function.
	 *
	 * @since 1.4
	 */
	pchap_do_action( 'pchap_admin_init' );
}

/**
 * Dedicated action to register admin settings.
 *
 * @since 1.4
 */
function pchap_register_admin_settings() {

	/**
	 * Fires inside the register_admin_settings function.
	 *
	 * @since 1.4
	 */
	pchap_do_action( 'pchap_register_admin_settings' );
}
