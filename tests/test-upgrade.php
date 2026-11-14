<?php
/**
 * Upgrade routine tests.
 *
 * @package Paginova
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Test classes use the required Test_PChap_ prefix and tests apply core and third-party hooks.

/**
 * Install and update routines: fresh install, 1.3, 1.6, 1.7 and 2.2 updates,
 * migration of the Multipage / Post Chapters data names.
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
		delete_option( 'pchap-db-version' );
		pchap()->db_version_raw = 0;
		$this->track_actions();

		$this->assertTrue( pchap_is_install() );
		$this->assertTrue( pchap_is_update() );

		pchap_setup_updater();

		$this->assertSame( 2300, (int) get_option( 'pchap-db-version' ) );
		$this->assertSame( 1, $this->fired['pchap_on_install'] );
		$this->assertSame( 0, $this->fired['pchap_on_update'] );
		$this->assertSame( 1, $this->count_cron_events( pchap_get_rebuild_cron_hook() ) );
		$this->assertFalse( pchap_is_update() );
		$this->assertFalse( pchap_is_install() );
	}

	/**
	 * 1.6 install (database version 1000): the rebuild is scheduled exactly once.
	 */
	public function test_update_from_1_6_schedules_rebuild_once() {
		update_option( 'pchap-db-version', 1000 );
		pchap()->db_version_raw = 0;
		$this->track_actions();

		$post_id = $this->create_post( "Intro\n\n[nextpage title=\"Deux\"]\n\nTwo" );
		delete_post_meta( $post_id, '_pchap_data' );

		$this->assertFalse( pchap_is_install() );
		$this->assertTrue( pchap_is_update() );
		$this->assertSame( 1000, pchap_get_installed_db_version() );

		pchap_setup_updater();

		$hook = pchap_get_rebuild_cron_hook();
		$this->assertSame( 2300, (int) get_option( 'pchap-db-version' ) );
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
		$this->assertSame( array(), get_post_meta( $post_id, '_pchap_data', false ) );
		do_action( $hook );
		$this->assertNotEmpty( get_post_meta( $post_id, '_pchap_data', true ) );
		$this->assertFalse( pchap_get_postmeta_rebuild_state() );
	}

	/**
	 * The 1.6 settings (stored as strings) are kept by the update.
	 */
	public function test_update_from_1_6_keeps_settings() {
		update_option( 'pchap-db-version', 1000 );
		pchap()->db_version_raw = 0;

		update_option( 'pchap-toc-position', 'bottom' );
		update_option( 'pchap-hide-toc-header', '1' );
		update_option( 'pchap-rewrite-title-priority', '50' );
		update_option( 'pchap-comments-on-page', 'last-page' );

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
		delete_option( 'pchap-db-version' );
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
		$this->assertSame( 2300, (int) get_option( 'pchap-db-version' ) );
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
		$this->assertFalse( get_option( 'pchap-disable-tinymce-buttons', false ) );
	}

	/**
	 * Legacy conversion never overwrites options that already exist.
	 */
	public function test_legacy_conversion_keeps_existing_options() {
		update_option( 'pchap-toc-position', 'bottom' );
		update_option( 'pchap-toc-row-labels', 'hidden' );

		pchap_convert_legacy_settings(
			array(
				'toc-position'           => 'top-left',
				'toc-page-labels'        => 'numbers',
				'rewrite-title-priority' => 'unknown',
			)
		);

		$this->assertSame( 'bottom', get_option( 'pchap-toc-position' ) );
		$this->assertSame( 'hidden', get_option( 'pchap-toc-row-labels' ) );
		$this->assertFalse( get_option( 'pchap-rewrite-title-priority', false ), 'Unknown legacy values are skipped.' );
	}

	/**
	 * Already up to date: nothing happens.
	 */
	public function test_up_to_date() {
		update_option( 'pchap-db-version', 2300 );
		pchap()->db_version_raw = 0;
		$this->track_actions();

		pchap_setup_updater();

		$this->assertSame( 0, $this->fired['pchap_on_update'] + $this->fired['pchap_on_install'] );
		$this->assertSame( 0, $this->count_cron_events( pchap_get_rebuild_cron_hook() ) );
		$this->assertSame( 2300, pchap_get_db_version() );
	}

	/**
	 * Multipage 1.7 with a pending rebuild: the old WP-Cron event is dropped
	 * and the rebuild starts again under the new event name (2.3 routine).
	 */
	public function test_update_from_1_7_moves_pending_rebuild() {
		update_option( 'pchap-db-version', 1700 );
		pchap()->db_version_raw = 0;
		$this->track_actions();

		pchap_save_postmeta_rebuild_state( array( 'cursor' => 42 ) );
		wp_schedule_single_event( time() + 60, 'mpp_rebuild_postmeta_batch' );

		pchap_setup_updater();

		$this->assertSame( 2300, (int) get_option( 'pchap-db-version' ) );
		$this->assertSame( 1, $this->fired['pchap_on_update'] );
		$this->assertSame( 0, $this->count_cron_events( 'mpp_rebuild_postmeta_batch' ), 'Old event removed.' );
		$this->assertSame( 1, $this->count_cron_events( pchap_get_rebuild_cron_hook() ), 'New event scheduled.' );
		$this->assertSame( 0, pchap_get_postmeta_rebuild_state()['cursor'], 'The index is rebuilt under its new name.' );
	}

	/**
	 * Old option and meta names of Multipage and Post Chapters.
	 *
	 * @var array
	 */
	private $legacy_settings = array(
		'mpp-toc-position'              => 'bottom',
		'mpp-hide-toc-header'           => '1',
		'mpp-comments-on-page'          => 'last-page',
		'_mpp-rewrite-title-priority'   => '50',
		'_mpp-rewrite-content-priority' => '5',
	);

	/**
	 * Store the data of a Post Chapters 2.2 site (old names).
	 *
	 * @return int ID of a post holding the old chapter index.
	 */
	private function store_legacy_data() {
		delete_option( 'pchap-db-version' );
		foreach ( array_keys( pchap_get_option_definitions() ) as $option_name ) {
			delete_option( $option_name );
		}

		update_option( '_mpp_db_version', 2000 );
		foreach ( $this->legacy_settings as $option_name => $value ) {
			update_option( $option_name, $value );
		}
		update_option( '_mpp-postmeta-built', 1700000000 );
		update_option( '_mpp-rebuild-last', array( 'time' => 1700000000 ) );
		update_option( '_mpp-rebuild-state', array( 'cursor' => 7 ) );
		update_option( '_mpp-rebuild-lock', '1700000000|token' );

		$post_id = $this->create_post( "Intro\n\n[nextpage title=\"Deux\"]\n\nTwo" );
		delete_post_meta( $post_id, '_pchap_data' );
		add_post_meta( $post_id, '_mpp_data', array( 'intro' => 'Intro' ) );

		return $post_id;
	}

	/**
	 * The legacy options move to their new names before anything reads them.
	 */
	public function test_migrate_legacy_options() {
		$this->store_legacy_data();

		$this->assertTrue( pchap_migrate_legacy_options() );
		pchap_clear_options_cache();

		$this->assertSame( 2000, (int) get_option( 'pchap-db-version' ), 'The update routines still run.' );
		$this->assertSame( 'bottom', pchap_get_toc_position() );
		$this->assertTrue( pchap_hide_toc_header() );
		$this->assertSame( 'last-page', pchap_get_comments_on_page() );
		$this->assertSame( 50, pchap_get_rewrite_title_priority() );
		$this->assertSame( 5, pchap_get_rewrite_content_priority() );

		foreach ( array_keys( pchap_get_legacy_option_names() ) as $old_name ) {
			$this->assertFalse( get_option( $old_name, false ), $old_name . ' deleted.' );
		}
		foreach ( array( 'pchap-postmeta-built', 'pchap-rebuild-last', 'pchap-rebuild-state', 'pchap-rebuild-lock' ) as $option_name ) {
			$this->assertFalse( get_option( $option_name, false ), $option_name . ' not copied: the index is rebuilt.' );
		}

		// Done once: a Paginova database version is stored now.
		update_option( 'mpp-toc-position', 'top-left' );
		$this->assertFalse( pchap_migrate_legacy_options() );
		$this->assertSame( 'top-left', get_option( 'mpp-toc-position' ) );
	}

	/**
	 * A setting already stored under its new name keeps its value.
	 */
	public function test_migrate_legacy_options_keeps_new_values() {
		$this->store_legacy_data();
		update_option( 'pchap-toc-position', 'top-left' );

		pchap_migrate_legacy_options();
		pchap_clear_options_cache();

		$this->assertSame( 'top-left', pchap_get_toc_position() );
		$this->assertFalse( get_option( 'mpp-toc-position', false ) );
	}

	/**
	 * Fresh install: nothing to migrate.
	 */
	public function test_migrate_legacy_options_fresh_install() {
		delete_option( 'pchap-db-version' );

		$this->assertFalse( pchap_migrate_legacy_options() );
		$this->assertFalse( get_option( 'pchap-db-version', false ) );
	}

	/**
	 * Post Chapters 2.2 -> Paginova 2.3: options moved, `_mpp_data` replaced by `_pchap_data`.
	 */
	public function test_update_from_2_2() {
		$post_id                = $this->store_legacy_data();
		pchap()->db_version_raw = 0;
		$this->track_actions();

		pchap_migrate_legacy_options();
		pchap_setup_updater();

		$this->assertSame( 2300, (int) get_option( 'pchap-db-version' ) );
		$this->assertSame( 1, $this->fired['pchap_on_update'] );
		$this->assertSame( 0, $this->fired['pchap_on_install'] );
		$this->assertSame( array(), get_post_meta( $post_id, '_mpp_data', false ), 'Old meta deleted.' );
		$this->assertSame( 1, $this->count_cron_events( pchap_get_rebuild_cron_hook() ) );

		do_action( pchap_get_rebuild_cron_hook() );

		$this->assertSame( PChap_Parser::multipage_return_array( get_post( $post_id )->post_content ), get_post_meta( $post_id, '_pchap_data', true ) );
		$this->assertFalse( pchap_get_postmeta_rebuild_state() );
		$this->assertGreaterThan( 1700000000, (int) get_option( 'pchap-postmeta-built' ) );
	}
}
