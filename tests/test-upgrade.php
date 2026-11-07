<?php
/**
 * Upgrade routine tests.
 *
 * @package Multipage
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Test classes use the required Test_Mpp_ prefix and tests apply core and third-party hooks.

/**
 * Install and update routines: fresh install, 1.6 -> 1.7, 1.3 -> 1.7.
 *
 * @group multipage
 * @group upgrade
 */
class Test_Mpp_Upgrade extends Mpp_UnitTestCase {

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
		$this->db_version_raw = multipage()->db_version_raw;
	}

	/**
	 * Restore the detected raw version and clean the rebuild state.
	 */
	public function tear_down() {
		multipage()->db_version_raw = $this->db_version_raw;
		wp_clear_scheduled_hook( mpp_get_rebuild_cron_hook() );
		parent::tear_down();
	}

	/**
	 * Count the actions fired by the updater.
	 *
	 * @var int[]
	 */
	private $fired = array(
		'mpp_on_install' => 0,
		'mpp_on_update'  => 0,
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
	 * Fresh install: version stored, a rebuild scheduled, mpp_on_install fired.
	 */
	public function test_fresh_install() {
		delete_option( '_mpp_db_version' );
		multipage()->db_version_raw = 0;
		$this->track_actions();

		$this->assertTrue( mpp_is_install() );
		$this->assertTrue( mpp_is_update() );

		mpp_setup_updater();

		$this->assertSame( 1700, (int) get_option( '_mpp_db_version' ) );
		$this->assertSame( 1, $this->fired['mpp_on_install'] );
		$this->assertSame( 0, $this->fired['mpp_on_update'] );
		$this->assertSame( 1, $this->count_cron_events( mpp_get_rebuild_cron_hook() ) );
		$this->assertFalse( mpp_is_update() );
		$this->assertFalse( mpp_is_install() );
	}

	/**
	 * 1.6 install (_mpp_db_version = 1000): the rebuild is scheduled exactly once.
	 */
	public function test_update_from_1_6_schedules_rebuild_once() {
		update_option( '_mpp_db_version', 1000 );
		multipage()->db_version_raw = 0;
		$this->track_actions();

		$post_id = $this->create_post( "Intro\n\n[nextpage title=\"Deux\"]\n\nTwo" );
		delete_post_meta( $post_id, '_mpp_data' );

		$this->assertFalse( mpp_is_install() );
		$this->assertTrue( mpp_is_update() );
		$this->assertSame( 1000, mpp_get_installed_db_version() );

		mpp_setup_updater();

		$hook = mpp_get_rebuild_cron_hook();
		$this->assertSame( 1700, (int) get_option( '_mpp_db_version' ) );
		$this->assertSame( 1, $this->fired['mpp_on_update'] );
		$this->assertSame( 0, $this->fired['mpp_on_install'] );
		$this->assertSame( 1, $this->count_cron_events( $hook ) );
		$state = mpp_get_postmeta_rebuild_state();
		$this->assertIsArray( $state );

		// The updater runs on every admin_init: nothing more happens.
		mpp_setup_updater();
		mpp_setup_updater();

		$this->assertSame( 1, $this->fired['mpp_on_update'] );
		$this->assertSame( 1, $this->count_cron_events( $hook ) );
		$this->assertSame( $state, mpp_get_postmeta_rebuild_state() );

		// The meta is built by the background routine.
		$this->assertSame( array(), get_post_meta( $post_id, '_mpp_data', false ) );
		do_action( $hook );
		$this->assertNotEmpty( get_post_meta( $post_id, '_mpp_data', true ) );
		$this->assertFalse( mpp_get_postmeta_rebuild_state() );
	}

	/**
	 * The 1.6 settings (stored as strings) are kept by the update.
	 */
	public function test_update_from_1_6_keeps_settings() {
		update_option( '_mpp_db_version', 1000 );
		multipage()->db_version_raw = 0;

		update_option( 'mpp-toc-position', 'bottom' );
		update_option( 'mpp-hide-toc-header', '1' );
		update_option( '_mpp-rewrite-title-priority', '50' );
		update_option( 'mpp-comments-on-page', 'last-page' );

		mpp_setup_updater();

		$this->assertSame( 'bottom', mpp_get_toc_position() );
		$this->assertTrue( mpp_hide_toc_header() );
		$this->assertSame( 50, mpp_get_rewrite_title_priority() );
		$this->assertSame( 'last-page', mpp_get_comments_on_page() );
	}

	/**
	 * The admin_init chain runs the updater (mpp_admin_init -> mpp_setup_updater).
	 */
	public function test_updater_is_hooked_on_admin_init() {
		$this->assertSame( 10, has_action( 'admin_init', 'mpp_admin_init' ) );
		$this->assertSame( 1000, has_action( 'mpp_admin_init', 'mpp_setup_updater' ) );
	}

	/**
	 * 1.3 install (legacy `multipage` option): settings converted, option removed, rebuild scheduled once.
	 */
	public function test_update_from_1_3() {
		delete_option( '_mpp_db_version' );
		foreach ( array_keys( mpp_get_option_definitions() ) as $option_name ) {
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
		// As detected by Multipage::versions() at boot when the legacy option exists.
		multipage()->db_version_raw = 999;
		$this->track_actions();

		$this->assertFalse( mpp_is_install() );
		$this->assertTrue( mpp_is_update() );

		mpp_setup_updater();

		$this->assertFalse( get_option( 'multipage', false ), 'Legacy option removed.' );
		$this->assertSame( 1700, (int) get_option( '_mpp_db_version' ) );
		$this->assertSame( 1, $this->fired['mpp_on_update'] );
		$this->assertSame( 1, $this->count_cron_events( mpp_get_rebuild_cron_hook() ) );

		$this->assertSame( 'first-page', mpp_get_comments_on_page() );
		$this->assertFalse( mpp_disable_standard_pagination() );
		$this->assertTrue( mpp_toc_only_on_the_first_page() );
		$this->assertSame( 'top-left', mpp_get_toc_position() );
		$this->assertTrue( mpp_hide_toc_header() );
		$this->assertTrue( mpp_comments_toc_link() );
		$this->assertSame( 'page', mpp_get_toc_row_labels() );
		$this->assertSame( 50, mpp_get_rewrite_title_priority() );
		$this->assertSame( 5, mpp_get_rewrite_content_priority() );
		$this->assertFalse( mpp_disable_tinymce_buttons(), 'Empty legacy values are skipped.' );
		$this->assertFalse( get_option( 'mpp-disable-tinymce-buttons', false ) );
	}

	/**
	 * Legacy conversion never overwrites options that already exist.
	 */
	public function test_legacy_conversion_keeps_existing_options() {
		update_option( 'mpp-toc-position', 'bottom' );
		update_option( 'mpp-toc-row-labels', 'hidden' );

		_mpp_convert_legacy_settings(
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
		update_option( '_mpp_db_version', 1700 );
		multipage()->db_version_raw = 0;
		$this->track_actions();

		mpp_setup_updater();

		$this->assertSame( 0, $this->fired['mpp_on_update'] + $this->fired['mpp_on_install'] );
		$this->assertSame( 0, $this->count_cron_events( mpp_get_rebuild_cron_hook() ) );
		$this->assertSame( 1700, mpp_get_db_version() );
	}
}
