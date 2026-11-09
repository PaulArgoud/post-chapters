<?php
/**
 * Post Chapters WP-CLI commands.
 *
 * @package Post Chapters
 * @subpackage CLI
 * @since 1.7.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

// Only load within WP-CLI.
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

if ( ! class_exists( 'PChap_CLI_Command' ) ) {

	/**
	 * Manage the Post Chapters chapter index.
	 *
	 * ## EXAMPLES
	 *
	 *     # Show the state of the chapter index.
	 *     $ wp post-chapters status
	 *
	 *     # Rebuild the chapter index of every post.
	 *     $ wp post-chapters rebuild
	 *
	 * @since 1.7.0
	 */
	class PChap_CLI_Command {

		/**
		 * Rebuild the chapter index (`_mpp_data` post meta) of every post.
		 *
		 * Parses every post containing a Post Chapters chapter (Subpage) block or a
		 * [nextpage] shortcode, updates its index, and deletes the index of
		 * posts which are no longer split. Revisions and auto-drafts are
		 * skipped. Any pending background rebuild is completed by this command.
		 *
		 * ## OPTIONS
		 *
		 * [--batch-size=<n>]
		 * : Number of posts processed per batch.
		 * ---
		 * default: 200
		 * ---
		 *
		 * [--dry-run]
		 * : Report what would change without writing anything.
		 *
		 * ## EXAMPLES
		 *
		 *     $ wp post-chapters rebuild
		 *     Rebuilding the chapter index  100% [==============] 0:01 / 0:01
		 *     Success: Chapter index rebuilt: 1250 posts checked, 12 updated, 3 stale entries removed, 1235 unchanged.
		 *
		 *     $ wp post-chapters rebuild --dry-run --batch-size=500
		 *
		 * @param array $args       Positional arguments (unused).
		 * @param array $assoc_args Associative arguments.
		 */
		public function rebuild( $args, $assoc_args ) {
			$batch_size = (int) \WP_CLI\Utils\get_flag_value( $assoc_args, 'batch-size', 200 );
			$dry_run    = (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', false );

			if ( $batch_size < 1 ) {
				WP_CLI::error( 'The --batch-size value must be a positive integer.' );
			}

			if ( ! class_exists( 'PChap_Parser' ) || ! function_exists( 'pchap_run_postmeta_rebuild' ) ) {
				WP_CLI::error( 'Post Chapters is not loaded on this site.' );
			}

			$locked = false;
			if ( ! $dry_run ) {
				$locked = pchap_rebuild_acquire_lock();
				if ( ! $locked ) {
					WP_CLI::error( 'A chapter index rebuild is already running (background or admin). Try again in a few minutes.' );
				}
			}

			$total    = pchap_count_postmeta_rebuild_candidates();
			$progress = \WP_CLI\Utils\make_progress_bar( $dry_run ? 'Checking the chapter index' : 'Rebuilding the chapter index', max( 1, $total ) );

			try {
				$stats = pchap_run_postmeta_rebuild(
					array(
						'batch_size' => $batch_size,
						'dry_run'    => $dry_run,
						'callback'   => static function ( $batch ) use ( $progress ) {
							if ( $batch['processed'] > 0 ) {
								$progress->tick( $batch['processed'] );
							}
						},
					)
				);

				if ( ! $dry_run && ! $stats['lock_lost'] ) {
					pchap_complete_postmeta_rebuild( $stats );
				}
			} finally {
				$progress->finish();
				if ( $locked ) {
					pchap_rebuild_release_lock();
				}
			}

			if ( ! empty( $stats['lock_lost'] ) ) {
				WP_CLI::error(
					sprintf(
						'The rebuild lock was taken over by another process after %d posts. That process continues the rebuild; run "wp post-chapters status" to follow it.',
						$stats['processed']
					)
				);
			}

			$message = sprintf(
				'%1$d %2$s checked, %3$d %4$s, %5$d stale %6$s %7$s, %8$d unchanged.',
				$stats['processed'],
				1 === (int) $stats['processed'] ? 'post' : 'posts',
				$stats['updated'],
				$dry_run ? 'to update' : 'updated',
				$stats['deleted'],
				1 === (int) $stats['deleted'] ? 'entry' : 'entries',
				$dry_run ? 'to remove' : 'removed',
				$stats['unchanged']
			);

			if ( $dry_run ) {
				WP_CLI::success( 'Dry run: ' . $message );
				return;
			}

			WP_CLI::success( 'Chapter index rebuilt: ' . $message );
		}

		/**
		 * Show the state of the chapter index.
		 *
		 * Counts the posts containing chapters, the posts holding an index, the
		 * posts whose index is missing or stale, and the pending background rebuild.
		 *
		 * ## OPTIONS
		 *
		 * [--format=<format>]
		 * : Render output in a particular format.
		 * ---
		 * default: table
		 * options:
		 *   - table
		 *   - json
		 *   - yaml
		 *   - csv
		 * ---
		 *
		 * ## EXAMPLES
		 *
		 *     $ wp post-chapters status
		 *     +-------------------+---------------------+
		 *     | key               | value               |
		 *     +-------------------+---------------------+
		 *     | multipage_posts   | 42                  |
		 *     | indexed_posts     | 42                  |
		 *     ...
		 *
		 * @param array $args       Positional arguments (unused).
		 * @param array $assoc_args Associative arguments.
		 */
		public function status( $args, $assoc_args ) {
			if ( ! function_exists( 'pchap_get_postmeta_index_stats' ) ) {
				WP_CLI::error( 'Post Chapters is not loaded on this site.' );
			}

			$format = \WP_CLI\Utils\get_flag_value( $assoc_args, 'format', 'table' );
			$stats  = pchap_get_postmeta_index_stats();
			$state  = pchap_get_postmeta_rebuild_state();
			$built  = (int) get_option( '_mpp-postmeta-built', 0 );
			$next   = wp_next_scheduled( pchap_get_rebuild_cron_hook() );

			$data = array(
				'multipage_posts'       => $stats['multipage_posts'],
				'indexed_posts'         => $stats['indexed_posts'],
				'missing_index'         => $stats['missing_index'],
				'stale_index'           => $stats['stale_index'],
				'pending_rebuild'       => false !== $state ? 'yes' : 'no',
				'pending_checked_posts' => false !== $state ? (int) $state['processed'] : 0,
				'pending_cursor'        => false !== $state ? (int) $state['cursor'] : 0,
				'next_background_run'   => $next ? gmdate( 'Y-m-d H:i:s', (int) $next ) . ' UTC' : '-',
				'rebuild_running'       => pchap_is_rebuild_locked() ? 'yes' : 'no',
				'last_rebuild'          => $built > DAY_IN_SECONDS ? gmdate( 'Y-m-d H:i:s', $built ) . ' UTC' : ( $built > 0 ? 'unknown date' : 'never' ),
				'db_version'            => (int) get_option( '_mpp_db_version', 0 ),
			);

			if ( 'table' === $format ) {
				$rows = array();
				foreach ( $data as $key => $value ) {
					$rows[] = array(
						'key'   => $key,
						'value' => $value,
					);
				}
				\WP_CLI\Utils\format_items( 'table', $rows, array( 'key', 'value' ) );
			} else {
				\WP_CLI\Utils\format_items( $format, array( $data ), array_keys( $data ) );
			}

			if ( $stats['missing_index'] > 0 || $stats['stale_index'] > 0 ) {
				WP_CLI::warning( 'The chapter index is out of date. Run "wp post-chapters rebuild" to fix it.' );
			}
		}
	}

	WP_CLI::add_command( 'post-chapters', 'PChap_CLI_Command' );

	// Multipage 1.x name, kept for existing scripts.
	WP_CLI::add_command( 'multipage', 'PChap_CLI_Command' );
}
