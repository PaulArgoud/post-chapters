<?php
/**
 * Multipage Blocks.
 *
 * Registers the "Subpage" block (multipage/subpage) from its block.json.
 *
 * @package Multipage
 * @subpackage Blocks
 * @since 1.7.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'mpp_register_blocks' ) ) {
	/**
	 * Register the Multipage blocks.
	 *
	 * Runs on every request (front end, admin, REST) so that the block type
	 * is known everywhere; the editor script and style are only enqueued by
	 * WordPress inside the block editor.
	 *
	 * @since 1.7.0
	 *
	 * @return void
	 */
	function mpp_register_blocks() {
		if ( ! function_exists( 'register_block_type' ) || ! defined( 'MPP_PLUGIN_DIR' ) ) {
			return;
		}

		$block_type = register_block_type( MPP_PLUGIN_DIR . 'blocks/subpage' );

		if ( ! $block_type instanceof WP_Block_Type ) {
			return;
		}

		/*
		 * JS translations. No $path on purpose: core resolves the files itself
		 * (WP_LANG_DIR/plugins first; WP 6.9+ also falls back to the folder
		 * registered by load_plugin_textdomain()), and
		 * mpp_load_bundled_script_translation_file() swaps a language pack of
		 * the original plugin for the bundled file, or provides the bundled
		 * file as the last resort (6.3 to 6.8 never look into the plugin folder
		 * by themselves).
		 */
		$handles = ! empty( $block_type->editor_script_handles )
			? (array) $block_type->editor_script_handles
			: array( generate_block_asset_handle( 'multipage/subpage', 'editorScript' ) );

		foreach ( $handles as $handle ) {
			wp_set_script_translations( $handle, 'sgr-nextpage-titles' );
		}
	}
}

add_action( 'init', 'mpp_register_blocks' );

if ( ! function_exists( 'mpp_load_bundled_script_translation_file' ) ) {
	/**
	 * Use the JS translations bundled in /languages.
	 *
	 * Hooked to 'load_script_translation_file', for a script of this plugin that
	 * uses the plugin text domain only. A file of WP_LANG_DIR/plugins (a language
	 * pack of the original plugin, see mpp_prefer_bundled_translations()) is
	 * replaced by the bundled "sgr-nextpage-titles-{locale}-{md5}.json" file of
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
	function mpp_load_bundled_script_translation_file( $file, $handle, $domain ) {
		if ( 'sgr-nextpage-titles' !== $domain || ! defined( 'MPP_PLUGIN_DIR' ) || ! defined( 'MPP__PLUGIN_FILE' ) ) {
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
		$plugin_path = trailingslashit( (string) wp_parse_url( plugins_url( '', MPP__PLUGIN_FILE ), PHP_URL_PATH ) );

		if ( '' === $src_path || '/' === $plugin_path || 0 !== strpos( $src_path, $plugin_path ) ) {
			return $file;
		}

		$relative = substr( $src_path, strlen( $plugin_path ) );

		// Translations are always based on the unminified file name.
		if ( '.min.js' === substr( $relative, -7 ) ) {
			$relative = substr( $relative, 0, -7 ) . '.js';
		}

		$basename = 'sgr-nextpage-titles-' . determine_locale() . '-' . md5( $relative ) . '.json';

		if ( false === $file ) {
			$bundled = MPP_PLUGIN_DIR . 'languages/' . $basename;
			return is_readable( $bundled ) ? $bundled : $file;
		}

		// A language pack of the original plugin (see mpp_prefer_bundled_translations()).
		$bundled = mpp_get_bundled_translation_file( $file, $basename );

		return '' !== $bundled ? $bundled : $file;
	}
}

add_filter( 'load_script_translation_file', 'mpp_load_bundled_script_translation_file', 10, 3 );
