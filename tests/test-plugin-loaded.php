<?php
/**
 * Smoke tests: the plugin boots inside WordPress.
 *
 * @package Paginova
 */

/**
 * Plugin bootstrap smoke tests.
 */
class PChap_Test_Plugin_Loaded extends WP_UnitTestCase {

	/**
	 * Remove the forced locale and the loaded translations after each test.
	 */
	public function tear_down() {
		remove_filter( 'determine_locale', array( $this, 'force_fr_fr_locale' ) );
		unload_textdomain( 'paginova', true );
		parent::tear_down();
	}

	/**
	 * The main plugin file was loaded and the singleton is available.
	 */
	public function test_plugin_is_loaded() {
		$this->assertTrue( function_exists( 'pchap' ), 'pchap() is not defined.' );
		$this->assertInstanceOf( 'PChap', pchap() );
		$this->assertTrue( class_exists( 'PChap_Parser' ), 'PChap_Parser is not loaded.' );
	}

	/**
	 * The version constant and the instance version match the release.
	 */
	public function test_version() {
		$this->assertTrue( defined( 'PCHAP_VERSION' ), 'PCHAP_VERSION is not defined.' );
		$this->assertMatchesRegularExpression( '/^\d+\.\d+\.\d+$/', PCHAP_VERSION );
		$this->assertSame( PCHAP_VERSION, pchap()->version );
	}

	/**
	 * No `nextpage` shortcode is registered (the markers are a content format).
	 */
	public function test_nextpage_shortcode_is_not_registered() {
		$this->assertFalse( shortcode_exists( 'nextpage' ) );
	}

	/**
	 * The text domain is registered on init with the bundled /languages fallback path.
	 */
	public function test_bundled_translations_are_loaded() {
		$this->assertSame( 10, has_action( 'init', 'pchap_load_textdomain' ) );

		add_filter( 'determine_locale', array( $this, 'force_fr_fr_locale' ) );
		unload_textdomain( 'paginova', true );

		$this->assertSame( 'Commentaires', __( 'Comments', 'paginova' ) );
	}

	/**
	 * Filter callback forcing the fr_FR locale.
	 *
	 * @return string
	 */
	public function force_fr_fr_locale() {
		return 'fr_FR';
	}
}
