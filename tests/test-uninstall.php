<?php
/**
 * Tests of uninstall.php.
 *
 * @package Paginova
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Test classes use the required Test_PChap_ prefix and tests apply core and third-party hooks.

/**
 * The uninstall.php file removes the plugin data (single site).
 *
 * The file is included inside the test transaction, so its deletions are rolled
 * back afterwards. The multisite branch is not covered (single-site test run).
 *
 * @group multipage
 * @group uninstall
 */
class Test_PChap_Uninstall extends PChap_UnitTestCase {

	/**
	 * Every option, the post meta and the scheduled event are removed; other data is kept.
	 */
	public function test_uninstall_removes_plugin_data() {
		$option_names = array_merge(
			array_keys( pchap_get_option_definitions() ),
			array( '_mpp_db_version', '_mpp-postmeta-built', '_mpp-rebuild-state', '_mpp-rebuild-last', '_mpp-rebuild-lock', 'multipage' )
		);
		foreach ( $option_names as $option_name ) {
			update_option( $option_name, 'x' );
		}
		update_option( 'unrelated-option', 'kept' );

		$post_id = $this->create_post( "Intro\n\n[nextpage title=\"Deux\"]\n\nTwo" );
		$this->assertNotEmpty( get_post_meta( $post_id, '_mpp_data', true ) );
		add_post_meta( $post_id, '_unrelated_meta', 'kept' );

		wp_schedule_single_event( time() + 100, 'pchap_rebuild_postmeta_batch' );
		$this->assertNotFalse( wp_next_scheduled( 'pchap_rebuild_postmeta_batch' ) );

		$this->uninstall();

		foreach ( $option_names as $option_name ) {
			$this->assertFalse( get_option( $option_name, false ), "Option {$option_name} was not deleted." );
		}
		$this->assertSame( 'kept', get_option( 'unrelated-option' ) );

		$this->assertSame( array(), get_post_meta( $post_id, '_mpp_data', false ) );
		$this->assertSame( 'kept', get_post_meta( $post_id, '_unrelated_meta', true ) );
		$this->assertFalse( wp_next_scheduled( 'pchap_rebuild_postmeta_batch' ) );
	}

	/**
	 * The uninstall list covers every registered setting.
	 */
	public function test_uninstall_lists_every_setting() {
		$source = file_get_contents( PCHAP_PLUGIN_DIR . 'uninstall.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		foreach ( array_keys( pchap_get_option_definitions() ) as $option_name ) {
			$this->assertStringContainsString( "'" . $option_name . "'", $source, "{$option_name} is not removed on uninstall." );
		}
	}

	/**
	 * Run uninstall.php, as WordPress does when the plugin is deleted.
	 *
	 * The caches are not flushed: uninstall.php has to leave them coherent by itself.
	 */
	private function uninstall() {
		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', 'paginova/paginova.php' );
		}

		include PCHAP_PLUGIN_DIR . 'uninstall.php';

		pchap_clear_options_cache();
	}

	/**
	 * With a persistent object cache, the index must not stay readable from the cache.
	 */
	public function test_uninstall_clears_the_post_meta_cache() {
		$post_id = $this->create_post( "Intro\n\n[nextpage title=\"Deux\"]\n\nTwo" );

		// Fill the post meta cache, as any page view does.
		$this->assertNotEmpty( get_post_meta( $post_id, '_mpp_data', true ) );
		$this->assertNotFalse( wp_cache_get( $post_id, 'post_meta' ) );

		$this->uninstall();

		$this->assertSame( array(), get_post_meta( $post_id, '_mpp_data', false ), 'The index is still readable from the cache.' );
	}

	/**
	 * Events of the background rebuild are removed whatever their arguments.
	 */
	public function test_uninstall_unschedules_events_with_arguments() {
		wp_schedule_single_event( time() + 100, 'pchap_rebuild_postmeta_batch', array( 'a' ) );
		wp_schedule_single_event( time() + 200, 'mpp_rebuild_postmeta_batch', array( 'b', 'c' ) );
		wp_schedule_single_event( time() + 300, 'unrelated_hook', array( 'kept' ) );

		$this->uninstall();

		$this->assertFalse( wp_next_scheduled( 'pchap_rebuild_postmeta_batch', array( 'a' ) ) );
		$this->assertFalse( wp_next_scheduled( 'mpp_rebuild_postmeta_batch', array( 'b', 'c' ) ) );
		$this->assertNotFalse( wp_next_scheduled( 'unrelated_hook', array( 'kept' ) ) );
	}

	/**
	 * The parsed chapters kept in the object cache go away.
	 */
	public function test_uninstall_flushes_the_cache_group() {
		if ( ! function_exists( 'wp_cache_supports' ) || ! wp_cache_supports( 'flush_group' ) ) {
			$this->markTestSkipped( 'The object cache cannot flush a group.' );
		}

		wp_cache_set( 'parse-x', array( 'pages' ), 'paginova' );
		wp_cache_set( 'other', 'kept', 'unrelated-group' );

		$this->uninstall();

		$this->assertFalse( wp_cache_get( 'parse-x', 'paginova' ) );
		$this->assertSame( 'kept', wp_cache_get( 'other', 'unrelated-group' ) );
	}

	/**
	 * After the plugin has lived (activation, settings, posts, page views, rebuild, lock), nothing of it
	 * is left in any table: options, post, user, term and comment meta, cron, transients and tables.
	 */
	public function test_uninstall_leaves_no_trace() {
		global $wpdb;

		// Install and use.
		pchap_activation();
		pchap_version_bump();
		foreach ( pchap_get_option_definitions() as $name => $definition ) {
			update_option( $name, $definition['default'] );
		}

		$post_id = $this->create_post( "Intro\n\n[nextpage title=\"Deux\"]\n\nTwo" );
		$page_id = $this->create_post(
			"Intro\n\n[nextpage title=\"Trois\"]\n\nThree",
			array(
				'post_type' => 'page',
				'post_name' => 'une-page',
			)
		);
		wp_update_post(
			array(
				'ID'           => $post_id,
				'post_content' => wp_slash( "Intro\n\n[nextpage title=\"Revu\"]\n\nRevised" ),
			)
		);
		$this->render_subpage( $post_id, 2 );

		pchap_save_postmeta_rebuild_state( array( 'cursor' => 5 ) );
		pchap_complete_postmeta_rebuild( array( 'processed' => 2 ) );
		pchap_save_postmeta_rebuild_state( array( 'cursor' => 7 ) );
		pchap_rebuild_acquire_lock();
		pchap_schedule_postmeta_rebuild();

		$this->assertNotEmpty( get_post_meta( $page_id, '_mpp_data', true ) );

		$this->uninstall();

		$pattern = '(^|[_-])(mpp|pchap)([_-]|$)|^multipage$|paginova|nextpage-titles';
		$tables  = array(
			$wpdb->options     => 'option_name',
			$wpdb->postmeta    => 'meta_key',
			$wpdb->usermeta    => 'meta_key',
			$wpdb->termmeta    => 'meta_key',
			$wpdb->commentmeta => 'meta_key',
		);
		foreach ( $tables as $table => $column ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Table and column names of core.
			$left = $wpdb->get_col( $wpdb->prepare( "SELECT {$column} FROM {$table} WHERE {$column} REGEXP %s", $pattern ) );
			$this->assertSame( array(), $left, "{$table} still holds data of the plugin." );
		}

		$this->assertSame( array(), $wpdb->get_col( "SHOW TABLES LIKE '%pchap%'" ), 'The plugin created no table.' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$this->assertDoesNotMatchRegularExpression( '/pchap|mpp_rebuild/', (string) wp_json_encode( _get_cron_array() ), 'No event of the plugin is left in the cron.' );
		$this->assertSame( array(), get_post_meta( $page_id, '_mpp_data', false ) );
	}
}
