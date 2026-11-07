<?php
/**
 * End-to-end invariant: WordPress' own pagination matches the parser.
 *
 * @package Multipage
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Test classes use the required Test_Mpp_ prefix and tests apply core and third-party hooks.

/**
 * For every corpus item: create the post, request its first page and every
 * subpage like WordPress does (WP::main(), then the loop), and check that the
 * number of WordPress pages equals the number of parsed pages and that each
 * subpage renders its own title and body.
 *
 * @group multipage
 * @group request
 */
class Test_Mpp_Request_Invariant extends Mpp_UnitTestCase {

	/**
	 * Data provider: the shared corpus.
	 *
	 * @return array
	 */
	public function data_corpus() {
		$data = array();
		foreach ( Mpp_Test_Corpus::items() as $name => $item ) {
			$data[ $name ] = array( $item['content'], $item['titles'] );
		}
		return $data;
	}

	/**
	 * Plain permalinks (?p=ID&page=N).
	 *
	 * @dataProvider data_corpus
	 *
	 * @param string   $content Post content.
	 * @param string[] $titles  Expected display titles.
	 */
	public function test_every_subpage_with_plain_permalinks( $content, $titles ) {
		$this->assert_every_subpage( $content, $titles );
	}

	/**
	 * Pretty permalinks (/post-name/N/).
	 *
	 * @dataProvider data_corpus
	 *
	 * @param string   $content Post content.
	 * @param string[] $titles  Expected display titles.
	 */
	public function test_every_subpage_with_pretty_permalinks( $content, $titles ) {
		$this->set_permalink_structure( '/%postname%/' );

		$this->assert_every_subpage( $content, $titles );
	}

	/**
	 * Request every subpage of a post and check the invariant.
	 *
	 * @param string   $content Post content.
	 * @param string[] $titles  Expected display titles.
	 */
	private function assert_every_subpage( $content, $titles ) {
		$post_id  = $this->create_post( $content );
		$parsed   = Multipage_Parser::parse( get_post( $post_id )->post_content );
		$expected = count( $parsed['pages'] );

		$this->assertTrue( $parsed['is_multipage'] );
		$this->assertSame( count( $titles ), $expected, 'Parsed page count.' );

		for ( $number = 1; $number <= $expected; $number++ ) {
			$this->go_to_subpage( $post_id, $number );

			$this->assertFalse( is_404(), "Subpage {$number} must not be a 404." );
			$this->assertTrue( is_singular() );
			$this->assertSame( $titles, $this->current_display_titles(), 'Display titles.' );

			$html = $this->render_main_loop_content();

			// Read the globals set by setup_postdata() (go_to() unsets them, so no "global" binding).
			$this->assertSame( $expected, (int) $GLOBALS['numpages'], "WordPress \$numpages on subpage {$number}." );
			$this->assertCount( $expected, $GLOBALS['pages'], "WordPress \$pages on subpage {$number}." );
			$this->assertSame( $number, (int) $GLOBALS['page'] );
			$this->assertSame( $expected > 1 ? 1 : 0, (int) $GLOBALS['multipage'] );
			$this->assertSame( $number, multipage()->get_current_page() );

			// Each subpage renders its own title, once, as the page heading.
			$title_html = mpp_get_subpage_title_html( $titles[ $number - 1 ], multipage()->get_title_format( $number - 1 ) );
			$this->assertSame( 1, substr_count( $html, '<h2>' . $title_html . '</h2>' ), "Heading of subpage {$number}: {$html}" );
			$this->assertStringContainsString( '<span class="current" aria-current="page">' . $title_html . '</span>', $html, "Current TOC item of subpage {$number}." );
			$this->assertSame( 1, substr_count( $html, 'mpp-toc-container' ), 'One table of contents.' );

			// Its own body only.
			$this->assertStringContainsString( 'BODY-' . $number, $html );
			for ( $other = 1; $other <= $expected; $other++ ) {
				if ( $other !== $number ) {
					$this->assertStringNotContainsString( 'BODY-' . $other, $html, "Subpage {$number} shows the body of subpage {$other}." );
				}
			}

			// No marker leaks.
			$this->assertStringNotContainsString( '[nextpage', $html );
			$this->assertStringNotContainsString( 'title="false"', $html );

			// Well-formed: the TOC and the navigation are never inside an element left open by the split.
			foreach ( array( 'h2', 'h3', 'p', 'div', 'nav', 'ul', 'li' ) as $tag ) {
				$this->assertTagBalanced( $tag, $html, "Subpage {$number}." );
			}

			$nav_position = strpos( $html, 'mpp-post-navigation' );
			if ( false !== $nav_position ) {
				$before_nav = substr( $html, 0, $nav_position );
				foreach ( array( 'h1', 'h2', 'h3', 'h4', 'p' ) as $tag ) {
					$this->assertTagBalanced( $tag, $before_nav, "The navigation of subpage {$number} is inside a <{$tag}>." );
				}
			}

			wp_reset_postdata();
		}

		// One past the last subpage is a 404.
		$this->go_to_subpage( $post_id, $expected + 1 );
		$this->assertTrue( is_404(), 'Out-of-range subpage must be a 404.' );
	}

	/**
	 * /post/1/ and /post/ are the same first page.
	 */
	public function test_page_one_query_var_is_the_first_page() {
		$post_id = $this->create_post( "Intro\n\n[nextpage title=\"Deux\"]\n\nTwo" );

		$this->go_to( add_query_arg( 'page', 1, get_permalink( $post_id ) ) );

		$this->assertFalse( is_404() );
		$this->assertSame( 1, multipage()->get_current_page() );
		$this->assertSame( 1, multipage()->page );
	}

	/**
	 * Posts with core page breaks only keep the core behavior (the plugin does nothing).
	 */
	public function test_core_page_breaks_only_post_keeps_core_pagination() {
		$post_id = $this->create_post( "One\n<!--nextpage-->\nTwo\n<!--nextpage-->\nThree" );

		$this->go_to_subpage( $post_id, 2 );
		$html = $this->render_main_loop_content();

		$this->assertFalse( is_404() );
		$this->assertSame( 3, (int) $GLOBALS['numpages'] );
		$this->assertFalse( multipage()->mpp_data );
		$this->assertStringContainsString( 'Two', $html );
		$this->assertStringNotContainsString( 'mpp-toc', $html );
		$this->assertStringNotContainsString( 'mpp-post-navigation', $html );

		$this->go_to_subpage( $post_id, 4 );
		$this->assertTrue( is_404() );
	}

	/**
	 * A fresh get_post() instance of the multipage post gets the same pages (content_pagination).
	 */
	public function test_fresh_post_instance_gets_the_same_pages() {
		$post_id = $this->create_post( Mpp_Test_Corpus::items()['classic shortcodes inside p']['content'] );

		$this->go_to_subpage( $post_id, 2 );

		clean_post_cache( $post_id );
		$fresh = get_post( $post_id );
		$this->assertStringContainsString( '[nextpage', $fresh->post_content, 'The raw content is untouched in the database.' );

		$query    = new WP_Query();
		$postdata = $query->generate_postdata( $fresh );

		$this->assertSame( 3, $postdata['numpages'] );
		$this->assertSame( array_column( multipage()->pages, 'content' ), $postdata['pages'] );
	}

	/**
	 * The raw post content stored in the database is never modified by a request.
	 */
	public function test_request_does_not_modify_the_stored_content() {
		$content = Mpp_Test_Corpus::items()['blocks 1.7']['content'];
		$post_id = $this->create_post( $content );

		$this->render_subpage( $post_id, 2 );

		global $wpdb;
		$stored = $wpdb->get_var( $wpdb->prepare( "SELECT post_content FROM {$wpdb->posts} WHERE ID = %d", $post_id ) );
		$this->assertSame( $content, $stored );
	}
}
