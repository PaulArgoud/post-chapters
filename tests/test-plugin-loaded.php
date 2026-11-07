<?php
/**
 * Smoke tests: the plugin boots inside WordPress.
 *
 * @package Multipage
 */

/**
 * Plugin bootstrap smoke tests.
 */
class MPP_Test_Plugin_Loaded extends WP_UnitTestCase {

	/**
	 * Remove the forced locale and the loaded translations after each test.
	 */
	public function tear_down() {
		remove_filter( 'determine_locale', array( $this, 'force_fr_fr_locale' ) );
		unload_textdomain( 'sgr-nextpage-titles', true );
		parent::tear_down();
	}

	/**
	 * The main plugin file was loaded and the singleton is available.
	 */
	public function test_plugin_is_loaded() {
		$this->assertTrue( function_exists( 'multipage' ), 'multipage() is not defined.' );
		$this->assertInstanceOf( 'Multipage', multipage() );
		$this->assertTrue( class_exists( 'Multipage_Parser' ), 'Multipage_Parser is not loaded.' );
	}

	/**
	 * The version constant and the instance version match the release.
	 */
	public function test_version() {
		$this->assertTrue( defined( 'MPP_VERSION' ), 'MPP_VERSION is not defined.' );
		$this->assertSame( '1.7.0', MPP_VERSION );
		$this->assertSame( MPP_VERSION, multipage()->version );
	}

	/**
	 * The legacy [nextpage] shortcode is registered.
	 */
	public function test_nextpage_shortcode_is_registered() {
		$this->assertTrue( shortcode_exists( 'nextpage' ) );
	}

	/**
	 * The text domain is registered on init with the bundled /languages fallback path.
	 */
	public function test_bundled_translations_are_loaded() {
		$this->assertSame( 10, has_action( 'init', 'mpp_load_multipage_textdomain' ) );

		add_filter( 'determine_locale', array( $this, 'force_fr_fr_locale' ) );
		unload_textdomain( 'sgr-nextpage-titles', true );

		$this->assertSame( 'Commentaires', __( 'Comments', 'sgr-nextpage-titles' ) );
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
