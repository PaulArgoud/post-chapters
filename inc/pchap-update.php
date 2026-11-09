<?php
/**
 * Post Chapters Updater.
 *
 * Install/update routines, activation/deactivation hooks and the subpage
 * index (post meta `_mpp_data`) rebuild, run in batches either synchronously
 * (admin action, WP-CLI) or in the background through WP-Cron.
 *
 * @package Post Chapters
 * @subpackage Updater
 * @since 1.4
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/** Versions ******************************************************************/

/**
 * Return the Post Chapters database version installed on this site.
 *
 * Combines the raw version detected by PChap::versions() (999 for a
 * legacy 1.3 install still holding the `multipage` option) with the version
 * stored in `_mpp_db_version` by pchap_version_bump() (1000 for 1.4 to 1.6).
 *
 * @since 1.7.0
 *
 * @return int The installed database version, 0 on a fresh install.
 */
function pchap_get_installed_db_version() {
	return max( (int) pchap_get_db_version_raw(), (int) get_option( '_mpp_db_version', 0 ) );
}

/**
 * Is this a fresh installation of Post Chapters?
 *
 * If no database version is found (neither the legacy settings nor the
 * stored `_mpp_db_version`), we infer that this is the first installation.
 *
 * @since 1.4
 * @since 1.7.0 Also checks the stored `_mpp_db_version`, so 1.4+ sites are
 *              detected as updates.
 *
 * @return bool True if this is a fresh install, otherwise false.
 */
function pchap_is_install() {
	return 0 === pchap_get_installed_db_version();
}

/**
 * Is this a Post Chapters update?
 *
 * Determined by comparing the registered Post Chapters version to the version
 * number stored in the database. If the registered version is greater, it's
 * an update.
 *
 * @since 1.4
 *
 * @return bool True if update, otherwise false.
 */
function pchap_is_update() {

	// Current DB version of this site (per site in a multisite network).
	$current_db   = (int) get_option( '_mpp_db_version', 0 );
	$current_live = (int) pchap_get_db_version();

	return $current_db < $current_live;
}

/**
 * Determine whether Post Chapters is in the process of being deactivated.
 *
 * @since 1.4
 *
 * @param string $basename Post Chapters basename.
 * @return bool True if deactivating Post Chapters, false if not.
 */
function pchap_is_deactivation( $basename = '' ) {
	$mpp    = pchap();
	$action = '';

	// Check GET for single deactivation, POST for bulk deactivation.
	// phpcs:disable WordPress.Security.NonceVerification -- Read only, WordPress verifies the nonce.
	$get_action  = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : '';
	$post_action = isset( $_POST['action'] ) ? sanitize_text_field( wp_unslash( $_POST['action'] ) ) : '';

	if ( '' !== $get_action && '-1' !== $get_action ) {
		$action = $get_action;
	} elseif ( '' !== $post_action && '-1' !== $post_action ) {
		$action = $post_action;
	}

	// Bail if not deactivating.
	if ( ! in_array( $action, array( 'deactivate', 'deactivate-selected' ), true ) ) {
		return false;
	}

	// The plugin(s) being deactivated.
	if ( 'deactivate' === $action ) {
		$plugins = isset( $_GET['plugin'] ) ? array( sanitize_text_field( wp_unslash( $_GET['plugin'] ) ) ) : array();
	} else {
		$plugins = isset( $_POST['checked'] ) ? array_map( 'sanitize_text_field', wp_unslash( (array) $_POST['checked'] ) ) : array();
	}
	// phpcs:enable

	// Set basename if empty.
	if ( empty( $basename ) && ! empty( $mpp->basename ) ) {
		$basename = $mpp->basename;
	}

	// Bail if no basename.
	if ( empty( $basename ) ) {
		return false;
	}

	// Is Post Chapters being deactivated?
	return in_array( $basename, $plugins, true );
}

/**
 * Update the Post Chapters version stored in the database to the current version.
 *
 * @since 1.4
 */
function pchap_version_bump() {
	update_option( '_mpp_db_version', pchap_get_db_version() );
}

/**
 * Set up the Post Chapters updater.
 *
 * @since 1.4
 */
function pchap_setup_updater() {

	// Are we running an outdated version of Post Chapters?
	if ( ! pchap_is_update() ) {
		return;
	}

	pchap_version_updater();
}

/**
 * Initialize an update or installation of Post Chapters.
 *
 * Post Chapters's version updater looks at what the current database version is,
 * and runs whatever other code is needed - either the "update" or "install"
 * code.
 *
 * @since 1.4
 */
function pchap_version_updater() {

	// Get the installed database version.
	$raw_db_version = pchap_get_installed_db_version();

	if ( 0 === $raw_db_version ) {
		/*
		 * Fresh install. Index the posts that may already contain subpages
		 * (e.g. the plugin was deleted then installed again).
		 */
		pchap_schedule_postmeta_rebuild();

		/**
		 * Fires when Post Chapters is installed.
		 *
		 * @since 1.4
		 */
		pchap_do_action( 'pchap_on_install' );

	} else {

		// Version 1.4.
		if ( $raw_db_version < 1000 ) {
			pchap_update_to_1_4();
		}

		// Version 1.7.
		if ( $raw_db_version < 1700 ) {
			pchap_update_to_1_7();
		}

		// Version 2.0 (Multipage becomes Post Chapters).
		if ( $raw_db_version < 2000 ) {
			pchap_update_to_2_0();
		}

		/**
		 * Fires when Post Chapters is updated.
		 *
		 * @since 1.4
		 */
		pchap_do_action( 'pchap_on_update' );
	}

	/* All done! *************************************************************/

	// Bump the version.
	pchap_version_bump();
}

/**
 * 1.4 update routine.
 *
 * - Convert the legacy `multipage` settings.
 * - Index the subpages of old multipage posts (postmeta value).
 *
 * The existence checks pass an explicit `false` default to get_option():
 * without it, the defaults registered with register_setting() (through
 * filter_default_option()) would be returned for missing options and no
 * legacy value would be converted.
 *
 * @since 1.4
 * @since 1.7.0 The subpage index is rebuilt in the background (WP-Cron)
 *              instead of synchronously, after the settings conversion.
 */
function pchap_update_to_1_4() {
	// Convert old settings schema.
	$old_settings = get_option( 'multipage', false );

	if ( is_array( $old_settings ) ) {
		_pchap_convert_legacy_settings( $old_settings );
	}

	// Remove the old options.
	delete_option( 'multipage' );

	/*
	 * Add multipage postmeta value to old multipage posts, in the background:
	 * a synchronous full rebuild could exceed max_execution_time on admin_init.
	 * Keeps a rebuild already pending (pchap_update_to_1_7() restarts it anyway).
	 */
	pchap_schedule_postmeta_rebuild( false );
}

/**
 * Convert the legacy (1.3) `multipage` settings into the 1.4+ options.
 *
 * Options which already exist in the database are left untouched.
 *
 * @since 1.7.0
 * @access private
 *
 * @param array $old_settings The legacy `multipage` option value.
 */
function _pchap_convert_legacy_settings( $old_settings ) {

	$priorities  = array(
		'highest' => '100',
		'high'    => '50',
		'low'     => '10',
		'lowest'  => '5',
	);
	$row_labels  = array(
		'pages'   => 'page',
		'numbers' => 'number',
		'hidden'  => 'hidden',
	);
	$direct_keys = array(
		'toc-oofp'                => 'mpp-toc-only-on-the-first-page',
		'toc-position'            => 'mpp-toc-position',
		'toc-hide-header'         => 'mpp-hide-toc-header',
		'toc-comments-link'       => 'mpp-comments-toc-link',
		'disable-tinymce-buttons' => 'mpp-disable-tinymce-buttons',
	);

	foreach ( $old_settings as $option_key => $option_value ) {
		if ( ! isset( $option_value ) || '' === $option_value ) {
			continue;
		}

		if ( 'comments-oofp' === $option_key && false === get_option( 'mpp-comments-on-page', false ) ) {
			add_option( 'mpp-comments-on-page', 'first-page' );
		}

		if ( 'unhide-pagination' === $option_key && false === get_option( 'mpp-disable-standard-pagination', false ) && $option_value ) {
			add_option( 'mpp-disable-standard-pagination', 0 );
		}

		if ( isset( $direct_keys[ $option_key ] ) && false === get_option( $direct_keys[ $option_key ], false ) ) {
			add_option( $direct_keys[ $option_key ], $option_value );
		}

		if ( 'toc-page-labels' === $option_key && false === get_option( 'mpp-toc-row-labels', false ) && is_string( $option_value ) && isset( $row_labels[ $option_value ] ) ) {
			add_option( 'mpp-toc-row-labels', $row_labels[ $option_value ] );
		}

		if ( 'rewrite-title-priority' === $option_key && false === get_option( '_mpp-rewrite-title-priority', false ) && is_string( $option_value ) && isset( $priorities[ $option_value ] ) ) {
			add_option( '_mpp-rewrite-title-priority', $priorities[ $option_value ] );
		}

		if ( 'rewrite-content-priority' === $option_key && false === get_option( '_mpp-rewrite-content-priority', false ) && is_string( $option_value ) && isset( $priorities[ $option_value ] ) ) {
			add_option( '_mpp-rewrite-content-priority', $priorities[ $option_value ] );
		}
	}
}

/**
 * 1.7 update routine.
 *
 * The subpage parser changed in 1.7: rebuild the subpage index of every post
 * in the background (WP-Cron), so existing posts keep their current index
 * until they are processed.
 *
 * @since 1.7.0
 */
function pchap_update_to_1_7() {
	pchap_schedule_postmeta_rebuild();
}

/**
 * 2.0 update routine.
 *
 * Multipage became Post Chapters: the options and the `_mpp_data` meta keep
 * their names, but the WP-Cron event of the background rebuild was renamed
 * from mpp_rebuild_postmeta_batch to pchap_rebuild_postmeta_batch. Drop the
 * old event and schedule the new one when a rebuild is still pending.
 *
 * @since 2.0.0
 */
function pchap_update_to_2_0() {
	wp_clear_scheduled_hook( 'mpp_rebuild_postmeta_batch' );
	pchap_ensure_postmeta_rebuild_event();
}

/** Subpage index (post meta) rebuild ****************************************/

/**
 * Return the name of the WP-Cron hook processing the background rebuild.
 *
 * @since 1.7.0
 *
 * @return string
 */
function pchap_get_rebuild_cron_hook() {
	return 'pchap_rebuild_postmeta_batch';
}

/**
 * Return the number of posts processed per rebuild batch.
 *
 * @since 1.7.0
 *
 * @return int
 */
function pchap_get_rebuild_batch_size() {

	/**
	 * Filters the number of posts processed per subpage index rebuild batch.
	 *
	 * @since 1.7.0
	 *
	 * @param int $batch_size Number of posts per batch. Default 200.
	 */
	$batch_size = (int) pchap_apply_filters( 'pchap_rebuild_postmeta_batch_size', 200 );

	return max( 1, $batch_size );
}

/**
 * Return the SQL condition (with placeholders) and its arguments matching the
 * posts whose subpage index must be (re)built: posts containing a Post Chapters
 * marker and posts already holding a `_mpp_data` meta (stale meta is deleted).
 *
 * Revisions and auto-drafts are excluded. Drafts, pending, private, future
 * and trashed posts are included, as save_post also indexes them.
 *
 * @since 1.7.0
 *
 * @return array { 0: string SQL condition on alias `p`, 1: array arguments }
 */
function _pchap_rebuild_candidates_where() {
	global $wpdb;

	$where = "p.post_type <> %s AND p.post_status <> %s AND ( p.post_content LIKE %s OR p.post_content LIKE %s OR EXISTS ( SELECT 1 FROM {$wpdb->postmeta} pm WHERE pm.post_id = p.ID AND pm.meta_key = %s ) )";
	$args  = array(
		'revision',
		'auto-draft',
		'%' . $wpdb->esc_like( '[nextpage' ) . '%',
		'%' . $wpdb->esc_like( 'wp:multipage/subpage' ) . '%',
		'_mpp_data',
	);

	return array( $where, $args );
}

/**
 * Count the posts the subpage index rebuild will check.
 *
 * @since 1.7.0
 *
 * @param int $after_id Optional. Only count posts with an ID greater than this one.
 * @return int
 */
function pchap_count_postmeta_rebuild_candidates( $after_id = 0 ) {
	global $wpdb;

	list( $where, $args ) = _pchap_rebuild_candidates_where();
	array_unshift( $args, (int) $after_id );

	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- $where only holds placeholders and table names.
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} p WHERE p.ID > %d AND {$where}", $args ) );
}

/**
 * Process one batch of the subpage index rebuild.
 *
 * Parses the content of up to `$batch_size` posts (ordered by ID, after
 * `$after_id`) and updates or deletes their `_mpp_data` post meta.
 *
 * @since 1.7.0
 *
 * @param int  $after_id   Process posts with an ID greater than this one.
 * @param int  $batch_size Maximum number of posts to process.
 * @param bool $dry_run    Optional. Only count the changes. Default false.
 * @return array {
 *     @type int  $processed Posts checked.
 *     @type int  $updated   Posts whose index was added or changed.
 *     @type int  $deleted   Posts whose stale index was deleted.
 *     @type int  $unchanged Posts whose index was already up to date.
 *     @type int  $last_id   Last post ID processed (cursor for the next batch).
 *     @type bool $done      Whether there are no more posts to process.
 * }
 */
function pchap_process_postmeta_batch( $after_id, $batch_size, $dry_run = false ) {
	global $wpdb;

	$after_id   = max( 0, (int) $after_id );
	$batch_size = max( 1, (int) $batch_size );
	$stats      = array(
		'processed' => 0,
		'updated'   => 0,
		'deleted'   => 0,
		'unchanged' => 0,
		'last_id'   => $after_id,
		'done'      => false,
	);

	list( $where, $args ) = _pchap_rebuild_candidates_where();
	array_unshift( $args, $after_id );
	$args[] = $batch_size;

	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- $where only holds placeholders and table names; $args holds the values of all the placeholders, $where's included.
	$posts = $wpdb->get_results( $wpdb->prepare( "SELECT p.ID, p.post_content FROM {$wpdb->posts} p WHERE p.ID > %d AND {$where} ORDER BY p.ID ASC LIMIT %d", $args ) );

	if ( empty( $posts ) ) {
		$stats['done'] = true;
		return $stats;
	}

	// Load the existing meta of the batch in one query, without priming the meta cache.
	$ids          = array_map( 'intval', wp_list_pluck( $posts, 'ID' ) );
	$placeholders = implode( ', ', array_fill( 0, count( $ids ), '%d' ) );
	$meta_args    = array_merge( array( '_mpp_data' ), $ids );

	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- $placeholders only holds %d placeholders.
	$meta_rows = $wpdb->get_results( $wpdb->prepare( "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s AND post_id IN ( {$placeholders} )", $meta_args ) );

	$existing = array();
	foreach ( (array) $meta_rows as $row ) {
		$existing[ (int) $row->post_id ][] = $row->meta_value;
	}
	unset( $meta_rows );

	foreach ( $posts as $post ) {
		$post_id  = (int) $post->ID;
		$new_data = PChap_Parser::multipage_return_array( (string) $post->post_content );
		$old_rows = isset( $existing[ $post_id ] ) ? $existing[ $post_id ] : array();

		++$stats['processed'];
		$stats['last_id'] = $post_id;

		if ( empty( $new_data ) ) {
			if ( empty( $old_rows ) ) {
				++$stats['unchanged'];
				continue;
			}
			if ( ! $dry_run ) {
				delete_post_meta( $post_id, '_mpp_data' );
			}
			++$stats['deleted'];
			continue;
		}

		if ( 1 === count( $old_rows ) && maybe_unserialize( $old_rows[0] ) === $new_data ) {
			++$stats['unchanged'];
			continue;
		}

		if ( ! $dry_run ) {
			if ( count( $old_rows ) > 1 ) {
				// Duplicated rows: start again from a single one.
				delete_post_meta( $post_id, '_mpp_data' );
			}
			update_post_meta( $post_id, '_mpp_data', wp_slash( $new_data ) );
		}
		++$stats['updated'];
	}

	$stats['done'] = count( $posts ) < $batch_size;

	return $stats;
}

/**
 * Release the memory used by the rebuild between two batches.
 *
 * @since 1.7.0
 */
function pchap_rebuild_release_memory() {
	global $wpdb;

	// Drop the in-memory (runtime) object cache when the cache implementation supports it.
	if ( function_exists( 'wp_cache_supports' ) && function_exists( 'wp_cache_flush_runtime' ) && wp_cache_supports( 'flush_runtime' ) ) {
		wp_cache_flush_runtime();
	}

	// Saved queries (SAVEQUERIES) grow with every batch.
	if ( defined( 'SAVEQUERIES' ) && SAVEQUERIES && isset( $wpdb->queries ) ) {
		$wpdb->queries = array();
	}
}

/**
 * Run the subpage index rebuild for as many batches as allowed.
 *
 * @since 1.7.0
 *
 * @param array $args {
 *     Optional. Arguments.
 *
 *     @type int           $after_id   Start after this post ID. Default 0.
 *     @type int           $batch_size Posts per batch. Default pchap_get_rebuild_batch_size().
 *     @type int|float     $time_limit Stop after the batch exceeding this number of
 *                                     seconds. 0 for no limit. Default 0.
 *     @type bool          $dry_run    Only count the changes. Default false.
 *     @type array         $stats      Totals to start from (resumed rebuild).
 *     @type callable|null $callback   Called after each batch with ( $batch, $totals ).
 * }
 * When the current process holds the rebuild lock (pchap_rebuild_acquire_lock()),
 * the lock is refreshed after every batch, and the run stops with `lock_lost`
 * set to true if another process took it over.
 *
 * @return array Totals: processed, updated, deleted, unchanged, batches, cursor (last ID),
 *               done and lock_lost.
 */
function pchap_run_postmeta_rebuild( $args = array() ) {
	$args = wp_parse_args(
		$args,
		array(
			'after_id'   => 0,
			'batch_size' => pchap_get_rebuild_batch_size(),
			'time_limit' => 0,
			'dry_run'    => false,
			'stats'      => array(),
			'callback'   => null,
		)
	);

	$totals = array(
		'processed' => 0,
		'updated'   => 0,
		'deleted'   => 0,
		'unchanged' => 0,
		'batches'   => 0,
	);
	foreach ( array_keys( $totals ) as $key ) {
		if ( isset( $args['stats'][ $key ] ) ) {
			$totals[ $key ] = (int) $args['stats'][ $key ];
		}
	}
	$totals['cursor']    = max( 0, (int) $args['after_id'] );
	$totals['done']      = false;
	$totals['lock_lost'] = false;

	$time_limit = (float) $args['time_limit'];
	$started    = microtime( true );
	$has_lock   = false !== _pchap_rebuild_held_lock();

	do {
		$batch = pchap_process_postmeta_batch( $totals['cursor'], (int) $args['batch_size'], (bool) $args['dry_run'] );

		++$totals['batches'];
		foreach ( array( 'processed', 'updated', 'deleted', 'unchanged' ) as $key ) {
			$totals[ $key ] += $batch[ $key ];
		}
		$totals['cursor'] = $batch['last_id'];
		$totals['done']   = $batch['done'];

		if ( is_callable( $args['callback'] ) ) {
			call_user_func( $args['callback'], $batch, $totals );
		}

		pchap_rebuild_release_memory();

		if ( $totals['done'] ) {
			break;
		}

		// Keep the lock alive between batches; stop if another process took it over.
		if ( $has_lock && ! pchap_rebuild_refresh_lock() ) {
			$totals['lock_lost'] = true;
			break;
		}
	} while ( $time_limit <= 0 || ( microtime( true ) - $started ) < $time_limit );

	return $totals;
}

/**
 * Update WP postsmeta with a new value for multipage posts that will contain all relevant infos about subpages.
 *
 * Processes every post in batches (ID order) and deletes stale indexes.
 *
 * @since 1.4
 * @since 1.7.0 Runs in batches, deletes stale meta and returns statistics.
 *
 * @param bool $update_option Whether to update the '_mpp-postmeta-built' option. Default true.
 * @return array Totals: processed, updated, deleted, unchanged, batches, cursor and done.
 */
function pchap_add_post_multipage_meta( $update_option = true ) {
	$stats = pchap_run_postmeta_rebuild();

	if ( true === $update_option ) {
		update_option( '_mpp-postmeta-built', time() );
	}

	return $stats;
}

/**
 * Return the state of the pending background rebuild.
 *
 * @since 1.7.0
 *
 * @return array|false The state (cursor, processed, updated, deleted, unchanged,
 *                     batches, started), or false when no rebuild is pending.
 */
function pchap_get_postmeta_rebuild_state() {
	$state = get_option( '_mpp-rebuild-state', false );

	if ( ! is_array( $state ) ) {
		return false;
	}

	return wp_parse_args(
		$state,
		array(
			'cursor'    => 0,
			'processed' => 0,
			'updated'   => 0,
			'deleted'   => 0,
			'unchanged' => 0,
			'batches'   => 0,
			'started'   => 0,
		)
	);
}

/**
 * Save the state of a background rebuild.
 *
 * @since 1.7.0
 *
 * @param array $stats Totals returned by pchap_run_postmeta_rebuild().
 */
function pchap_save_postmeta_rebuild_state( $stats ) {
	$previous = pchap_get_postmeta_rebuild_state();
	$state    = array(
		'cursor'    => isset( $stats['cursor'] ) ? (int) $stats['cursor'] : 0,
		'processed' => isset( $stats['processed'] ) ? (int) $stats['processed'] : 0,
		'updated'   => isset( $stats['updated'] ) ? (int) $stats['updated'] : 0,
		'deleted'   => isset( $stats['deleted'] ) ? (int) $stats['deleted'] : 0,
		'unchanged' => isset( $stats['unchanged'] ) ? (int) $stats['unchanged'] : 0,
		'batches'   => isset( $stats['batches'] ) ? (int) $stats['batches'] : 0,
		'started'   => ( $previous && ! empty( $previous['started'] ) ) ? (int) $previous['started'] : time(),
	);

	update_option( '_mpp-rebuild-state', $state, false );
}

/**
 * Schedule a background rebuild of the subpage index.
 *
 * @since 1.7.0
 *
 * @param bool $restart Optional. Restart from the first post even if a rebuild
 *                      is already pending. Default true.
 * @return bool Whether a background run is scheduled.
 */
function pchap_schedule_postmeta_rebuild( $restart = true ) {
	if ( $restart || false === pchap_get_postmeta_rebuild_state() ) {
		update_option(
			'_mpp-rebuild-state',
			array(
				'cursor'    => 0,
				'processed' => 0,
				'updated'   => 0,
				'deleted'   => 0,
				'unchanged' => 0,
				'batches'   => 0,
				'started'   => time(),
			),
			false
		);
	}

	return pchap_ensure_postmeta_rebuild_event();
}

/**
 * Make sure the background rebuild event is scheduled while a rebuild is pending.
 *
 * @since 1.7.0
 *
 * @param int $delay Optional. Seconds before the next run. Default 0.
 * @return bool Whether a background run is scheduled.
 */
function pchap_ensure_postmeta_rebuild_event( $delay = 0 ) {
	$hook = pchap_get_rebuild_cron_hook();

	if ( false === pchap_get_postmeta_rebuild_state() ) {
		return false;
	}

	if ( wp_next_scheduled( $hook ) ) {
		return true;
	}

	return true === wp_schedule_single_event( time() + max( 0, (int) $delay ), $hook );
}

/**
 * Mark the subpage index rebuild as complete.
 *
 * @since 1.7.0
 *
 * @param array $stats Totals returned by pchap_run_postmeta_rebuild().
 */
function pchap_complete_postmeta_rebuild( $stats ) {
	update_option( '_mpp-postmeta-built', time() );
	update_option(
		'_mpp-rebuild-last',
		array(
			'time'      => time(),
			'processed' => isset( $stats['processed'] ) ? (int) $stats['processed'] : 0,
			'updated'   => isset( $stats['updated'] ) ? (int) $stats['updated'] : 0,
			'deleted'   => isset( $stats['deleted'] ) ? (int) $stats['deleted'] : 0,
		),
		false
	);
	delete_option( '_mpp-rebuild-state' );
	wp_clear_scheduled_hook( pchap_get_rebuild_cron_hook() );
}

/**
 * Return the lock duration of the subpage index rebuild, in seconds.
 *
 * A lock which has not been refreshed for this long is considered stale
 * (e.g. the process died). The owner refreshes it after every batch.
 *
 * @since 1.7.0
 *
 * @return int
 */
function pchap_get_rebuild_lock_ttl() {
	return 5 * MINUTE_IN_SECONDS;
}

/**
 * Get or set the value of the rebuild lock held by the current process.
 *
 * The lock value is "<timestamp>|<owner token>".
 *
 * @since 1.7.0
 * @access private
 *
 * @param string|false|null $value Optional. New value, false when released.
 *                                 Null (default) to only read it.
 * @return string|false The value held by this process, false if none.
 */
function _pchap_rebuild_held_lock( $value = null ) {
	static $held = false;

	if ( null !== $value ) {
		$held = is_string( $value ) ? $value : false;
	}

	return $held;
}

/**
 * Return the timestamp stored in a rebuild lock value.
 *
 * @since 1.7.0
 * @access private
 *
 * @param string|null $value Lock value ("<timestamp>|<token>", or a bare
 *                           timestamp), null when there is no lock.
 * @return int The timestamp, 0 when there is no lock.
 */
function _pchap_rebuild_lock_time( $value ) {
	if ( ! is_string( $value ) || '' === $value ) {
		return 0;
	}

	$parts = explode( '|', $value, 2 );

	return (int) $parts[0];
}

/**
 * Build a new rebuild lock value owned by the current process.
 *
 * @since 1.7.0
 * @access private
 *
 * @param string $token Optional. Owner token to keep. Default a new token.
 * @return string
 */
function _pchap_rebuild_new_lock_value( $token = '' ) {
	if ( '' === $token ) {
		$token = wp_generate_uuid4();
	}

	return time() . '|' . $token;
}

/**
 * Acquire the subpage index rebuild lock.
 *
 * Uses an atomic INSERT IGNORE on the options table (same approach as
 * WP_Upgrader::create_lock()), bypassing the options cache. A stale lock
 * (not refreshed for pchap_get_rebuild_lock_ttl() seconds) is taken over with
 * a compare-and-set UPDATE.
 *
 * @since 1.7.0
 *
 * @return bool Whether the lock was acquired.
 */
function pchap_rebuild_acquire_lock() {
	global $wpdb;

	$lock  = '_mpp-rebuild-lock';
	$value = _pchap_rebuild_new_lock_value();

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Atomic rebuild lock: the Options API has no compare-and-swap and its cache must be bypassed.
	$inserted = $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$wpdb->options} ( option_name, option_value, autoload ) VALUES ( %s, %s, 'off' )", $lock, $value ) );

	if ( $inserted ) {
		_pchap_rebuild_held_lock( $value );
		return true;
	}

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Atomic rebuild lock: the Options API has no compare-and-swap and its cache must be bypassed.
	$current = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $lock ) );

	if ( null === $current || _pchap_rebuild_lock_time( $current ) > time() - pchap_get_rebuild_lock_ttl() ) {
		return false;
	}

	// Stale lock: take it over, atomically.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Atomic rebuild lock: the Options API has no compare-and-swap and its cache must be bypassed.
	$updated = $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value = %s", $value, $lock, $current ) );

	if ( 1 !== (int) $updated ) {
		return false;
	}

	_pchap_rebuild_held_lock( $value );
	return true;
}

/**
 * Refresh the subpage index rebuild lock held by the current process.
 *
 * Long runs (WP-CLI, filtered time budgets) call it after every batch so the
 * lock never looks stale while its owner is alive.
 *
 * @since 1.7.0
 *
 * @return bool True if this process still owns the lock, false if it does not
 *              hold it or lost it (taken over by another process).
 */
function pchap_rebuild_refresh_lock() {
	global $wpdb;

	$held = _pchap_rebuild_held_lock();

	if ( false === $held ) {
		return false;
	}

	$lock  = '_mpp-rebuild-lock';
	$parts = explode( '|', $held, 2 );
	$token = isset( $parts[1] ) ? $parts[1] : '';
	$value = _pchap_rebuild_new_lock_value( $token );

	if ( $value === $held ) {
		// Same second: only check the ownership (an identical UPDATE affects no row).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Atomic rebuild lock: the Options API has no compare-and-swap and its cache must be bypassed.
		$current = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $lock ) );

		if ( $current !== $held ) {
			_pchap_rebuild_held_lock( false );
			return false;
		}

		return true;
	}

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Atomic rebuild lock: the Options API has no compare-and-swap and its cache must be bypassed.
	$updated = $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value = %s", $value, $lock, $held ) );

	if ( 1 !== (int) $updated ) {
		_pchap_rebuild_held_lock( false );
		return false;
	}

	_pchap_rebuild_held_lock( $value );
	return true;
}

/**
 * Release the subpage index rebuild lock.
 *
 * Only the lock owned by the current process is deleted: a lock taken over
 * by another process is left alone.
 *
 * @since 1.7.0
 */
function pchap_rebuild_release_lock() {
	global $wpdb;

	$held = _pchap_rebuild_held_lock();

	if ( false === $held ) {
		return;
	}

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Atomic rebuild lock: the Options API has no compare-and-swap and its cache must be bypassed.
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s", '_mpp-rebuild-lock', $held ) );

	_pchap_rebuild_held_lock( false );
}

/**
 * Is a subpage index rebuild currently running?
 *
 * @since 1.7.0
 *
 * @return bool
 */
function pchap_is_rebuild_locked() {
	global $wpdb;

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Atomic rebuild lock: the Options API has no compare-and-swap and its cache must be bypassed.
	$current = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", '_mpp-rebuild-lock' ) );

	return null !== $current && _pchap_rebuild_lock_time( $current ) > time() - pchap_get_rebuild_lock_ttl();
}

/**
 * WP-Cron callback: process the pending background rebuild.
 *
 * Processes batches during a short time budget, then reschedules itself
 * until every post has been processed. A watchdog event is scheduled first
 * so the rebuild resumes even if this run dies.
 *
 * @since 1.7.0
 */
function pchap_cron_rebuild_postmeta() {
	$state = pchap_get_postmeta_rebuild_state();
	$hook  = pchap_get_rebuild_cron_hook();

	if ( false === $state ) {
		return;
	}

	if ( ! pchap_rebuild_acquire_lock() ) {
		// Another process is working: check again later.
		pchap_ensure_postmeta_rebuild_event( MINUTE_IN_SECONDS );
		return;
	}

	// Watchdog: resume after the lock expires if this run dies.
	wp_schedule_single_event( time() + pchap_get_rebuild_lock_ttl() + MINUTE_IN_SECONDS, $hook );

	/**
	 * Filters the time budget of one background rebuild run, in seconds.
	 *
	 * At least one batch is always processed.
	 *
	 * @since 1.7.0
	 *
	 * @param int $time_limit Seconds. Default 15.
	 */
	$time_limit = (int) pchap_apply_filters( 'pchap_rebuild_cron_time_limit', 15 );

	try {
		$stats = pchap_run_postmeta_rebuild(
			array(
				'after_id'   => $state['cursor'],
				'time_limit' => max( 1, $time_limit ),
				'stats'      => $state,
			)
		);

		if ( $stats['lock_lost'] ) {
			// Another process owns the rebuild now: leave the state and the watchdog to it.
			return;
		}

		// Remove the watchdog.
		wp_clear_scheduled_hook( $hook );

		if ( $stats['done'] ) {
			pchap_complete_postmeta_rebuild( $stats );
		} else {
			pchap_save_postmeta_rebuild_state( $stats );
			wp_schedule_single_event( time(), $hook );
		}
	} finally {
		pchap_rebuild_release_lock();
	}
}
add_action( 'pchap_rebuild_postmeta_batch', 'pchap_cron_rebuild_postmeta' );

/**
 * Return statistics about the subpage index.
 *
 * Runs full-table queries: avoid calling it on every request.
 *
 * @since 1.7.0
 *
 * @return array {
 *     @type int $multipage_posts Posts containing a Post Chapters marker.
 *     @type int $indexed_posts   Posts holding a `_mpp_data` meta.
 *     @type int $missing_index   Posts containing a marker but no `_mpp_data` meta.
 *     @type int $stale_index     Posts holding a `_mpp_data` meta but no marker.
 * }
 */
function pchap_get_postmeta_index_stats() {
	global $wpdb;

	$markers = $wpdb->prepare(
		'( p.post_content LIKE %s OR p.post_content LIKE %s )',
		'%' . $wpdb->esc_like( '[nextpage' ) . '%',
		'%' . $wpdb->esc_like( 'wp:multipage/subpage' ) . '%'
	);
	$base    = $wpdb->prepare( 'p.post_type <> %s AND p.post_status <> %s', 'revision', 'auto-draft' );
	$has_key = $wpdb->prepare( "EXISTS ( SELECT 1 FROM {$wpdb->postmeta} pm WHERE pm.post_id = p.ID AND pm.meta_key = %s )", '_mpp_data' );

	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery -- Fragments are prepared above.
	$stats = array(
		'multipage_posts' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} p WHERE {$base} AND {$markers}" ),
		'indexed_posts'   => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} p WHERE {$base} AND {$has_key}" ),
		'missing_index'   => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} p WHERE {$base} AND {$markers} AND NOT {$has_key}" ),
		'stale_index'     => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} p WHERE {$base} AND NOT {$markers} AND {$has_key}" ),
	);
	// phpcs:enable

	return $stats;
}

/** Activation Actions ********************************************************/

/**
 * Fire activation hooks and events.
 *
 * Runs on Post Chapters activation. Posts edited while the plugin was inactive
 * have an outdated subpage index, so a background rebuild is scheduled.
 *
 * @since 1.5
 */
function pchap_activation() {

	pchap_schedule_postmeta_rebuild();

	/**
	 * Fires during the activation of Post Chapters.
	 *
	 * @since 1.5
	 */
	pchap_do_action( 'pchap_activation' );
}

/**
 * Fire deactivation hooks and events.
 *
 * Runs on Post Chapters deactivation.
 *
 * @since 1.5
 */
function pchap_deactivation() {

	// Stop the background rebuild, it is scheduled again on activation.
	wp_clear_scheduled_hook( pchap_get_rebuild_cron_hook() );

	/**
	 * Fires during the deactivation of Post Chapters.
	 *
	 * @since 1.5
	 */
	pchap_do_action( 'pchap_deactivation' );
}
