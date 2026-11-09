<?php
/**
 * Page builders: a post rendered by Elementor, Divi, Beaver Builder or Bricks is not split.
 *
 * @package Post Chapters
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Test classes use the required Test_PChap_ prefix and tests apply core and third-party hooks.

/**
 * PChap::is_builder_post().
 *
 * @group request
 */
class Test_PChap_Builders extends PChap_UnitTestCase {

	/**
	 * Intro, then the chapters "Un" and "Deux".
	 */
	const CONTENT = 'Intro[nextpage title="Un"]Premier[nextpage title="Deux"]Second';

	/**
	 * Post meta that tell a builder renders the post, with the value it sets.
	 *
	 * @return array
	 */
	public function data_builders() {
		return array(
			'Elementor'      => array( '_elementor_edit_mode', 'builder' ),
			'Divi'           => array( '_et_pb_use_builder', 'on' ),
			'Beaver Builder' => array( '_fl_builder_enabled', '1' ),
			'Bricks'         => array( '_bricks_editor_mode', 'bricks' ),
		);
	}

	/**
	 * Values of the same meta that mean the builder is not used for the post.
	 *
	 * @return array
	 */
	public function data_builders_off() {
		return array(
			'Elementor, edit mode off' => array( '_elementor_edit_mode', '' ),
			'Divi, builder off'        => array( '_et_pb_use_builder', 'off' ),
			'Beaver Builder, disabled' => array( '_fl_builder_enabled', '0' ),
			'Bricks, WordPress editor' => array( '_bricks_editor_mode', 'wordpress' ),
		);
	}

	/**
	 * A post rendered by a builder is not split: no chapters, no table of contents.
	 *
	 * @dataProvider data_builders
	 *
	 * @param string $meta_key   Post meta key.
	 * @param string $meta_value Value.
	 */
	public function test_builder_post_is_not_split( $meta_key, $meta_value ) {
		$post_id = $this->create_post( self::CONTENT );
		update_post_meta( $post_id, $meta_key, $meta_value );

		$this->assertTrue( PChap::is_builder_post( $post_id ) );

		$content = $this->render_subpage( $post_id, 1 );

		$this->assertFalse( pchap()->is_multipage_view() );
		$this->assertFalse( pchap()->setup() );
		$this->assertStringNotContainsString( 'mpp-toc-container', $content );
		$this->assertStringNotContainsString( 'mpp-post-navigation', $content );
		$this->assertFalse( has_filter( 'the_content', array( pchap(), 'pchap_the_content' ) ), 'The content filter of the plugin is not hooked.' );
	}

	/**
	 * With the builder off for the post, the chapters work.
	 *
	 * @dataProvider data_builders_off
	 *
	 * @param string $meta_key   Post meta key.
	 * @param string $meta_value Value.
	 */
	public function test_builder_meta_off_keeps_chapters( $meta_key, $meta_value ) {
		$post_id = $this->create_post( self::CONTENT );
		update_post_meta( $post_id, $meta_key, $meta_value );

		$this->assertFalse( PChap::is_builder_post( $post_id ) );

		$content = $this->render_subpage( $post_id, 2 );

		$this->assertTrue( pchap()->is_multipage_view() );
		$this->assertStringContainsString( '<h2>Un</h2>', $content );
		$this->assertStringContainsString( 'mpp-toc-container', $content );
	}

	/**
	 * A post without any builder meta is split.
	 */
	public function test_post_without_builder_is_split() {
		$post_id = $this->create_post( self::CONTENT );

		$this->assertFalse( PChap::is_builder_post( $post_id ) );
		$this->assertStringContainsString( 'mpp-toc-container', $this->render_subpage( $post_id, 2 ) );
	}

	/**
	 * The filter splits a builder post anyway, or leaves another builder's posts alone.
	 */
	public function test_filter() {
		$builder_post = $this->create_post( self::CONTENT );
		update_post_meta( $builder_post, '_elementor_edit_mode', 'builder' );
		$other_post = $this->create_post( self::CONTENT, array( 'post_name' => 'autre' ) );
		update_post_meta( $other_post, '_other_builder', 'yes' );

		add_filter(
			'pchap_is_builder_post',
			static function ( $is_builder, $post_id ) use ( $builder_post, $other_post ) {
				if ( $post_id === $builder_post ) {
					return false;
				}
				return $post_id === $other_post ? true : $is_builder;
			},
			10,
			2
		);

		$this->assertFalse( PChap::is_builder_post( $builder_post ) );
		$this->assertTrue( PChap::is_builder_post( $other_post ) );

		$this->assertStringContainsString( 'mpp-toc-container', $this->render_subpage( $builder_post, 2 ) );
		$this->assertStringNotContainsString( 'mpp-toc-container', $this->render_subpage( $other_post, 1 ) );
	}
}
