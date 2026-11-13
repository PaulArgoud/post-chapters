<?php
/**
 * [nextpage] shortcode tests.
 *
 * @package Paginova
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Test classes use the required Test_PChap_ prefix and tests apply core and third-party hooks.

/**
 * The [nextpage] shortcode where the content is not split (feeds, REST, do_shortcode()).
 *
 * @group multipage
 * @group shortcode
 */
class Test_PChap_Shortcode extends PChap_UnitTestCase {

	/**
	 * Classic multipage content.
	 *
	 * @var string
	 */
	const CLASSIC = "Intro BODY-1\n\n[nextpage title=\"Deux\"]\n\nBODY-2\n\n[nextpage title=\"Trois\" slug=\"three\"]\n\nBODY-3";

	/**
	 * Function do_shortcode(): the shortcode renders nothing.
	 */
	public function test_do_shortcode_renders_nothing() {
		$this->assertTrue( shortcode_exists( 'nextpage' ) );
		$this->assertSame( 'ab', do_shortcode( 'a[nextpage title="X"]b' ) );
		$this->assertSame( 'ab', do_shortcode( 'a[nextpage]b' ) );
		$this->assertSame( 'a<!--nextpage-->b', do_shortcode( 'a<!--nextpage-->b' ), 'Core breaks untouched.' );
		$this->assertSame( 'a[nextpage title="X"]b', do_shortcode( 'a[[nextpage title="X"]]b' ), 'Escaped shortcode displayed literally.' );
		$this->assertSame( '', PChap_Shortcodes::nextpage( array( 'title' => 'X' ), null ) );
	}

	/**
	 * Filter the_content outside a multipage view: all pages, no shortcode text, no TOC.
	 */
	public function test_the_content_outside_multipage_view() {
		$html = apply_filters( 'the_content', self::CLASSIC );

		foreach ( array( 'BODY-1', 'BODY-2', 'BODY-3' ) as $body ) {
			$this->assertStringContainsString( $body, $html );
		}
		$this->assertStringNotContainsString( '[nextpage', $html );
		$this->assertStringNotContainsString( 'title=', $html );
		$this->assertStringNotContainsString( 'mpp-toc', $html );
	}

	/**
	 * REST API: rendered content holds every page and no shortcode.
	 */
	public function test_rest_api_content() {
		$post_id = $this->create_post( self::CLASSIC );

		$response = rest_do_request( new WP_REST_Request( 'GET', '/wp/v2/posts/' . $post_id ) );
		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$html = $data['content']['rendered'];

		$this->assertStringContainsString( 'BODY-1', $html );
		$this->assertStringContainsString( 'BODY-3', $html );
		$this->assertStringNotContainsString( '[nextpage', $html );
		$this->assertStringNotContainsString( 'mpp-toc', $html );
		$this->assertStringNotContainsString( 'mpp-post-navigation', $html );
	}

	/**
	 * Feeds: the post content holds no shortcode and no TOC.
	 */
	public function test_feed_content() {
		$post_id = $this->create_post( self::CLASSIC );

		$this->reset_multipage();
		$this->go_to( home_url( '/?feed=rss2' ) );
		$this->assertTrue( is_feed() );
		$this->assertFalse( pchap()->is_multipage_view() );

		$query = new WP_Query( array( 'p' => $post_id ) );
		$query->the_post();
		$html = apply_filters( 'the_content', get_the_content() );
		wp_reset_postdata();

		$this->assertStringNotContainsString( '[nextpage', $html );
		$this->assertStringNotContainsString( 'mpp-toc', $html );
		$this->assertStringContainsString( 'BODY-1', $html );
	}

	/**
	 * Block content with the saved shortcode, rendered outside a multipage view.
	 */
	public function test_block_content_outside_multipage_view() {
		$html = apply_filters( 'the_content', PChap_Test_Corpus::items()['blocks 1.7']['content'] );

		$this->assertStringContainsString( 'BODY-2', $html );
		$this->assertStringNotContainsString( '[nextpage', $html );
		$this->assertStringNotContainsString( 'title="', $html );
	}

	/**
	 * Excerpts of multipage posts on archives hold no shortcode text.
	 */
	public function test_archive_excerpt() {
		$post_id = $this->create_post( "[nextpage title=\"Un\"]\n\nBODY-1" );

		$this->reset_multipage();
		$this->go_to( home_url( '/' ) );
		$query = new WP_Query( array( 'p' => $post_id ) );
		$query->the_post();
		$excerpt = get_the_excerpt();
		wp_reset_postdata();

		$this->assertStringContainsString( 'BODY-1', $excerpt );
		$this->assertStringNotContainsString( 'nextpage', $excerpt );
		$this->assertStringNotContainsString( 'Un', $excerpt );
	}
}
