<?php
/**
 * Post Chapters Common Functions.
 *
 * @package Post Chapters
 * @subpackage Functions
 * @since 1.4
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/** Versions ******************************************************************/

/**
 * Output the Post Chapters version.
 *
 * @since 1.4
 */
function pchap_version() {
	echo esc_html( pchap_get_version() );
}

/**
 * Return the Post Chapters version.
 *
 * @since 1.4
 *
 * @return string The Post Chapters version.
 */
function pchap_get_version() {
	return pchap()->version;
}

/**
 * Output the Post Chapters database version.
 *
 * @since 1.4
 */
function pchap_db_version() {
	echo (int) pchap_get_db_version();
}

/**
 * Return the Post Chapters database version.
 *
 * @since 1.4
 *
 * @return int The Post Chapters database version.
 */
function pchap_get_db_version() {
	return pchap()->db_version;
}

/**
 * Output the Post Chapters database version, as stored in the database.
 *
 * @since 1.4
 */
function pchap_db_version_raw() {
	echo (int) pchap_get_db_version_raw();
}

/**
 * Return the Post Chapters database version, as stored in the database.
 *
 * @since 1.4
 *
 * @return int The Post Chapters version direct from the database.
 */
function pchap_get_db_version_raw() {
	$mpp = pchap();
	return ! empty( $mpp->db_version_raw ) ? $mpp->db_version_raw : 0;
}

/** Miscellaneous hooks *******************************************************/

/**
 * Load the Post Chapters translation files for the current language.
 *
 * Since WordPress 6.7 load_plugin_textdomain() only registers a custom
 * lookup path; the files are loaded just in time on the first translation
 * call. WP_Textdomain_Registry looks into WP_LANG_DIR/plugins first and only
 * falls back to the path registered here. The language packs found there for
 * this slug belong to the original plugin on translate.wordpress.org and lack
 * the strings of this version, so pchap_load_bundled_textdomain_mofile() swaps
 * them for the bundled file of the same locale, when there is one.
 *
 * @since 1.4
 * @since 1.7.0 Registers the bundled /languages directory as the fallback path.
 *
 * @see load_plugin_textdomain() for a description of return values.
 *
 * @return bool True on success, false on failure.
 */
function pchap_load_textdomain() {
	return load_plugin_textdomain(
		'post-chapters',
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
	if ( 'post-chapters' !== $domain || ! is_string( $mofile ) ) {
		return $mofile;
	}

	$basename = wp_basename( $mofile );
	if ( ! preg_match( '/^post-chapters-[A-Za-z0-9_@-]+\.mo$/', $basename ) ) {
		return $mofile;
	}

	$bundled = pchap_get_bundled_translation_file( $mofile, $basename );

	return '' !== $bundled ? $bundled : $mofile;
}
add_filter( 'load_textdomain_mofile', 'pchap_load_bundled_textdomain_mofile', 10, 2 );

/** Hooks *********************************************************************/

/**
 * Name of the Multipage hook that matches a Post Chapters hook.
 *
 * Post Chapters 2.0 renamed the mpp_* hooks of Multipage to pchap_*; the old
 * names keep working, as deprecated hooks.
 *
 * @since 2.0.0
 * @access private
 *
 * @param string $hook_name A Post Chapters hook name (pchap_*).
 * @return string The matching mpp_* hook name, or '' for any other hook.
 */
function _pchap_get_legacy_hook_name( $hook_name ) {
	return 0 === strpos( $hook_name, 'pchap_' ) ? 'mpp_' . substr( $hook_name, strlen( 'pchap_' ) ) : '';
}

/**
 * Apply a Post Chapters filter, then its deprecated Multipage name.
 *
 * Callbacks hooked to the mpp_* name (Multipage 1.x) still run, after the
 * pchap_* ones, and trigger a deprecation notice when WP_DEBUG is on.
 *
 * @since 2.0.0
 *
 * @param string $hook_name The pchap_* filter name.
 * @param mixed  $value     The value to filter.
 * @param mixed  ...$args   Additional arguments passed to the callbacks.
 * @return mixed The filtered value.
 */
function pchap_apply_filters( $hook_name, $value, ...$args ) {
	$value = apply_filters( $hook_name, $value, ...$args ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Always a pchap_* hook.

	$legacy = _pchap_get_legacy_hook_name( $hook_name );
	if ( '' !== $legacy && has_filter( $legacy ) ) {
		$value = apply_filters_deprecated( $legacy, array_merge( array( $value ), $args ), '2.0.0', $hook_name ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Deprecated mpp_* name of a pchap_* hook.
	}

	return $value;
}

/**
 * Fire a Post Chapters action, then its deprecated Multipage name.
 *
 * @since 2.0.0
 *
 * @param string $hook_name The pchap_* action name.
 * @param mixed  ...$args   Arguments passed to the callbacks.
 */
function pchap_do_action( $hook_name, ...$args ) {
	do_action( $hook_name, ...$args ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Always a pchap_* hook.

	$legacy = _pchap_get_legacy_hook_name( $hook_name );
	if ( '' !== $legacy && has_action( $legacy ) ) {
		do_action_deprecated( $legacy, $args, '2.0.0', $hook_name ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Deprecated mpp_* name of a pchap_* hook.
	}
}
