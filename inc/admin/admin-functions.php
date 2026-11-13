<?php
/**
 * Paginova Admin Functions.
 *
 * @package Paginova
 * @since 1.4
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Highlight the Settings > Paginova menu item, regardless of which actual
 * Paginova admin screen we are on.
 *
 * @since 1.4
 *
 * @global string $plugin_page  The current plugin page.
 * @global string $submenu_file The submenu item to highlight.
 */
function pchap_modify_admin_menu_highlight() {
	global $plugin_page, $submenu_file;

	// This tweaks the Settings subnav menu to show only one Paginova menu item.
	if ( in_array( $plugin_page, array( 'paginova-advanced' ), true ) ) {
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Intended.
		$submenu_file = 'paginova';
	}
}

/**
 * Output the correct admin URL based on WordPress configuration.
 *
 * @since 1.4
 *
 * @param string $path   See {@link pchap_get_admin_url()}.
 * @param string $scheme See {@link pchap_get_admin_url()}.
 */
function pchap_admin_url( $path = '', $scheme = 'admin' ) {
	echo esc_url( pchap_get_admin_url( $path, $scheme ) );
}

/**
 * Return the correct admin URL based on WordPress configuration.
 *
 * @since 1.4
 *
 * @param string $path   Optional. The sub-path under /wp-admin to be
 *                       appended to the admin URL.
 * @param string $scheme The scheme to use. Default is 'admin', which
 *                       obeys {@link force_ssl_admin()} and {@link is_ssl()}. 'http'
 *                       or 'https' can be passed to force those schemes.
 * @return string Admin url link with optional path appended.
 */
function pchap_get_admin_url( $path = '', $scheme = 'admin' ) {
	return admin_url( $path, $scheme );
}

/**
 * Output the tabs in the admin area.
 *
 * @since 1.4
 *
 * @param string $active_tab Name of the tab that is active. Optional.
 */
function pchap_admin_tabs( $active_tab = '' ) {
	$tabs_html    = '';
	$idle_class   = 'nav-tab';
	$active_class = 'nav-tab nav-tab-active';

	/**
	 * Filters the admin tabs to be displayed.
	 *
	 * @since 1.4
	 *
	 * @param array $value Array of tabs to output to the admin area.
	 */
	$tabs = pchap_apply_filters( 'pchap_admin_tabs', pchap_get_admin_tabs( $active_tab ) );

	// Loop through tabs and build navigation.
	foreach ( array_values( (array) $tabs ) as $tab_data ) {
		if ( ! is_array( $tab_data ) || ! isset( $tab_data['name'], $tab_data['href'] ) ) {
			continue;
		}

		$is_current = ( $tab_data['name'] === $active_tab );
		$tab_class  = $is_current ? $active_class : $idle_class;
		$tabs_html .= '<a href="' . esc_url( $tab_data['href'] ) . '" class="' . esc_attr( $tab_class ) . '"' . ( $is_current ? ' aria-current="page"' : '' ) . '>' . esc_html( $tab_data['name'] ) . '</a>';
	}

	echo wp_kses(
		$tabs_html,
		array(
			'a' => array(
				'href'         => true,
				'class'        => true,
				'aria-current' => true,
			),
		)
	);

	/**
	 * Fires after the output of tabs for the admin area.
	 *
	 * @since 1.4
	 */
	pchap_do_action( 'pchap_admin_tabs' );
}

/**
 * Get the data for the tabs in the admin area.
 *
 * @since 1.4
 *
 * @param string $active_tab Name of the tab that is active. Optional.
 * @return array
 */
function pchap_get_admin_tabs( $active_tab = '' ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Public 1.4+ signature kept for backward compatibility.
	$tabs = array(
		'0' => array(
			'href' => pchap_get_admin_url( add_query_arg( array( 'page' => 'paginova' ), 'options-general.php' ) ),
			'name' => __( 'Options', 'paginova' ),
		),
		'1' => array(
			'href' => pchap_get_admin_url( add_query_arg( array( 'page' => 'paginova-advanced' ), 'options-general.php' ) ),
			'name' => __( 'Advanced', 'paginova' ),
		),
	);

	/**
	 * Filters the tab data used in our wp-admin screens.
	 *
	 * @since 1.4
	 *
	 * @param array $tabs Tab data.
	 */
	return pchap_apply_filters( 'pchap_get_admin_tabs', $tabs );
}

/**
 * Check if the Block Editor may be used.
 *
 * Returns false only when the Classic Editor plugin forces the classic
 * editor for everybody. Must only be used after the plugins_loaded action.
 *
 * Mirrors Classic_Editor::get_settings() (Classic Editor 1.6+, checked with
 * 1.7.0): the `classic_editor_plugin_settings` filter; on multisite, the
 * network defaults (`classic_editor_network_default_settings` filter),
 * overridden by the site options when per-site settings are allowed; on a
 * single site, the `classic-editor-replace` / `classic-editor-allow-users`
 * options. Per-user choices are not checked: when users may switch editors,
 * the block editor can be used.
 *
 * @since 1.4
 *
 * @return bool
 */
function pchap_is_block_editor_active() {
	if ( ! pchap_is_classic_editor_plugin_active() ) {
		return true;
	}

	/** This filter is documented in the Classic Editor plugin. */
	$settings = apply_filters( 'classic_editor_plugin_settings', false ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Classic Editor plugin hook.

	if ( is_array( $settings ) ) {
		$editor      = ( isset( $settings['editor'] ) && 'block' === $settings['editor'] ) ? 'block' : 'classic';
		$allow_users = ! empty( $settings['allow-users'] );
	} elseif ( is_multisite() ) {
		// Network defaults.
		$defaults = array(
			'editor'      => 'block' === get_network_option( null, 'classic-editor-replace' ) ? 'block' : 'classic',
			'allow-users' => false,
		);

		/** This filter is documented in the Classic Editor plugin. */
		$defaults = apply_filters( 'classic_editor_network_default_settings', $defaults ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Classic Editor plugin hook.

		if ( 'allow' === get_network_option( null, 'classic-editor-allow-sites' ) ) {
			// Per-site settings are allowed: the site options override the network defaults when set.
			$editor_option      = get_option( 'classic-editor-replace' );
			$allow_users_option = get_option( 'classic-editor-allow-users' );

			if ( $editor_option ) {
				$defaults['editor'] = $editor_option;
			}
			if ( $allow_users_option ) {
				$defaults['allow-users'] = ( 'allow' === $allow_users_option );
			}
		}

		$editor      = ( is_array( $defaults ) && isset( $defaults['editor'] ) && 'block' === $defaults['editor'] ) ? 'block' : 'classic';
		$allow_users = is_array( $defaults ) && ! empty( $defaults['allow-users'] );
	} else {
		// Single site. Normalize the old 'no-replace' value as the Classic Editor plugin does.
		$editor_option = get_option( 'classic-editor-replace' );
		$editor        = in_array( $editor_option, array( 'block', 'no-replace' ), true ) ? 'block' : 'classic';
		$allow_users   = ( 'allow' === get_option( 'classic-editor-allow-users' ) );
	}

	// When users may switch editors, the block editor can be used.
	return 'block' === $editor || $allow_users;
}

/**
 * Check if Classic Editor plugin is active.
 *
 * @since 1.4
 *
 * @return bool
 */
function pchap_is_classic_editor_plugin_active() {
	if ( ! function_exists( 'is_plugin_active' ) ) {
		include_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	return is_plugin_active( 'classic-editor/classic-editor.php' );
}
