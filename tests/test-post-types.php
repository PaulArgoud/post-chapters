<?php
/**
 * Custom post types: chapters work on any public post type, not only on posts.
 *
 * @package Post Chapters
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Test classes use the required Test_PChap_ prefix.

/**
 * A flat custom post type with its own URL base, a hierarchical one, and plain permalinks.
 *
 * @group request
 */
class Test_PChap_Post_Types extends PChap_UnitTestCase {

	/**
	 * Intro, then the chapters "Un" and "Deux".
	 */
	const CONTENT = 'Intro[nextpage title="Un"]Premier[nextpage title="Deux"]Second';

	/**
	 * Register the post types and the rewrite rules.
	 */
	public function set_up() {
		parent::set_up();

		// The permalink structure first: a post type only gets rewrite rules when it is set.
		$this->set_permalink_structure( '/%postname%/' );

		register_post_type(
			'pchap_book',
			array(
				'public'       => true,
				'show_in_rest' => true,
				'supports'     => array( 'title', 'editor', 'comments' ),
				'rewrite'      => array( 'slug' => 'books' ),
			)
		);
		register_post_type(
			'pchap_guide',
			array(
				'public'       => true,
				'hierarchical' => true,
				'show_in_rest' => true,
				'supports'     => array( 'title', 'editor', 'page-attributes' ),
				'rewrite'      => array( 'slug' => 'guides' ),
			)
		);

		flush_rewrite_rules( false );
	}

	/**
	 * Unregister the post types.
	 */
	public function tear_down() {
		unregister_post_type( 'pchap_book' );
		unregister_post_type( 'pchap_guide' );

		parent::tear_down();
	}

	/**
	 * Post types of the tests.
	 *
	 * @return array
	 */
	public function data_post_types() {
		return array(
			'flat custom post type'         => array( 'pchap_book', '/books/mon-livre/' ),
			'hierarchical custom post type' => array( 'pchap_guide', '/guides/mon-livre/' ),
			'page'                          => array( 'page', '/mon-livre/' ),
		);
	}

	/**
	 * A post with chapters.
	 *
	 * @param string $post_type Post type.
	 * @return int Post ID.
	 */
	private function create_chapters_post( $post_type ) {
		return $this->create_post(
			self::CONTENT,
			array(
				'post_type'  => $post_type,
				'post_title' => 'Mon livre',
				'post_name'  => 'mon-livre',
			)
		);
	}

	/**
	 * The chapters have their own URLs, and the plugin renders them.
	 *
	 * @dataProvider data_post_types
	 *
	 * @param string $post_type Post type.
	 * @param string $path      Path of the post.
	 */
	public function test_chapters_of_a_post_type( $post_type, $path ) {
		$post_id = $this->create_chapters_post( $post_type );

		$this->assertSame( home_url( $path ), get_permalink( $post_id ) );

		$content = $this->render_subpage( $post_id, 2 );

		$this->assertSame( $post_type, get_post_type() );
		$this->assertTrue( is_singular( $post_type ) );
		$this->assertFalse( is_404() );
		$this->assertTrue( pchap()->is_multipage_view() );
		$this->assertSame( 2, pchap()->get_current_page() );
		$this->assertSame( 3, pchap()->max_num_pages );
		$this->assertStringContainsString( '<h2>Un</h2>', $content );
		$this->assertStringContainsString( 'Premier', $content );
		$this->assertStringNotContainsString( 'Second', $content );
		$this->assertStringContainsString( 'mpp-toc-container', $content );
		$this->assertStringContainsString( 'mpp-post-navigation', $content );
		$this->assertStringContainsString( home_url( $path . '3/' ), $content, 'The navigation links to the next chapter.' );
	}

	/**
	 * The last chapter exists, the next one does not.
	 *
	 * @dataProvider data_post_types
	 *
	 * @param string $post_type Post type.
	 * @param string $path      Path of the post.
	 */
	public function test_chapter_range( $post_type, $path ) {
		$post_id = $this->create_chapters_post( $post_type );

		$this->go_to( home_url( $path . '3/' ) );
		$this->assertFalse( is_404(), 'The last chapter.' );
		$this->assertSame( 3, pchap()->get_current_page() );

		$this->go_to_subpage( $post_id, 1 );
		$this->go_to( home_url( $path . '4/' ) );
		$this->assertTrue( is_404(), 'Beyond the last chapter.' );
	}

	/**
	 * The title of the document and the rel links are those of the chapter.
	 *
	 * @dataProvider data_post_types
	 *
	 * @param string $post_type Post type.
	 * @param string $path      Path of the post.
	 */
	public function test_head_of_a_chapter( $post_type, $path ) {
		$post_id = $this->create_chapters_post( $post_type );

		$this->go_to_subpage( $post_id, 2 );

		$this->assertStringContainsString( 'Un', wp_get_document_title() );

		$head = (string) get_echo( array( pchap()->seo, 'print_rel_links' ) );
		$this->assertStringContainsString( '<link rel="prev" href="' . home_url( $path ) . '" />', $head );
		$this->assertStringContainsString( '<link rel="next" href="' . home_url( $path . '3/' ) . '" />', $head );
	}

	/**
	 * The index kept for third-party code is written for any post type, and rebuilt for any post type.
	 *
	 * @dataProvider data_post_types
	 *
	 * @param string $post_type Post type.
	 */
	public function test_index_meta( $post_type ) {
		$post_id = $this->create_chapters_post( $post_type );
		$index   = get_post_meta( $post_id, '_mpp_data', true );

		$this->assertSame( 'Un', $index['un'] );
		$this->assertSame( 'Deux', $index['deux'] );

		delete_post_meta( $post_id, '_mpp_data' );
		$stats = pchap_process_postmeta_batch( 0, 1000 );

		$this->assertGreaterThanOrEqual( 1, $stats['updated'] );
		$this->assertSame( 'Un', get_post_meta( $post_id, '_mpp_data', true )['un'] );
	}

	/**
	 * A child of a hierarchical post type keeps its parent in the URL of its chapters.
	 */
	public function test_hierarchical_child() {
		$parent = $this->create_chapters_post( 'pchap_guide' );
		$child  = $this->create_post(
			self::CONTENT,
			array(
				'post_type'   => 'pchap_guide',
				'post_title'  => 'Enfant',
				'post_name'   => 'enfant',
				'post_parent' => $parent,
			)
		);

		$this->assertSame( home_url( '/guides/mon-livre/enfant/' ), get_permalink( $child ) );

		$content = $this->render_subpage( $child, 2 );

		$this->assertSame( $child, pchap()->post_id );
		$this->assertStringContainsString( '<h2>Un</h2>', $content );
		$this->assertStringContainsString( home_url( '/guides/mon-livre/enfant/3/' ), $content );
	}

	/**
	 * Plain permalinks: the chapter is the "page" query variable of the post type.
	 */
	public function test_plain_permalinks() {
		$this->set_permalink_structure( '' );
		$post_id = $this->create_chapters_post( 'pchap_book' );

		$content = $this->render_subpage( $post_id, 2 );

		$this->assertSame( 2, pchap()->get_current_page() );
		$this->assertStringContainsString( '<h2>Un</h2>', $content );
		$this->assertStringContainsString( 'pchap_book=mon-livre', $this->subpage_url( $post_id, 2 ) );
	}

	/**
	 * A post type listed in a secondary query is not decorated, like posts.
	 */
	public function test_secondary_query_is_not_decorated() {
		$post_id = $this->create_chapters_post( 'pchap_book' );
		$this->go_to_subpage( $post_id, 2 );

		$query = new WP_Query(
			array(
				'post_type' => 'pchap_book',
				'p'         => $post_id,
			)
		);
		$query->the_post();
		$content = (string) get_echo( 'the_content' );
		wp_reset_postdata();

		$this->assertStringNotContainsString( 'mpp-toc-container', $content );
	}

	/**
	 * The Chapter block is not tied to a post type.
	 */
	public function test_block_is_available_for_every_post_type() {
		$block = WP_Block_Type_Registry::get_instance()->get_registered( 'multipage/subpage' );

		$this->assertNotNull( $block );
		$this->assertEmpty( $block->parent, 'The block can be inserted anywhere.' );

		$settings = get_block_editor_settings(
			array(),
			new WP_Block_Editor_Context(
				array(
					'post' => get_post( $this->create_chapters_post( 'pchap_book' ) ),
				)
			)
		);
		$allowed  = $settings['allowedBlockTypes'];

		$this->assertTrue( true === $allowed || in_array( 'multipage/subpage', (array) $allowed, true ) );
	}
}
