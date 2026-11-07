<?php
/**
 * Subpage index rebuild tests.
 *
 * @package Multipage
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Test classes use the required Test_Mpp_ prefix and tests apply core and third-party hooks.

/**
 * Function mpp_add_post_multipage_meta(), batching, the WP-Cron routine and the lock.
 *
 * @group multipage
 * @group rebuild
 */
class Test_Mpp_Rebuild extends Mpp_UnitTestCase {

	/**
	 * Multipage content.
	 *
	 * @var string
	 */
	const MULTIPAGE = "Intro\n\n[nextpage title=\"Deux\"]\n\nTwo";

	/**
	 * Clean up the rebuild state.
	 */
	public function tear_down() {
		mpp_rebuild_release_lock();
		wp_clear_scheduled_hook( mpp_get_rebuild_cron_hook() );
		parent::tear_down();
	}

	/**
	 * Delete every `_mpp_data` meta, without going through save_post.
	 */
	private function delete_all_meta() {
		delete_metadata( 'post', 0, '_mpp_data', '', true );
	}

	/**
	 * Create the fixture posts, then drop the meta written by save_post.
	 *
	 * @return array Post IDs by role.
	 */
	private function create_fixture() {
		$ids = array(
			'publish' => $this->create_post( self::MULTIPAGE ),
			'draft'   => $this->create_post( self::MULTIPAGE, array( 'post_status' => 'draft' ) ),
			'block'   => $this->create_post( Mpp_Test_Corpus::items()['blocks 1.7']['content'] ),
			'plain'   => $this->create_post( 'Plain post' ),
			'stale'   => $this->create_post( 'Was multipage once' ),
		);

		// A revision and an auto-draft holding markers.
		wp_update_post(
			array(
				'ID'           => $ids['publish'],
				'post_content' => self::MULTIPAGE . "\n\n[nextpage title=\"Trois\"]\n\nThree",
			)
		);
		$revisions       = wp_get_post_revisions( $ids['publish'] );
		$ids['revision'] = (int) key( $revisions );

		$ids['auto-draft'] = self::factory()->post->create(
			array(
				'post_status'  => 'auto-draft',
				'post_content' => self::MULTIPAGE,
			)
		);

		$this->delete_all_meta();

		// Stale meta on a plain post (e.g. edited while the plugin was inactive).
		add_post_meta( $ids['stale'], '_mpp_data', array( 'intro' => '%%intro%%' ) );

		return $ids;
	}

	/**
	 * Full rebuild: stats, drafts included, revisions/auto-drafts excluded, stale meta deleted.
	 */
	public function test_full_rebuild() {
		$ids = $this->create_fixture();

		$this->assertSame( 4, mpp_count_postmeta_rebuild_candidates(), 'publish, draft, block and stale.' );

		$stats = mpp_add_post_multipage_meta();

		$this->assertSame( 4, $stats['processed'] );
		$this->assertSame( 3, $stats['updated'] );
		$this->assertSame( 1, $stats['deleted'] );
		$this->assertSame( 0, $stats['unchanged'] );
		$this->assertTrue( $stats['done'] );

		$this->assertSame( Multipage_Parser::multipage_return_array( get_post( $ids['publish'] )->post_content ), get_post_meta( $ids['publish'], '_mpp_data', true ) );
		$this->assertNotEmpty( get_post_meta( $ids['draft'], '_mpp_data', true ) );
		$this->assertNotEmpty( get_post_meta( $ids['block'], '_mpp_data', true ) );
		$this->assertSame( array(), get_post_meta( $ids['plain'], '_mpp_data', false ) );
		$this->assertSame( array(), get_post_meta( $ids['stale'], '_mpp_data', false ), 'Stale meta deleted.' );
		$this->assertSame( array(), get_post_meta( $ids['revision'], '_mpp_data', false ), 'Revisions excluded.' );
		$this->assertSame( array(), get_post_meta( $ids['auto-draft'], '_mpp_data', false ), 'Auto-drafts excluded.' );

		$this->assertGreaterThan( 0, (int) get_option( '_mpp-postmeta-built' ) );

		// Second run: everything is up to date.
		$stats = mpp_add_post_multipage_meta( false );
		$this->assertSame( 3, $stats['processed'] );
		$this->assertSame( 3, $stats['unchanged'] );
		$this->assertSame( 0, $stats['updated'] );
		$this->assertSame( 0, $stats['deleted'] );
	}

	/**
	 * The index stats match the fixture.
	 */
	public function test_index_stats() {
		$this->create_fixture();

		$this->assertSame(
			array(
				'multipage_posts' => 3,
				'indexed_posts'   => 1,
				'missing_index'   => 3,
				'stale_index'     => 1,
			),
			mpp_get_postmeta_index_stats()
		);

		mpp_add_post_multipage_meta();

		$this->assertSame(
			array(
				'multipage_posts' => 3,
				'indexed_posts'   => 3,
				'missing_index'   => 0,
				'stale_index'     => 0,
			),
			mpp_get_postmeta_index_stats()
		);
	}

	/**
	 * Backslashes survive the rebuild, and outdated meta is replaced.
	 */
	public function test_rebuild_updates_outdated_meta_and_keeps_backslashes() {
		$post_id = $this->create_post( "Intro\n\n" . Mpp_Test_Corpus::block( 'a\\b' ) . "\n\nTwo" );
		update_post_meta( $post_id, '_mpp_data', array( 'old' => 'Old' ) );
		add_post_meta( $post_id, '_mpp_data', array( 'duplicate' => 'Row' ) );

		$stats = mpp_add_post_multipage_meta( false );

		$this->assertSame( 1, $stats['updated'] );
		$rows = get_post_meta( $post_id, '_mpp_data', false );
		$this->assertCount( 1, $rows, 'Duplicated rows are merged.' );
		$this->assertSame(
			array(
				'intro' => '%%intro%%',
				'ab'    => 'a\\b',
			),
			$rows[0]
		);
	}

	/**
	 * Dry run: counts only.
	 */
	public function test_dry_run() {
		$ids = $this->create_fixture();

		$stats = mpp_run_postmeta_rebuild( array( 'dry_run' => true ) );

		$this->assertSame( 3, $stats['updated'] );
		$this->assertSame( 1, $stats['deleted'] );
		$this->assertSame( array(), get_post_meta( $ids['publish'], '_mpp_data', false ) );
		$this->assertNotEmpty( get_post_meta( $ids['stale'], '_mpp_data', false ) );
	}

	/**
	 * Batching with a small batch size (mpp_rebuild_postmeta_batch_size filter).
	 */
	public function test_batching() {
		$ids = array();
		for ( $i = 0; $i < 5; $i++ ) {
			$ids[] = $this->create_post( self::MULTIPAGE );
		}
		$this->delete_all_meta();

		add_filter(
			'mpp_rebuild_postmeta_batch_size',
			static function () {
				return 2;
			}
		);
		$this->assertSame( 2, mpp_get_rebuild_batch_size() );

		$batches = array();
		$stats   = mpp_run_postmeta_rebuild(
			array(
				'callback' => static function ( $batch ) use ( &$batches ) {
					$batches[] = $batch['processed'];
				},
			)
		);

		$this->assertSame( array( 2, 2, 1 ), $batches );
		$this->assertSame( 3, $stats['batches'] );
		$this->assertSame( 5, $stats['processed'] );
		$this->assertSame( 5, $stats['updated'] );
		$this->assertSame( max( $ids ), $stats['cursor'] );
		$this->assertTrue( $stats['done'] );

		foreach ( $ids as $id ) {
			$this->assertNotEmpty( get_post_meta( $id, '_mpp_data', true ) );
		}

		// A single batch, after a cursor.
		$this->delete_all_meta();
		$batch = mpp_process_postmeta_batch( $ids[2], 2 );
		$this->assertSame( 2, $batch['processed'] );
		$this->assertSame( $ids[4], $batch['last_id'] );
		$this->assertSame( array(), get_post_meta( $ids[0], '_mpp_data', false ) );
		$this->assertNotEmpty( get_post_meta( $ids[3], '_mpp_data', true ) );
	}

	/**
	 * The batch size is never below 1.
	 */
	public function test_batch_size_minimum() {
		add_filter( 'mpp_rebuild_postmeta_batch_size', '__return_zero' );

		$this->assertSame( 1, mpp_get_rebuild_batch_size() );
	}

	/**
	 * Scheduling: state saved, a single event, no duplicates.
	 */
	public function test_schedule_rebuild() {
		$hook = mpp_get_rebuild_cron_hook();

		$this->assertFalse( mpp_get_postmeta_rebuild_state() );
		$this->assertFalse( mpp_ensure_postmeta_rebuild_event(), 'Nothing to schedule without a pending rebuild.' );

		$this->assertTrue( mpp_schedule_postmeta_rebuild() );
		$this->assertTrue( mpp_schedule_postmeta_rebuild() );
		$this->assertTrue( mpp_schedule_postmeta_rebuild( false ) );

		$this->assertSame( 1, $this->count_cron_events( $hook ) );
		$state = mpp_get_postmeta_rebuild_state();
		$this->assertSame( 0, $state['cursor'] );
		$this->assertGreaterThan( 0, $state['started'] );

		// restart = false keeps a pending cursor.
		mpp_save_postmeta_rebuild_state(
			array(
				'cursor'    => 42,
				'processed' => 3,
			)
		);
		mpp_schedule_postmeta_rebuild( false );
		$this->assertSame( 42, mpp_get_postmeta_rebuild_state()['cursor'] );

		mpp_schedule_postmeta_rebuild( true );
		$this->assertSame( 0, mpp_get_postmeta_rebuild_state()['cursor'] );
	}

	/**
	 * The WP-Cron routine processes everything and completes the rebuild.
	 */
	public function test_cron_routine_completes() {
		$ids  = $this->create_fixture();
		$hook = mpp_get_rebuild_cron_hook();

		add_filter(
			'mpp_rebuild_postmeta_batch_size',
			static function () {
				return 2;
			}
		);

		mpp_schedule_postmeta_rebuild();
		$this->assertSame( 1, $this->count_cron_events( $hook ) );

		do_action( $hook );

		$this->assertFalse( mpp_get_postmeta_rebuild_state(), 'State deleted.' );
		$this->assertSame( 0, $this->count_cron_events( $hook ), 'No event left (watchdog removed).' );
		$this->assertFalse( mpp_is_rebuild_locked(), 'Lock released.' );
		$this->assertFalse( _mpp_rebuild_held_lock() );

		$last = get_option( '_mpp-rebuild-last' );
		$this->assertSame( 4, $last['processed'] );
		$this->assertSame( 3, $last['updated'] );
		$this->assertSame( 1, $last['deleted'] );
		$this->assertGreaterThan( 0, (int) get_option( '_mpp-postmeta-built' ) );

		$this->assertNotEmpty( get_post_meta( $ids['draft'], '_mpp_data', true ) );
		$this->assertSame( array(), get_post_meta( $ids['stale'], '_mpp_data', false ) );
	}

	/**
	 * The WP-Cron routine does nothing without a pending rebuild.
	 */
	public function test_cron_routine_without_state() {
		$post_id = $this->create_post( self::MULTIPAGE );
		$this->delete_all_meta();

		mpp_cron_rebuild_postmeta();

		$this->assertSame( array(), get_post_meta( $post_id, '_mpp_data', false ) );
		$this->assertSame( 0, $this->count_cron_events( mpp_get_rebuild_cron_hook() ) );
	}

	/**
	 * A run that exceeds its time budget saves its cursor and reschedules itself.
	 */
	public function test_cron_routine_resumes_after_time_budget() {
		$ids = array();
		for ( $i = 0; $i < 3; $i++ ) {
			$ids[] = $this->create_post( self::MULTIPAGE );
		}
		$this->delete_all_meta();
		$hook = mpp_get_rebuild_cron_hook();

		add_filter(
			'mpp_rebuild_postmeta_batch_size',
			static function () {
				return 1;
			}
		);
		add_filter( 'mpp_rebuild_cron_time_limit', '__return_zero' ); // Clamped to 1 second.

		// Make the first batch exceed the 1 second budget.
		$slowed = false;
		add_filter(
			'mpp_parser_is_meaningful_content',
			static function ( $meaningful ) use ( &$slowed ) {
				if ( ! $slowed ) {
					$slowed = true;
					usleep( 1100000 );
				}
				return $meaningful;
			}
		);

		mpp_schedule_postmeta_rebuild();
		mpp_cron_rebuild_postmeta();

		$state = mpp_get_postmeta_rebuild_state();
		$this->assertIsArray( $state, 'Rebuild still pending.' );
		$this->assertSame( $ids[0], $state['cursor'] );
		$this->assertSame( 1, $state['processed'] );
		$this->assertSame( 1, $this->count_cron_events( $hook ), 'Next run scheduled, watchdog removed.' );
		$this->assertFalse( mpp_is_rebuild_locked() );
		$this->assertNotEmpty( get_post_meta( $ids[0], '_mpp_data', true ) );
		$this->assertSame( array(), get_post_meta( $ids[2], '_mpp_data', false ) );

		// The next run finishes.
		mpp_cron_rebuild_postmeta();

		$this->assertFalse( mpp_get_postmeta_rebuild_state() );
		$this->assertSame( 0, $this->count_cron_events( $hook ) );
		$this->assertSame( 3, get_option( '_mpp-rebuild-last' )['processed'], 'Totals carried over the runs.' );
		$this->assertNotEmpty( get_post_meta( $ids[2], '_mpp_data', true ) );
	}

	/**
	 * A fresh lock held by another process: the run is postponed.
	 */
	public function test_cron_routine_with_lock_held_elsewhere() {
		global $wpdb;

		$post_id = $this->create_post( self::MULTIPAGE );
		$this->delete_all_meta();
		$hook = mpp_get_rebuild_cron_hook();

		mpp_schedule_postmeta_rebuild();
		wp_clear_scheduled_hook( $hook );

		$wpdb->insert(
			$wpdb->options,
			array(
				'option_name'  => '_mpp-rebuild-lock',
				'option_value' => time() . '|other-process',
				'autoload'     => 'off',
			)
		);
		$this->assertTrue( mpp_is_rebuild_locked() );

		mpp_cron_rebuild_postmeta();

		$this->assertSame( array(), get_post_meta( $post_id, '_mpp_data', false ), 'Nothing processed.' );
		$this->assertIsArray( mpp_get_postmeta_rebuild_state(), 'Still pending.' );
		$this->assertSame( 1, $this->count_cron_events( $hook ), 'Retried later.' );
		$next = wp_next_scheduled( $hook );
		$this->assertGreaterThanOrEqual( time() + MINUTE_IN_SECONDS - 5, $next );
		$this->assertSame( time() . '|other-process', $wpdb->get_var( "SELECT option_value FROM {$wpdb->options} WHERE option_name = '_mpp-rebuild-lock'" ), 'The other lock is left alone.' );
	}

	/**
	 * A stale lock (dead process) is taken over.
	 */
	public function test_cron_routine_takes_over_stale_lock() {
		global $wpdb;

		$post_id = $this->create_post( self::MULTIPAGE );
		$this->delete_all_meta();

		mpp_schedule_postmeta_rebuild();

		$wpdb->insert(
			$wpdb->options,
			array(
				'option_name'  => '_mpp-rebuild-lock',
				'option_value' => ( time() - mpp_get_rebuild_lock_ttl() - 10 ) . '|dead-process',
				'autoload'     => 'off',
			)
		);
		$this->assertFalse( mpp_is_rebuild_locked() );

		mpp_cron_rebuild_postmeta();

		$this->assertNotEmpty( get_post_meta( $post_id, '_mpp_data', true ) );
		$this->assertFalse( mpp_get_postmeta_rebuild_state() );
		$this->assertNull( $wpdb->get_var( "SELECT option_value FROM {$wpdb->options} WHERE option_name = '_mpp-rebuild-lock'" ), 'Lock released.' );
	}

	/**
	 * Lock primitives.
	 */
	public function test_lock_primitives() {
		global $wpdb;

		$this->assertFalse( mpp_is_rebuild_locked() );
		$this->assertFalse( mpp_rebuild_refresh_lock(), 'Nothing to refresh.' );

		$this->assertTrue( mpp_rebuild_acquire_lock() );
		$this->assertTrue( mpp_is_rebuild_locked() );
		$held = _mpp_rebuild_held_lock();
		$this->assertIsString( $held );

		// A second acquisition fails while the lock is fresh.
		$this->assertFalse( mpp_rebuild_acquire_lock() );
		$this->assertSame( $held, _mpp_rebuild_held_lock() );

		$this->assertTrue( mpp_rebuild_refresh_lock() );

		// Taken over by another process: refresh fails and release leaves it alone.
		$wpdb->update( $wpdb->options, array( 'option_value' => time() . '|thief' ), array( 'option_name' => '_mpp-rebuild-lock' ) );
		$this->assertFalse( mpp_rebuild_refresh_lock() );
		$this->assertFalse( _mpp_rebuild_held_lock() );
		mpp_rebuild_release_lock();
		$this->assertTrue( mpp_is_rebuild_locked() );

		$wpdb->delete( $wpdb->options, array( 'option_name' => '_mpp-rebuild-lock' ) );
		$this->assertFalse( mpp_is_rebuild_locked() );

		$this->assertTrue( mpp_rebuild_acquire_lock() );
		mpp_rebuild_release_lock();
		$this->assertFalse( mpp_is_rebuild_locked() );
		$this->assertFalse( _mpp_rebuild_held_lock() );
	}

	/**
	 * A run that loses its lock between batches stops.
	 */
	public function test_run_stops_when_lock_is_lost() {
		global $wpdb;

		for ( $i = 0; $i < 3; $i++ ) {
			$this->create_post( self::MULTIPAGE );
		}
		$this->delete_all_meta();

		$this->assertTrue( mpp_rebuild_acquire_lock() );

		$stats = mpp_run_postmeta_rebuild(
			array(
				'batch_size' => 1,
				'callback'   => static function () use ( $wpdb ) {
					$wpdb->update( $wpdb->options, array( 'option_value' => time() . '|thief' ), array( 'option_name' => '_mpp-rebuild-lock' ) );
				},
			)
		);

		$this->assertTrue( $stats['lock_lost'] );
		$this->assertFalse( $stats['done'] );
		$this->assertSame( 1, $stats['batches'] );
	}

	/**
	 * Activation schedules a rebuild, deactivation removes the event.
	 */
	public function test_activation_and_deactivation() {
		$hook = mpp_get_rebuild_cron_hook();

		mpp_activation();
		$this->assertSame( 1, $this->count_cron_events( $hook ) );
		$this->assertIsArray( mpp_get_postmeta_rebuild_state() );
		$this->assertSame( 1, did_action( 'mpp_activation' ) );

		mpp_deactivation();
		$this->assertSame( 0, $this->count_cron_events( $hook ) );
		$this->assertSame( 1, did_action( 'mpp_deactivation' ) );

		// Activation hooks are registered for the plugin basename.
		$this->assertSame( 10, has_action( 'activate_' . multipage()->basename, 'mpp_activation' ) );
		$this->assertSame( 10, has_action( 'deactivate_' . multipage()->basename, 'mpp_deactivation' ) );
	}
}
