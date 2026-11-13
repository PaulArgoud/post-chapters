<?php
/**
 * Paginova bundled translations.
 *
 * Loads the translation files shipped in /languages, and prefers them to the
 * language packs of WP_LANG_DIR (they always match the strings of the release).
 * Only the GitHub build ships this file and /languages: the WordPress.org build
 * (bin/build-zip.sh --wporg) leaves both out, translations then come from
 * translate.wordpress.org language packs, loaded just in time by WordPress.
 *
 * @package Paginova
 * @subpackage Functions
 * @since 2.2.0 Moved from inc/pchap-functions.php and inc/pchap-blocks.php.
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Load the Paginova translation files for the current language.
 *
 * Since WordPress 6.7 load_plugin_textdomain() only registers a custom
 * lookup path; the files are loaded just in time on the first translation
 * call. WP_Textdomain_Registry looks into WP_LANG_DIR/plugins first and only
 * falls back to the path registered here. A language pack found there can lag
 * behind the strings of this release, so pchap_load_bundled_textdomain_mofile()
 * swaps it for the bundled file of the same locale, when there is one. Without
 * this call the bundled translations of /languages would never load.
 *
 * @since 1.4
 * @since 1.7.0 Registers the bundled /languages directory as the fallback path.
 *
 * @see load_plugin_textdomain() for a description of return values.
 *
 * @return bool True on success, false on failure.
 */
function pchap_load_textdomain() {
	// phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound -- Registers the bundled translations of /languages.
	return load_plugin_textdomain(
		'paginova',
		false,
		dirname( plugin_basename( PCHAP__PLUGIN_FILE ) ) . '/languages'
	);
}
add_action( 'init', 'pchap_load_textdomain' );

/**
 * Whether the bundled translations replace the language packs installed in WP_LANG_DIR.
 *
 * The bundled translations ship with each release and always match its
 * strings, while a language pack installed in WP_LANG_DIR can lag behind (or,
 * before 2.0, translate the strings of the original Multipage plugin, which
 * shared the old slug). Only the packs in WP_LANG_DIR are replaced, and only for
 * the locales bundled in /languages; other locations (e.g. Loco Translate custom
 * files) are left alone.
 *
 * @since 1.7.1
 *
 * @return bool
 */
function pchap_prefer_bundled_translations() {
	/**
	 * Filters whether the bundled translations replace the installed language packs.
	 *
	 * @since 1.7.1
	 *
	 * @param bool $prefer True to use the bundled files of /languages. Default true.
	 */
	return (bool) pchap_apply_filters( 'pchap_prefer_bundled_translations', true );
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
function pchap_get_bundled_translation_file( $file, $basename ) {
	if ( ! is_string( $file ) || '' === $file || ! defined( 'PCHAP_PLUGIN_DIR' ) || ! defined( 'WP_LANG_DIR' ) ) {
		return '';
	}

	$file      = wp_normalize_path( $file );
	$packs_dir = trailingslashit( wp_normalize_path( WP_LANG_DIR ) ) . 'plugins/';
	$bundled   = wp_normalize_path( PCHAP_PLUGIN_DIR . 'languages/' . $basename );

	// Only the language packs (files directly in WP_LANG_DIR/plugins), never a custom location.
	if ( $file === $bundled || 0 !== strpos( $file, $packs_dir ) || false !== strpos( substr( $file, strlen( $packs_dir ) ), '/' ) ) {
		return '';
	}

	if ( ! pchap_prefer_bundled_translations() || ! is_readable( $bundled ) ) {
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
function pchap_load_bundled_textdomain_mofile( $mofile, $domain ) {
	if ( 'paginova' !== $domain || ! is_string( $mofile ) ) {
		return $mofile;
	}

	$basename = wp_basename( $mofile );
	if ( ! preg_match( '/^paginova-[A-Za-z0-9_@-]+\.mo$/', $basename ) ) {
		return $mofile;
	}

	$bundled = pchap_get_bundled_translation_file( $mofile, $basename );

	return '' !== $bundled ? $bundled : $mofile;
}
add_filter( 'load_textdomain_mofile', 'pchap_load_bundled_textdomain_mofile', 10, 2 );

if ( ! function_exists( 'pchap_load_bundled_script_translation_file' ) ) {
	/**
	 * Use the JS translations bundled in /languages.
	 *
	 * Hooked to 'load_script_translation_file', for a script of this plugin that
	 * uses the plugin text domain only. A file of WP_LANG_DIR/plugins (a language
	 * pack, see pchap_prefer_bundled_translations()) is
	 * replaced by the bundled "paginova-{locale}-{md5}.json" file of
	 * the same locale, when there is one. load_script_textdomain() calls
	 * load_script_translations() with $file = false once every regular location
	 * failed: the bundled file is then the last resort.
	 *
	 * @since 1.7.0
	 * @since 1.7.1 Replaces the language packs installed in WP_LANG_DIR/plugins.
	 *
	 * @param string|false $file   Path to the translation file to load, false if there isn't one.
	 * @param string       $handle Name of the script to register a translation domain to.
	 * @param string       $domain The text domain.
	 * @return string|false Path to the translation file, or the original value.
	 */
	function pchap_load_bundled_script_translation_file( $file, $handle, $domain ) {
		if ( 'paginova' !== $domain || ! defined( 'PCHAP_PLUGIN_DIR' ) || ! defined( 'PCHAP__PLUGIN_FILE' ) ) {
			return $file;
		}

		// A file of a custom location (not a language pack) is never replaced.
		if ( false !== $file && ( ! is_string( $file ) || 0 !== strpos( wp_normalize_path( $file ), trailingslashit( wp_normalize_path( WP_LANG_DIR ) ) . 'plugins/' ) ) ) {
			return $file;
		}

		$wp_scripts = wp_scripts();
		if ( ! isset( $wp_scripts->registered[ $handle ] ) || ! is_string( $wp_scripts->registered[ $handle ]->src ) ) {
			return $file;
		}

		// Path of the script relative to the plugin folder, as core computes it
		// for the md5 part of the file name (e.g. "blocks/subpage/index.js").
		$src_path    = (string) wp_parse_url( $wp_scripts->registered[ $handle ]->src, PHP_URL_PATH );
		$plugin_path = trailingslashit( (string) wp_parse_url( plugins_url( '', PCHAP__PLUGIN_FILE ), PHP_URL_PATH ) );

		if ( '' === $src_path || '/' === $plugin_path || 0 !== strpos( $src_path, $plugin_path ) ) {
			return $file;
		}

		$relative = substr( $src_path, strlen( $plugin_path ) );

		// Translations are always based on the unminified file name.
		if ( '.min.js' === substr( $relative, -7 ) ) {
			$relative = substr( $relative, 0, -7 ) . '.js';
		}

		$basename = 'paginova-' . determine_locale() . '-' . md5( $relative ) . '.json';

		if ( false === $file ) {
			$bundled = PCHAP_PLUGIN_DIR . 'languages/' . $basename;
			return is_readable( $bundled ) ? $bundled : $file;
		}

		// A language pack of the original plugin (see pchap_prefer_bundled_translations()).
		$bundled = pchap_get_bundled_translation_file( $file, $basename );

		return '' !== $bundled ? $bundled : $file;
	}
}

add_filter( 'load_script_translation_file', 'pchap_load_bundled_script_translation_file', 10, 3 );
