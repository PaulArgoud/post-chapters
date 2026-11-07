<?php
/**
 * Multipage Uninstall.
 *
 * Removes all plugin data when the plugin is deleted via the WordPress admin:
 * options, the `_mpp_data` post meta and the scheduled background rebuild.
 * On multisite, the data of every site of the network is removed.
 *
 * The plugin is not loaded when this file runs: it must stay self-contained.
 *
 * @package Multipage
 * @since 1.5.15
 */

// Exit if not called by WordPress.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Remove the Multipage data of the current site.
 *
 * @since 1.7.0
 */
$mpp_uninstall_site = static function () {
	global $wpdb;

	$option_names = array(
		// Settings.
		'mpp-hide-intro-title',
		'mpp-comments-on-page',
		'mpp-continue-or-prev-next',
		'mpp-disable-standard-pagination',
		'mpp-toc-only-on-the-first-page',
		'mpp-toc-position',
		'mpp-toc-row-labels',
		'mpp-hide-toc-header',
		'mpp-comments-toc-link',
		'mpp-disable-tinymce-buttons',
		'_mpp-rewrite-title-priority',
		'_mpp-rewrite-content-priority',
		// Internal state.
		'_mpp_db_version',
		'_mpp-postmeta-built',
		'_mpp-rebuild-state',
		'_mpp-rebuild-last',
		'_mpp-rebuild-lock',
		// Legacy (1.3) settings.
		'multipage',
	);

	foreach ( $option_names as $option ) {
		delete_option( $option );
	}

	// Background rebuild.
	wp_clear_scheduled_hook( 'mpp_rebuild_postmeta_batch' );

	// Delete all _mpp_data post meta entries in one query.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- One-off uninstall cleanup of every post, no cache to keep.
	$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => '_mpp_data' ) );
};

if ( is_multisite() ) {
	$mpp_offset = 0;
	$mpp_number = 100;

	do {
		$mpp_site_ids = get_sites(
			array(
				'fields'                 => 'ids',
				'number'                 => $mpp_number,
				'offset'                 => $mpp_offset,
				'orderby'                => 'id',
				'order'                  => 'ASC',
				'update_site_meta_cache' => false,
			)
		);

		foreach ( $mpp_site_ids as $mpp_site_id ) {
			switch_to_blog( (int) $mpp_site_id );
			$mpp_uninstall_site();
			restore_current_blog();
		}

		$mpp_offset    += $mpp_number;
		$mpp_site_count = count( $mpp_site_ids );
	} while ( $mpp_site_count === $mpp_number );
} else {
	$mpp_uninstall_site();
}
