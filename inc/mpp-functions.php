<?php
/**
 * Multipage Common Functions.
 *
 * @package Multipage
 * @subpackage Functions
 * @since 1.4
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/** Versions ******************************************************************/

/**
 * Output the Multipage version.
 *
 * @since 1.4
 */
function mpp_version() {
	echo esc_html( mpp_get_version() );
}

/**
 * Return the Multipage version.
 *
 * @since 1.4
 *
 * @return string The Multipage version.
 */
function mpp_get_version() {
	return multipage()->version;
}

/**
 * Output the Multipage database version.
 *
 * @since 1.4
 */
function mpp_db_version() {
	echo (int) mpp_get_db_version();
}

/**
 * Return the Multipage database version.
 *
 * @since 1.4
 *
 * @return int The Multipage database version.
 */
function mpp_get_db_version() {
	return multipage()->db_version;
}

/**
 * Output the Multipage database version, as stored in the database.
 *
 * @since 1.4
 */
function mpp_db_version_raw() {
	echo (int) mpp_get_db_version_raw();
}

/**
 * Return the Multipage database version, as stored in the database.
 *
 * @since 1.4
 *
 * @return int The Multipage version direct from the database.
 */
function mpp_get_db_version_raw() {
	$mpp = multipage();
	return ! empty( $mpp->db_version_raw ) ? $mpp->db_version_raw : 0;
}

/** Miscellaneous hooks *******************************************************/

/**
 * Load the Multipage translation files for the current language.
 *
 * Since WordPress 6.7 load_plugin_textdomain() only registers a custom
 * lookup path; the files are loaded just in time on the first translation
 * call. WP_Textdomain_Registry looks into WP_LANG_DIR/plugins first (language
 * packs from translate.wordpress.org) and only falls back to the path
 * registered here, so the bundled files in /languages are used only when no
 * language pack is installed.
 *
 * @since 1.4
 * @since 1.7.0 Registers the bundled /languages directory as the fallback path.
 *
 * @see load_plugin_textdomain() for a description of return values.
 *
 * @return bool True on success, false on failure.
 */
function mpp_load_multipage_textdomain() {
	return load_plugin_textdomain(
		'sgr-nextpage-titles',
		false,
		dirname( plugin_basename( MPP__PLUGIN_FILE ) ) . '/languages'
	);
}
add_action( 'init', 'mpp_load_multipage_textdomain' );
