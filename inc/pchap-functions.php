<?php
/**
 * Paginova Common Functions.
 *
 * @package Paginova
 * @subpackage Functions
 * @since 1.4
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/** Versions ******************************************************************/

/**
 * Output the Paginova version.
 *
 * @since 1.4
 */
function pchap_version() {
	echo esc_html( pchap_get_version() );
}

/**
 * Return the Paginova version.
 *
 * @since 1.4
 *
 * @return string The Paginova version.
 */
function pchap_get_version() {
	return pchap()->version;
}

/**
 * Output the Paginova database version.
 *
 * @since 1.4
 */
function pchap_db_version() {
	echo (int) pchap_get_db_version();
}

/**
 * Return the Paginova database version.
 *
 * @since 1.4
 *
 * @return int The Paginova database version.
 */
function pchap_get_db_version() {
	return pchap()->db_version;
}

/**
 * Output the Paginova database version, as stored in the database.
 *
 * @since 1.4
 */
function pchap_db_version_raw() {
	echo (int) pchap_get_db_version_raw();
}

/**
 * Return the Paginova database version, as stored in the database.
 *
 * @since 1.4
 *
 * @return int The Paginova version direct from the database.
 */
function pchap_get_db_version_raw() {
	$mpp = pchap();
	return ! empty( $mpp->db_version_raw ) ? $mpp->db_version_raw : 0;
}

/** Miscellaneous hooks *******************************************************/

/** Hooks *********************************************************************/

/**
 * Name of the Multipage hook that matches a Paginova hook.
 *
 * Post Chapters 2.0 renamed the mpp_* hooks of Multipage to pchap_*; the old
 * names keep working, as deprecated hooks.
 *
 * @since 2.0.0
 * @access private
 *
 * @param string $hook_name A Paginova hook name (pchap_*).
 * @return string The matching mpp_* hook name, or '' for any other hook.
 */
function pchap_get_legacy_hook_name( $hook_name ) {
	return 0 === strpos( $hook_name, 'pchap_' ) ? 'mpp_' . substr( $hook_name, strlen( 'pchap_' ) ) : '';
}

/**
 * Apply a Paginova filter, then its deprecated Multipage name.
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

	$legacy = pchap_get_legacy_hook_name( $hook_name );
	if ( '' !== $legacy && has_filter( $legacy ) ) {
		$value = apply_filters_deprecated( $legacy, array_merge( array( $value ), $args ), '2.0.0', $hook_name ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Deprecated mpp_* name of a pchap_* hook.
	}

	return $value;
}

/**
 * Fire a Paginova action, then its deprecated Multipage name.
 *
 * @since 2.0.0
 *
 * @param string $hook_name The pchap_* action name.
 * @param mixed  ...$args   Arguments passed to the callbacks.
 */
function pchap_do_action( $hook_name, ...$args ) {
	do_action( $hook_name, ...$args ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Always a pchap_* hook.

	$legacy = pchap_get_legacy_hook_name( $hook_name );
	if ( '' !== $legacy && has_action( $legacy ) ) {
		do_action_deprecated( $legacy, $args, '2.0.0', $hook_name ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Deprecated mpp_* name of a pchap_* hook.
	}
}
