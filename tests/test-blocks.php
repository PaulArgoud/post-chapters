<?php
/**
 * Block registration tests.
 *
 * @package Paginova
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Test classes use the required Test_PChap_ prefix and tests apply core and third-party hooks.

/**
 * The multipage/subpage block, registered from blocks/subpage/block.json.
 *
 * @group multipage
 * @group blocks
 */
class Test_PChap_Blocks extends PChap_UnitTestCase {

	/**
	 * The registered block type.
	 *
	 * @return WP_Block_Type
	 */
	private function block_type() {
		$block_type = WP_Block_Type_Registry::get_instance()->get_registered( 'multipage/subpage' );
		$this->assertInstanceOf( 'WP_Block_Type', $block_type, 'multipage/subpage is not registered.' );
		return $block_type;
	}

	/**
	 * The block is registered on init, with API version 3.
	 */
	public function test_block_is_registered() {
		$this->assertSame( 10, has_action( 'init', 'pchap_register_blocks' ) );

		$block_type = $this->block_type();

		$this->assertSame( 3, $block_type->api_version );
		$this->assertFalse( $block_type->is_dynamic(), 'Static block: the saved [nextpage] shortcode is the content.' );
	}

	/**
	 * The title attribute defaults to false (save() of 1.6 posts depends on it).
	 */
	public function test_block_attributes() {
		$attributes = $this->block_type()->attributes;

		$this->assertArrayHasKey( 'title', $attributes );
		$this->assertSame( 'string', $attributes['title']['type'] );
		$this->assertArrayHasKey( 'default', $attributes['title'] );
		$this->assertFalse( $attributes['title']['default'] );
	}

	/**
	 * Supports from block.json.
	 */
	public function test_block_supports() {
		$supports = $this->block_type()->supports;

		$this->assertFalse( $supports['className'] );
		$this->assertFalse( $supports['customClassName'] );
		$this->assertTrue( $supports['html'] );
	}

	/**
	 * The editor script and style are registered, the script with the plugin text domain
	 * and a way to load the bundled translations (explicit path, or the bundled-file fallback).
	 */
	public function test_editor_assets() {
		$block_type = $this->block_type();

		$this->assertNotEmpty( $block_type->editor_script_handles );
		$this->assertNotEmpty( $block_type->editor_style_handles );

		$scripts = wp_scripts();
		foreach ( $block_type->editor_script_handles as $handle ) {
			$this->assertArrayHasKey( $handle, $scripts->registered, "Script {$handle} is not registered." );

			$script = $scripts->registered[ $handle ];
			$this->assertSame( 'paginova', $script->textdomain );
			$this->assertTrue(
				untrailingslashit( PCHAP_PLUGIN_DIR . 'languages' ) === untrailingslashit( (string) $script->translations_path )
					|| false !== has_filter( 'load_script_translation_file', 'pchap_load_bundled_script_translation_file' ),
				'Neither a translations path nor the bundled translations fallback is set.'
			);
			$this->assertStringEndsWith( 'blocks/subpage/index.js', strtok( $script->src, '?' ) );
			$this->assertSame( PCHAP_VERSION, $script->ver );

			foreach ( array( 'wp-blocks', 'wp-block-editor', 'wp-element', 'wp-i18n' ) as $dependency ) {
				$this->assertContains( $dependency, $script->deps );
			}
		}

		$styles = wp_styles();
		foreach ( $block_type->editor_style_handles as $handle ) {
			$this->assertArrayHasKey( $handle, $styles->registered, "Style {$handle} is not registered." );
		}
	}

	/**
	 * The block files exist.
	 */
	public function test_block_files() {
		foreach ( array( 'block.json', 'index.js', 'index.asset.php', 'editor.css' ) as $file ) {
			$this->assertFileExists( PCHAP_PLUGIN_DIR . 'blocks/subpage/' . $file );
		}

		$metadata = wp_json_file_decode( PCHAP_PLUGIN_DIR . 'blocks/subpage/block.json', array( 'associative' => true ) );
		$this->assertSame( 'multipage/subpage', $metadata['name'] );
		$this->assertSame( 3, $metadata['apiVersion'] );
	}

	/**
	 * Rendering the block on its own (outside a multipage view) outputs no shortcode text.
	 */
	public function test_block_render_outside_multipage_view() {
		foreach ( array( do_blocks( PChap_Test_Corpus::block( 'Deux' ) ), render_block( parse_blocks( PChap_Test_Corpus::block( 'Deux' ) )[0] ) ) as $html ) {
			$this->assertStringNotContainsString( '[nextpage', $html );
			$this->assertStringNotContainsString( 'Deux', $html );
		}
	}
}
