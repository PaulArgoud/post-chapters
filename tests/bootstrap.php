<?php
/**
 * PHPUnit bootstrap file for the Multipage plugin.
 *
 * Locates the WordPress PHPUnit test library, in this order:
 * 1. the WP_TESTS_DIR environment variable (set to /wordpress-phpunit in the
 *    wp-env "tests-cli" container);
 * 2. the WP_PHPUNIT__DIR environment variable (wp-phpunit/wp-phpunit package);
 * 3. /wordpress-phpunit (wp-env mount point);
 * 4. the system temporary directory + /wordpress-tests-lib (install-wp-tests.sh).
 *
 * The Yoast PHPUnit Polyfills are taken from WP_TESTS_PHPUNIT_POLYFILLS_PATH
 * (constant or environment variable) or from this plugin's vendor/ directory.
 *
 * @package Multipage
 */

$mpp_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $mpp_tests_dir ) {
	$mpp_tests_dir = getenv( 'WP_PHPUNIT__DIR' );
}

if ( ! $mpp_tests_dir && is_dir( '/wordpress-phpunit' ) ) {
	$mpp_tests_dir = '/wordpress-phpunit';
}

if ( ! $mpp_tests_dir ) {
	$mpp_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

$mpp_tests_dir = rtrim( $mpp_tests_dir, '/\\' );

if ( ! file_exists( $mpp_tests_dir . '/includes/functions.php' ) ) {
	fwrite( STDERR, "Could not find the WordPress test library in {$mpp_tests_dir}. Set the WP_TESTS_DIR environment variable or run the tests inside the wp-env tests-cli container." . PHP_EOL ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite, WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI output, before WordPress is loaded.
	exit( 1 );
}

// Tell the WordPress test library where the PHPUnit Polyfills are.
if ( ! defined( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' ) ) {
	$mpp_polyfills_path = getenv( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' );
	if ( ! $mpp_polyfills_path ) {
		$mpp_polyfills_path = dirname( __DIR__ ) . '/vendor/yoast/phpunit-polyfills';
	}
	define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', $mpp_polyfills_path ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Constant read by the WordPress test library.
	unset( $mpp_polyfills_path );
}

// Give access to tests_add_filter().
require_once $mpp_tests_dir . '/includes/functions.php';

/**
 * Manually load the plugin being tested.
 *
 * The test database is reinstalled for every run, so the plugin is not
 * "active": it is loaded as a must-use plugin would be.
 */
function mpp_tests_manually_load_plugin() {
	require dirname( __DIR__ ) . '/sgr-nextpage-titles.php';
}
tests_add_filter( 'muplugins_loaded', 'mpp_tests_manually_load_plugin' );

// Start up the WordPress testing environment.
require $mpp_tests_dir . '/includes/bootstrap.php';

/*
 * Load the admin hook registrations once, before the first test saves the
 * hooks baseline: MPP_Admin (only instantiated on admin requests) includes
 * these files with require_once, so hooks they add inside a test would be
 * dropped by the hook restoration and never added again.
 */
require_once MPP_PLUGIN_DIR . 'inc/admin/admin-actions.php';

// Shared test helpers.
require_once __DIR__ . '/includes/class-mpp-unittestcase.php';
require_once __DIR__ . '/includes/class-mpp-test-corpus.php';
