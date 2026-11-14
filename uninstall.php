<?php
/**
 * Paginova Uninstall.
 *
 * Removes all plugin data when the plugin is deleted via the WordPress admin:
 * options, the `_pchap_data` post meta, the scheduled background rebuild and the
 * entries of the plugin in the object cache (also a persistent one, Redis...).
 * The plugin writes nothing else: no table, no user or term meta, no
 * transient, no file.
 * The data of Multipage and Post Chapters (`mpp-*` options, `_mpp_data` meta),
 * which Paginova moves to its own names when it is updated, are left alone:
 * they belong to these plugins when Paginova never took them over.
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
		'pchap-hide-intro-title',
		'pchap-comments-on-page',
		'pchap-continue-or-prev-next',
		'pchap-disable-standard-pagination',
		'pchap-toc-only-on-the-first-page',
		'pchap-toc-position',
		'pchap-toc-row-labels',
		'pchap-hide-toc-header',
		'pchap-comments-toc-link',
		'pchap-disable-tinymce-buttons',
		'pchap-rewrite-title-priority',
		'pchap-rewrite-content-priority',
		// Internal state.
		'pchap-db-version',
		'pchap-postmeta-built',
		'pchap-rebuild-state',
		'pchap-rebuild-last',
		'pchap-rebuild-lock',
	);

	foreach ( $option_names as $option ) {
		delete_option( $option );
	}

	// Background rebuild, whatever the arguments of the events.
	wp_unschedule_hook( 'pchap_rebuild_postmeta_batch' );

	// Delete every _pchap_data post meta entry. The core function also clears the post meta
	// cache of each post: with a persistent object cache, deleting the rows alone would
	// leave the index readable until the cache entries expire.
	delete_post_meta_by_key( '_pchap_data' );

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
