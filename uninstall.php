<?php
/**
 * Paginova Uninstall.
 *
 * Removes all plugin data when the plugin is deleted via the WordPress admin:
 * options, the `_mpp_data` post meta, the scheduled background rebuild and the
 * entries of the plugin in the object cache (also a persistent one, Redis...).
 * The plugin writes nothing else: no table, no user or term meta, no
 * transient, no file.
 * The option and meta names are the ones of Multipage, which Paginova
 * reuses: deleting Multipage (1.7 and earlier) from the admin removes the
 * Paginova data too, and the other way round.
 * On multisite, the data of every site of the network is removed.
 *
 * The plugin is not loaded when this file runs: it must stay self-contained.
 *
 * @package Paginova
 * @since 1.5.15
 */

// Exit if not called by WordPress.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Remove the Paginova data of the current site.
 *
 * @since 1.7.0
 */
$pchap_uninstall_site = static function () {
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

	// Background rebuild (and its Multipage 1.x name), whatever the arguments of the events.
	wp_unschedule_hook( 'pchap_rebuild_postmeta_batch' );
	wp_unschedule_hook( 'mpp_rebuild_postmeta_batch' );

	// Delete every _mpp_data post meta entry. The core function also clears the post meta
	// cache of each post: with a persistent object cache, deleting the rows alone would
	// leave the index readable until the cache entries expire.
	delete_post_meta_by_key( '_mpp_data' );

	// The parsed chapters cached by the plugin (a day at most, but gone now).
	if ( function_exists( 'wp_cache_flush_group' ) && function_exists( 'wp_cache_supports' ) && wp_cache_supports( 'flush_group' ) ) {
		wp_cache_flush_group( 'paginova' );
	}
};

if ( is_multisite() ) {
	$pchap_offset = 0;
	$pchap_number = 100;

	do {
		$pchap_site_ids = get_sites(
			array(
				'fields'                 => 'ids',
				'number'                 => $pchap_number,
				'offset'                 => $pchap_offset,
				'orderby'                => 'id',
				'order'                  => 'ASC',
				'update_site_meta_cache' => false,
			)
		);

		foreach ( $pchap_site_ids as $pchap_site_id ) {
			switch_to_blog( (int) $pchap_site_id );
			$pchap_uninstall_site();
			restore_current_blog();
		}

		$pchap_offset    += $pchap_number;
		$pchap_site_count = count( $pchap_site_ids );
	} while ( $pchap_site_count === $pchap_number );
} else {
	$pchap_uninstall_site();
}
