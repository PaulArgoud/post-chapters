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
 * call. WP_Textdomain_Registry looks into WP_LANG_DIR/plugins first and only
 * falls back to the path registered here. The language packs found there for
 * this slug belong to the original plugin on translate.wordpress.org and lack
 * the strings of this version, so mpp_load_bundled_textdomain_mofile() swaps
 * them for the bundled file of the same locale, when there is one.
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

/**
 * Whether the bundled translations replace the language packs installed in WP_LANG_DIR.
 *
 * The slug sgr-nextpage-titles is shared with the original plugin: the language
 * packs that translate.wordpress.org delivers for it (de_DE, en_GB, es_ES, fr_FR,
 * it_IT, nb_NO, nl_NL, tr_TR...) translate the strings of the original plugin, not
 * those of this version. Only the packs in WP_LANG_DIR are replaced, and only for
 * the locales bundled in /languages; other locations (e.g. Loco Translate custom
 * files) are left alone.
 *
 * @since 1.7.1
 *
 * @return bool
 */
function mpp_prefer_bundled_translations() {
	/**
	 * Filters whether the bundled translations replace the installed language packs.
	 *
	 * @since 1.7.1
	 *
	 * @param bool $prefer True to use the bundled files of /languages. Default true.
	 */
	return (bool) apply_filters( 'mpp_prefer_bundled_translations', true );
}

/**
 * Bundled translation file replacing a file of WP_LANG_DIR, if any.
 *
 * @since 1.7.1
 *
 * @param string $file     Path of the file WordPress is about to load.
 * @param string $basename Basename of the bundled file to use instead.
 * @return string The bundled file, or '' when the file must be kept.
 */
function mpp_get_bundled_translation_file( $file, $basename ) {
	if ( ! is_string( $file ) || '' === $file || ! defined( 'MPP_PLUGIN_DIR' ) || ! defined( 'WP_LANG_DIR' ) ) {
		return '';
	}

	$file      = wp_normalize_path( $file );
	$packs_dir = trailingslashit( wp_normalize_path( WP_LANG_DIR ) ) . 'plugins/';
	$bundled   = wp_normalize_path( MPP_PLUGIN_DIR . 'languages/' . $basename );

	// Only the language packs (files directly in WP_LANG_DIR/plugins), never a custom location.
	if ( $file === $bundled || 0 !== strpos( $file, $packs_dir ) || false !== strpos( substr( $file, strlen( $packs_dir ) ), '/' ) ) {
		return '';
	}

	if ( ! mpp_prefer_bundled_translations() || ! is_readable( $bundled ) ) {
		return '';
	}

	return $bundled;
}

/**
 * Load the bundled .mo (and .l10n.php) file instead of an installed language pack.
 *
 * Hooked to 'load_textdomain_mofile', which load_textdomain() applies before it
 * derives the .l10n.php path from the .mo path (WordPress 6.5+), so both formats
 * follow. Works with the just-in-time loading (6.7+) and with the direct loading of
 * load_plugin_textdomain() (6.3 to 6.6). The path given by WP_Textdomain_Registry
 * does not matter: a pack listed in its cached file list (group translation_files)
 * but deleted since is replaced too.
 *
 * @since 1.7.1
 *
 * @param string $mofile Path to the MO file.
 * @param string $domain Text domain.
 * @return string
 */
function mpp_load_bundled_textdomain_mofile( $mofile, $domain ) {
	if ( 'sgr-nextpage-titles' !== $domain || ! is_string( $mofile ) ) {
		return $mofile;
	}

	$basename = wp_basename( $mofile );
	if ( ! preg_match( '/^sgr-nextpage-titles-[A-Za-z0-9_@-]+\.mo$/', $basename ) ) {
		return $mofile;
	}

	$bundled = mpp_get_bundled_translation_file( $mofile, $basename );

	return '' !== $bundled ? $bundled : $mofile;
}
add_filter( 'load_textdomain_mofile', 'mpp_load_bundled_textdomain_mofile', 10, 2 );
