<?php
/**
 * Tests of uninstall.php.
 *
 * @package Multipage
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Test classes use the required Test_Mpp_ prefix and tests apply core and third-party hooks.

/**
 * The uninstall.php file removes the plugin data (single site).
 *
 * The file is included inside the test transaction, so its deletions are rolled
 * back afterwards. The multisite branch is not covered (single-site test run).
 *
 * @group multipage
 * @group uninstall
 */
class Test_Mpp_Uninstall extends Mpp_UnitTestCase {

	/**
	 * Every option, the post meta and the scheduled event are removed; other data is kept.
	 */
	public function test_uninstall_removes_plugin_data() {
		$option_names = array_merge(
			array_keys( mpp_get_option_definitions() ),
			array( '_mpp_db_version', '_mpp-postmeta-built', '_mpp-rebuild-state', '_mpp-rebuild-last', '_mpp-rebuild-lock', 'multipage' )
		);
		foreach ( $option_names as $option_name ) {
			update_option( $option_name, 'x' );
		}
		update_option( 'unrelated-option', 'kept' );

		$post_id = $this->create_post( "Intro\n\n[nextpage title=\"Deux\"]\n\nTwo" );
		$this->assertNotEmpty( get_post_meta( $post_id, '_mpp_data', true ) );
		add_post_meta( $post_id, '_unrelated_meta', 'kept' );

		wp_schedule_single_event( time() + 100, 'mpp_rebuild_postmeta_batch' );
		$this->assertNotFalse( wp_next_scheduled( 'mpp_rebuild_postmeta_batch' ) );

		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', 'sgr-nextpage-titles/sgr-nextpage-titles.php' );
		}
		include MPP_PLUGIN_DIR . 'uninstall.php';

		wp_cache_flush();
		mpp_clear_options_cache();

		foreach ( $option_names as $option_name ) {
			$this->assertFalse( get_option( $option_name, false ), "Option {$option_name} was not deleted." );
		}
		$this->assertSame( 'kept', get_option( 'unrelated-option' ) );

		$this->assertSame( array(), get_post_meta( $post_id, '_mpp_data', false ) );
		$this->assertSame( 'kept', get_post_meta( $post_id, '_unrelated_meta', true ) );
		$this->assertFalse( wp_next_scheduled( 'mpp_rebuild_postmeta_batch' ) );
	}

	/**
	 * The uninstall list covers every registered setting.
	 */
	public function test_uninstall_lists_every_setting() {
		$source = file_get_contents( MPP_PLUGIN_DIR . 'uninstall.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		foreach ( array_keys( mpp_get_option_definitions() ) as $option_name ) {
			$this->assertStringContainsString( "'" . $option_name . "'", $source, "{$option_name} is not removed on uninstall." );
		}
	}
}
