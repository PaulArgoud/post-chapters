<?php
/**
 * Admin assets tests: the admin stylesheet points to a file of the plugin.
 *
 * @package Paginova
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Test classes use the required Test_PChap_ prefix.
// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited -- The tests switch the text direction of $wp_locale and restore it.

/**
 * PChap_Admin::enqueue_scripts().
 *
 * @group admin
 */
class Test_PChap_Admin_Assets extends PChap_UnitTestCase {

	/**
	 * Remove the stylesheet and restore the text direction.
	 */
	public function tear_down() {
		wp_dequeue_style( 'paginova-admin' );
		wp_deregister_style( 'paginova-admin' );
		$GLOBALS['wp_locale']->text_direction = 'ltr';

		parent::tear_down();
	}

	/**
	 * Path on disk of the enqueued admin stylesheet.
	 *
	 * @return string
	 */
	private function enqueued_stylesheet_path() {
		PChap_Admin::enqueue_scripts();

		$this->assertTrue( wp_style_is( 'paginova-admin', 'enqueued' ) );

		$src  = wp_styles()->registered['paginova-admin']->src;
		$base = trailingslashit( PCHAP_PLUGIN_URL );
		$this->assertStringStartsWith( $base, $src );

		return trailingslashit( PCHAP_PLUGIN_DIR ) . substr( $src, strlen( $base ) );
	}

	/**
	 * The left-to-right stylesheet exists.
	 */
	public function test_admin_stylesheet_exists() {
		$this->assertFileExists( $this->enqueued_stylesheet_path() );
	}

	/**
	 * The right-to-left stylesheet exists.
	 */
	public function test_admin_rtl_stylesheet_exists() {
		$GLOBALS['wp_locale']->text_direction = 'rtl';

		$path = $this->enqueued_stylesheet_path();

		$this->assertStringContainsString( '-rtl', $path );
		$this->assertFileExists( $path );
	}
}
