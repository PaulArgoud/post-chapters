<?php
/**
 * Paginova Blocks.
 *
 * Registers the "Subpage" block (multipage/subpage) from its block.json.
 *
 * @package Paginova
 * @subpackage Blocks
 * @since 1.7.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'pchap_register_blocks' ) ) {
	/**
	 * Register the Paginova blocks.
	 *
	 * Runs on every request (front end, admin, REST) so that the block type
	 * is known everywhere; the editor script and style are only enqueued by
	 * WordPress inside the block editor.
	 *
	 * @since 1.7.0
	 *
	 * @return void
	 */
	function pchap_register_blocks() {
		if ( ! function_exists( 'register_block_type' ) || ! defined( 'PCHAP_PLUGIN_DIR' ) ) {
			return;
		}

		$block_type = register_block_type( PCHAP_PLUGIN_DIR . 'blocks/subpage' );

		if ( ! $block_type instanceof WP_Block_Type ) {
			return;
		}

		/*
		 * JS translations. No $path on purpose: core resolves the files itself
		 * (WP_LANG_DIR/plugins). With the bundled translations (inc/pchap-l10n.php),
		 * pchap_load_bundled_script_translation_file() swaps a language pack for
		 * the bundled file, or provides the bundled file as the last resort.
		 */
		$handles = ! empty( $block_type->editor_script_handles )
			? (array) $block_type->editor_script_handles
			: array( generate_block_asset_handle( 'multipage/subpage', 'editorScript' ) );

		foreach ( $handles as $handle ) {
			wp_set_script_translations( $handle, 'paginova' );
		}
	}
}

add_action( 'init', 'pchap_register_blocks' );
