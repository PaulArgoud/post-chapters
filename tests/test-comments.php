<?php
/**
 * Comments visibility tests.
 *
 * @package Paginova
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Test classes use the required Test_PChap_ prefix and tests apply core and third-party hooks.

/**
 * The "Display comments on" setting, for classic themes (comments_template)
 * and block themes (core/comments blocks).
 *
 * @group multipage
 * @group comments
 */
class Test_PChap_Comments extends PChap_UnitTestCase {

	/**
	 * Three-page classic post.
	 *
	 * @var string
	 */
	const CLASSIC = "Intro\n\n[nextpage title=\"Deux\"]\n\nTwo\n\n[nextpage title=\"Trois\"]\n\nThree";

	/**
	 * A core/comments block, as saved by the block editor (non-legacy).
	 *
	 * @var string
	 */
	const COMMENTS_BLOCK = '<!-- wp:comments --><div class="wp-block-comments"><!-- wp:comments-title /--><!-- wp:post-comments-form /--></div><!-- /wp:comments -->';

	/**
	 * Data provider: setting => array( visible on page 1, 2, 3 ).
	 *
	 * @return array
	 */
	public function data_comments_on_page() {
		return array(
			'all'        => array( 'all', array( true, true, true ) ),
			'first-page' => array( 'first-page', array( true, false, false ) ),
			'last-page'  => array( 'last-page', array( false, false, true ) ),
			'invalid'    => array( 'bogus', array( true, true, true ) ),
		);
	}

	/**
	 * Classic themes: comments_template is replaced by an empty file where comments are hidden.
	 *
	 * @dataProvider data_comments_on_page
	 *
	 * @param string $setting  Setting value.
	 * @param bool[] $visible  Visibility on pages 1, 2 and 3.
	 */
	public function test_comments_template( $setting, $visible ) {
		$this->set_options( array( 'pchap-comments-on-page' => $setting ) );
		$post_id  = $this->create_post( self::CLASSIC );
		$template = '/theme/comments.php';

		foreach ( $visible as $index => $is_visible ) {
			$page = $index + 1;
			$this->go_to_subpage( $post_id, $page );
			the_post();

			$filtered = apply_filters( 'comments_template', $template );

			if ( $is_visible ) {
				$this->assertSame( $template, $filtered, "Comments must be visible on page {$page}." );
			} else {
				$this->assertSame( PCHAP_PLUGIN_DIR . 'index.php', $filtered, "Comments must be hidden on page {$page}." );
				$this->assertFileExists( $filtered );
				$this->assertSame( '', trim( (string) get_echo( 'comments_template' ) ), 'The replacement template outputs nothing.' );
			}

			$this->assertSame( $is_visible, pchap()->comments->allowed_on_current_page() );
		}
	}

	/**
	 * Block themes: the core comment blocks render nothing where comments are hidden.
	 *
	 * @dataProvider data_comments_on_page
	 *
	 * @param string $setting  Setting value.
	 * @param bool[] $visible  Visibility on pages 1, 2 and 3.
	 */
	public function test_comment_blocks( $setting, $visible ) {
		$this->set_options( array( 'pchap-comments-on-page' => $setting ) );
		$post_id = $this->create_post( self::CLASSIC, array( 'comment_status' => 'open' ) );

		foreach ( $visible as $index => $is_visible ) {
			$page = $index + 1;
			$this->go_to_subpage( $post_id, $page );
			the_post();

			$html = do_blocks( self::COMMENTS_BLOCK );

			if ( $is_visible ) {
				$this->assertStringContainsString( 'wp-block-comments', $html, "Comments block must be rendered on page {$page}." );
			} else {
				$this->assertSame( '', trim( $html ), "Comments block must be hidden on page {$page}." );
			}

			foreach ( array( 'core/comments', 'core/post-comments-form', 'core/comments-title', 'core/comment-template' ) as $block_name ) {
				$pre = apply_filters(
					'pre_render_block',
					null,
					array(
						'blockName'    => $block_name,
						'attrs'        => array(),
						'innerBlocks'  => array(),
						'innerHTML'    => '',
						'innerContent' => array(),
					),
					null
				);
				$this->assertSame( $is_visible ? null : '', $pre, "{$block_name} on page {$page}." );
			}

			wp_reset_postdata();
		}
	}

	/**
	 * Other blocks are never hidden, and the comment blocks of other posts neither.
	 */
	public function test_other_blocks_and_posts_are_not_hidden() {
		$this->set_options( array( 'pchap-comments-on-page' => 'first-page' ) );
		$post_id  = $this->create_post( self::CLASSIC );
		$other_id = $this->create_post( 'Other', array( 'post_title' => 'Other' ) );

		$this->go_to_subpage( $post_id, 2 );
		the_post();

		$this->assertStringContainsString( 'Paragraph', do_blocks( '<!-- wp:paragraph --><p>Paragraph</p><!-- /wp:paragraph -->' ) );

		$secondary = new WP_Query( array( 'p' => $other_id ) );
		$secondary->the_post();
		$this->assertStringContainsString( 'wp-block-comments', do_blocks( self::COMMENTS_BLOCK ), 'Comments of another post are not hidden.' );
		wp_reset_postdata();
	}

	/**
	 * Comments are not hidden on posts that are not multipage.
	 */
	public function test_plain_post_comments_are_not_hidden() {
		$this->set_options( array( 'pchap-comments-on-page' => 'last-page' ) );
		$post_id = $this->create_post( 'Plain' );

		$this->go_to_subpage( $post_id, 1 );
		the_post();

		$this->assertSame( '/theme/comments.php', apply_filters( 'comments_template', '/theme/comments.php' ) );
		$this->assertStringContainsString( 'wp-block-comments', do_blocks( self::COMMENTS_BLOCK ) );
	}
}
