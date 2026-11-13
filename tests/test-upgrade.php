<?php
/**
 * Upgrade routine tests.
 *
 * @package Paginova
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Test classes use the required Test_PChap_ prefix and tests apply core and third-party hooks.

/**
 * Install and update routines: fresh install, 1.6 -> 1.7, 1.3 -> 1.7.
 *
 * @group multipage
 * @group upgrade
 */
class Test_PChap_Upgrade extends PChap_UnitTestCase {

	/**
	 * Raw database version detected at boot, restored after each test.
	 *
	 * @var int
	 */
	private $db_version_raw;

	/**
	 * Save the detected raw version.
	 */
	public function set_up() {
		parent::set_up();
		$this->db_version_raw = pchap()->db_version_raw;
	}

	/**
	 * Restore the detected raw version and clean the rebuild state.
	 */
	public function tear_down() {
		pchap()->db_version_raw = $this->db_version_raw;
		wp_clear_scheduled_hook( pchap_get_rebuild_cron_hook() );
		parent::tear_down();
	}

	/**
	 * Count the actions fired by the updater.
	 *
	 * @var int[]
	 */
	private $fired = array(
		'pchap_on_install' => 0,
		'pchap_on_update'  => 0,
	);

	/**
	 * Track the updater actions.
	 */
	private function track_actions() {
		foreach ( array_keys( $this->fired ) as $action ) {
			add_action(
				$action,
				function () use ( $action ) {
					++$this->fired[ $action ];
				}
			);
		}
	}

	/**
	 * Fresh install: version stored, a rebuild scheduled, pchap_on_install fired.
	 */
	public function test_fresh_install() {
		delete_option( '_mpp_db_version' );
		pchap()->db_version_raw = 0;
		$this->track_actions();

		$this->assertTrue( pchap_is_install() );
		$this->assertTrue( pchap_is_update() );

		pchap_setup_updater();

		$this->assertSame( 2000, (int) get_option( '_mpp_db_version' ) );
		$this->assertSame( 1, $this->fired['pchap_on_install'] );
		$this->assertSame( 0, $this->fired['pchap_on_update'] );
		$this->assertSame( 1, $this->count_cron_events( pchap_get_rebuild_cron_hook() ) );
		$this->assertFalse( pchap_is_update() );
		$this->assertFalse( pchap_is_install() );
	}

	/**
	 * 1.6 install (_mpp_db_version = 1000): the rebuild is scheduled exactly once.
	 */
	public function test_update_from_1_6_schedules_rebuild_once() {
		update_option( '_mpp_db_version', 1000 );
		pchap()->db_version_raw = 0;
		$this->track_actions();

		$post_id = $this->create_post( "Intro\n\n[nextpage title=\"Deux\"]\n\nTwo" );
		delete_post_meta( $post_id, '_mpp_data' );

		$this->assertFalse( pchap_is_install() );
		$this->assertTrue( pchap_is_update() );
		$this->assertSame( 1000, pchap_get_installed_db_version() );

		pchap_setup_updater();

		$hook = pchap_get_rebuild_cron_hook();
		$this->assertSame( 2000, (int) get_option( '_mpp_db_version' ) );
		$this->assertSame( 1, $this->fired['pchap_on_update'] );
		$this->assertSame( 0, $this->fired['pchap_on_install'] );
		$this->assertSame( 1, $this->count_cron_events( $hook ) );
		$state = pchap_get_postmeta_rebuild_state();
		$this->assertIsArray( $state );

		// The updater runs on every admin_init: nothing more happens.
		pchap_setup_updater();
		pchap_setup_updater();

		$this->assertSame( 1, $this->fired['pchap_on_update'] );
		$this->assertSame( 1, $this->count_cron_events( $hook ) );
		$this->assertSame( $state, pchap_get_postmeta_rebuild_state() );

		// The meta is built by the background routine.
		$this->assertSame( array(), get_post_meta( $post_id, '_mpp_data', false ) );
		do_action( $hook );
		$this->assertNotEmpty( get_post_meta( $post_id, '_mpp_data', true ) );
		$this->assertFalse( pchap_get_postmeta_rebuild_state() );
	}

	/**
	 * The 1.6 settings (stored as strings) are kept by the update.
	 */
	public function test_update_from_1_6_keeps_settings() {
		update_option( '_mpp_db_version', 1000 );
		pchap()->db_version_raw = 0;

		update_option( 'mpp-toc-position', 'bottom' );
		update_option( 'mpp-hide-toc-header', '1' );
		update_option( '_mpp-rewrite-title-priority', '50' );
		update_option( 'mpp-comments-on-page', 'last-page' );

		pchap_setup_updater();

		$this->assertSame( 'bottom', pchap_get_toc_position() );
		$this->assertTrue( pchap_hide_toc_header() );
		$this->assertSame( 50, pchap_get_rewrite_title_priority() );
		$this->assertSame( 'last-page', pchap_get_comments_on_page() );
	}

	/**
	 * The admin_init chain runs the updater (pchap_admin_init -> pchap_setup_updater).
	 */
	public function test_updater_is_hooked_on_admin_init() {
		$this->assertSame( 10, has_action( 'admin_init', 'pchap_admin_init' ) );
		$this->assertSame( 1000, has_action( 'pchap_admin_init', 'pchap_setup_updater' ) );
	}

	/**
	 * 1.3 install (legacy `multipage` option): settings converted, option removed, rebuild scheduled once.
	 */
	public function test_update_from_1_3() {
		delete_option( '_mpp_db_version' );
		foreach ( array_keys( pchap_get_option_definitions() ) as $option_name ) {
			delete_option( $option_name );
		}

		update_option(
			'multipage',
			array(
				'comments-oofp'            => 1,
				'unhide-pagination'        => 1,
				'toc-oofp'                 => 1,
				'toc-position'             => 'top-left',
				'toc-hide-header'          => 1,
				'toc-comments-link'        => 1,
				'toc-page-labels'          => 'pages',
				'rewrite-title-priority'   => 'high',
				'rewrite-content-priority' => 'lowest',
				'disable-tinymce-buttons'  => '',
			)
		);
		// As detected by PChap::versions() at boot when the legacy option exists.
		pchap()->db_version_raw = 999;
		$this->track_actions();

		$this->assertFalse( pchap_is_install() );
		$this->assertTrue( pchap_is_update() );

		pchap_setup_updater();

		$this->assertFalse( get_option( 'multipage', false ), 'Legacy option removed.' );
		$this->assertSame( 2000, (int) get_option( '_mpp_db_version' ) );
		$this->assertSame( 1, $this->fired['pchap_on_update'] );
		$this->assertSame( 1, $this->count_cron_events( pchap_get_rebuild_cron_hook() ) );

		$this->assertSame( 'first-page', pchap_get_comments_on_page() );
		$this->assertFalse( pchap_disable_standard_pagination() );
		$this->assertTrue( pchap_toc_only_on_the_first_page() );
		$this->assertSame( 'top-left', pchap_get_toc_position() );
		$this->assertTrue( pchap_hide_toc_header() );
		$this->assertTrue( pchap_comments_toc_link() );
		$this->assertSame( 'page', pchap_get_toc_row_labels() );
		$this->assertSame( 50, pchap_get_rewrite_title_priority() );
		$this->assertSame( 5, pchap_get_rewrite_content_priority() );
		$this->assertFalse( pchap_disable_tinymce_buttons(), 'Empty legacy values are skipped.' );
		$this->assertFalse( get_option( 'mpp-disable-tinymce-buttons', false ) );
	}

	/**
	 * Legacy conversion never overwrites options that already exist.
	 */
	public function test_legacy_conversion_keeps_existing_options() {
		update_option( 'mpp-toc-position', 'bottom' );
		update_option( 'mpp-toc-row-labels', 'hidden' );

		pchap_convert_legacy_settings(
			array(
				'toc-position'           => 'top-left',
				'toc-page-labels'        => 'numbers',
				'rewrite-title-priority' => 'unknown',
			)
		);

		$this->assertSame( 'bottom', get_option( 'mpp-toc-position' ) );
		$this->assertSame( 'hidden', get_option( 'mpp-toc-row-labels' ) );
		$this->assertFalse( get_option( '_mpp-rewrite-title-priority', false ), 'Unknown legacy values are skipped.' );
	}

	/**
	 * Already up to date: nothing happens.
	 */
	public function test_up_to_date() {
		update_option( '_mpp_db_version', 2000 );
		pchap()->db_version_raw = 0;
		$this->track_actions();

		pchap_setup_updater();

		$this->assertSame( 0, $this->fired['pchap_on_update'] + $this->fired['pchap_on_install'] );
		$this->assertSame( 0, $this->count_cron_events( pchap_get_rebuild_cron_hook() ) );
		$this->assertSame( 2000, pchap_get_db_version() );
	}

	/**
	 * Multipage 1.7 -> Paginova 2.0 with a pending rebuild: the old WP-Cron
	 * event is dropped and the rebuild continues under the new event name.
	 */
	public function test_update_from_1_7_moves_pending_rebuild() {
		update_option( '_mpp_db_version', 1700 );
		pchap()->db_version_raw = 0;
		$this->track_actions();

		pchap_save_postmeta_rebuild_state( array( 'cursor' => 42 ) );
		wp_schedule_single_event( time() + 60, 'mpp_rebuild_postmeta_batch' );

		pchap_setup_updater();

		$this->assertSame( 2000, (int) get_option( '_mpp_db_version' ) );
		$this->assertSame( 1, $this->fired['pchap_on_update'] );
		$this->assertSame( 0, $this->count_cron_events( 'mpp_rebuild_postmeta_batch' ), 'Old event removed.' );
		$this->assertSame( 1, $this->count_cron_events( pchap_get_rebuild_cron_hook() ), 'New event scheduled.' );
		$this->assertSame( 42, pchap_get_postmeta_rebuild_state()['cursor'], 'The rebuild resumes where it stopped.' );
	}

	/**
	 * Multipage 1.7 -> Paginova 2.0 without a pending rebuild: no event at all.
	 */
	public function test_update_from_1_7_without_pending_rebuild() {
		update_option( '_mpp_db_version', 1700 );
		pchap()->db_version_raw = 0;
		delete_option( '_mpp-rebuild-state' );

		pchap_setup_updater();

		$this->assertSame( 2000, (int) get_option( '_mpp_db_version' ) );
		$this->assertSame( 0, $this->count_cron_events( 'mpp_rebuild_postmeta_batch' ) );
		$this->assertSame( 0, $this->count_cron_events( pchap_get_rebuild_cron_hook() ) );
	}
}
