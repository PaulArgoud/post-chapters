<?php
/**
 * Subpage title escaping tests.
 *
 * @package Post Chapters
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Test classes use the required Test_PChap_ prefix and tests apply core and third-party hooks.

/**
 * Title helpers and the escaping of titles in the rendered output and the document title.
 *
 * @group multipage
 * @group titles
 */
class Test_PChap_Titles extends PChap_UnitTestCase {

	/**
	 * Function pchap_get_subpage_title_html().
	 *
	 * @dataProvider data_title_html
	 *
	 * @param string $title    Title.
	 * @param string $format   Format.
	 * @param string $expected Expected HTML.
	 */
	public function test_mpp_get_subpage_title_html( $title, $format, $expected ) {
		$this->assertSame( $expected, pchap_get_subpage_title_html( $title, $format ) );
	}

	/**
	 * Data provider for test_mpp_get_subpage_title_html().
	 *
	 * @return array
	 */
	public function data_title_html() {
		return array(
			'html entity kept'           => array( 'Tom &amp; Jerry', 'html', 'Tom &amp; Jerry' ),
			'html inline tag kept'       => array( '<strong>b</strong> and <em>i</em>', 'html', '<strong>b</strong> and <em>i</em>' ),
			'html script stripped'       => array( '<script>alert(1)</script>Evil', 'html', 'alert(1)Evil' ),
			'html unclosed tag balanced' => array( '<strong>bold', 'html', '<strong>bold</strong>' ),
			'html attribute stripped'    => array( '<span onclick="alert(1)">x</span>', 'html', '<span>x</span>' ),
			'text raw lt and amp'        => array( 'a < b & c', 'text', 'a &lt; b &amp; c' ),
			'text existing entity kept'  => array( 'Tom &amp; Jerry', 'text', 'Tom &amp; Jerry' ),
			'text allowed inline tag'    => array( '<strong>b</strong>', 'text', '<strong>b</strong>' ),
			'text generic type'          => array( 'C++ <T>', 'text', 'C++ &lt;T&gt;' ),
			'text quotes'                => array( 'He said "yes"', 'text', 'He said "yes"' ),
			'empty'                      => array( '', 'html', '' ),
		);
	}

	/**
	 * Function pchap_get_subpage_title_text().
	 *
	 * @dataProvider data_title_text
	 *
	 * @param string $title    Title.
	 * @param string $format   Format.
	 * @param string $expected Expected text.
	 */
	public function test_mpp_get_subpage_title_text( $title, $format, $expected ) {
		$this->assertSame( $expected, pchap_get_subpage_title_text( $title, $format ) );
	}

	/**
	 * Data provider for test_mpp_get_subpage_title_text().
	 *
	 * @return array
	 */
	public function data_title_text() {
		return array(
			'html entity decoded'   => array( 'Tom &amp; Jerry', 'html', 'Tom & Jerry' ),
			'html tags stripped'    => array( '<strong>b</strong>', 'html', 'b' ),
			'html encoded tag text' => array( '&lt;b&gt;', 'html', '<b>' ),
			'text raw'              => array( 'a < b & c', 'text', 'a < b & c' ),
			'text tags stripped'    => array( '<em>x</em> y', 'text', 'x y' ),
			'whitespace collapsed'  => array( "a \n\t b", 'html', 'a b' ),
		);
	}

	/**
	 * Non-scalar titles give an empty string.
	 */
	public function test_non_scalar_titles() {
		$this->assertSame( '', pchap_get_subpage_title_html( array( 'x' ) ) );
		$this->assertSame( '', pchap_get_subpage_title_text( null ) );
	}

	/**
	 * Inner HTML of the page title heading.
	 *
	 * @param string $html Rendered content.
	 * @return string
	 */
	private function heading( $html ) {
		$this->assertSame( 1, preg_match( '/^<h2>(.*?)<\/h2>/s', $html, $match ), "No page title heading at the start of: {$html}" );
		return $match[1];
	}

	/**
	 * Gutenberg "Tom &amp; Jerry" is not double encoded (heading, TOC, navigation, document title).
	 */
	public function test_block_title_with_entity_is_not_double_encoded() {
		$post_id = $this->create_post( PChap_Test_Corpus::paragraph( 'Intro' ) . "\n\n" . PChap_Test_Corpus::block( 'Tom &amp; Jerry' ) . "\n\n" . PChap_Test_Corpus::paragraph( 'Two' ) );

		$html = $this->render_subpage( $post_id, 1 );
		$this->assertStringContainsString( '>Tom &amp; Jerry</a>', $html, 'Continue link and TOC.' );
		$this->assertStringNotContainsString( '&amp;amp;', $html );

		$html = $this->render_subpage( $post_id, 2 );
		$this->assertSame( 'Tom &amp; Jerry', $this->heading( $html ) );
		$this->assertStringNotContainsString( '&amp;amp;', $html );

		$title = wp_get_document_title();
		$this->assertStringNotContainsString( '&amp;amp;', $title );
		$this->assertStringContainsString( 'Tom & Jerry', html_entity_decode( $title, ENT_QUOTES, 'UTF-8' ) );
	}

	/**
	 * Legacy titles with inline HTML: the tag is rendered, and stripped from the document title.
	 */
	public function test_legacy_inline_html_title() {
		$post_id = $this->create_post( PChap_Test_Corpus::paragraph( 'Intro' ) . "\n\n" . PChap_Test_Corpus::block( '<strong>b</strong> title' ) . "\n\n" . PChap_Test_Corpus::paragraph( 'Two' ) );

		$html = $this->render_subpage( $post_id, 2 );
		$this->assertSame( '<strong>b</strong> title', $this->heading( $html ) );

		$title = wp_get_document_title();
		$this->assertStringContainsString( 'b title', $title );
		$this->assertStringNotContainsString( '<strong>', $title );
		$this->assertStringNotContainsString( '&lt;strong', $title );
	}

	/**
	 * Classic raw title "a < b & c" is shown as text.
	 */
	public function test_classic_raw_title_is_escaped_as_text() {
		$post_id = $this->create_post( "Intro\n\n[nextpage title=\"a < b & c\"]\n\nTwo" );

		$html = $this->render_subpage( $post_id, 2 );
		$this->assertSame( 'a &lt; b &amp; c', $this->heading( $html ) );
		$this->assertStringContainsString( '<span class="current" aria-current="page">a &lt; b &amp; c</span>', $html );

		$title = wp_get_document_title();
		$this->assertStringContainsString( 'a < b & c', html_entity_decode( $title, ENT_QUOTES, 'UTF-8' ) );
		$this->assertStringNotContainsString( '<', $title );
	}

	/**
	 * Classic titles with characters core keeps in shortcode attributes ("&", ">", "<T>").
	 */
	public function test_classic_titles_with_special_characters() {
		$post_id = $this->create_post( "Intro\n\n[nextpage title=\"Fish & Chips > all\"]\n\nTwo\n\n[nextpage title=\"C++ <T>\"]\n\nThree" );

		$html = $this->render_subpage( $post_id, 2 );
		$this->assertSame( 'Fish &amp; Chips &gt; all', $this->heading( $html ) );
		$this->assertStringContainsString( 'Fish & Chips > all', html_entity_decode( wp_get_document_title(), ENT_QUOTES, 'UTF-8' ) );

		$html = $this->render_subpage( $post_id, 3 );
		$this->assertSame( 'C++ &lt;T&gt;', $this->heading( $html ) );
		$this->assertStringContainsString( '>Fish &amp; Chips &gt; all</a>', $html, 'TOC row of page 2.' );
	}

	/**
	 * Quotes in titles (classic and block).
	 */
	public function test_titles_with_quotes() {
		$post_id = $this->create_post( "Intro\n\n[nextpage title='He said \"yes\"']\n\nTwo\n\n" . PChap_Test_Corpus::block( 'L\'été "chaud"' ) . "\n\nThree" );

		$html = $this->render_subpage( $post_id, 2 );
		$this->assertSame( 'He said "yes"', html_entity_decode( $this->heading( $html ), ENT_QUOTES, 'UTF-8' ) );

		$html = $this->render_subpage( $post_id, 3 );
		$this->assertSame( 'L\'été "chaud"', html_entity_decode( $this->heading( $html ), ENT_QUOTES, 'UTF-8' ) );
		$this->assertStringContainsString( 'chaud', wp_get_document_title() );
	}

	/**
	 * Data provider: XSS payloads.
	 *
	 * @return array
	 */
	public function data_xss_payloads() {
		return array(
			'script'           => array( '<script>alert(1)</script>Evil' ),
			'img onerror'      => array( '<img src=x onerror=alert(1)>Evil' ),
			'javascript url'   => array( '<a href="javascript:alert(1)">Evil</a>' ),
			'event on allowed' => array( '<span onclick="alert(1)" style="x">Evil</span>' ),
			'svg onload'       => array( '<svg onload=alert(1)>Evil' ),
			'iframe'           => array( '<iframe src="javascript:alert(1)"></iframe>Evil' ),
			'broken attribute' => array( '"><script>alert(1)</script>Evil' ),
			'encoded script'   => array( '&lt;script&gt;alert(1)&lt;/script&gt;Evil' ),
		);
	}

	/**
	 * Assert that rendered HTML holds no active payload.
	 *
	 * @param string $html HTML.
	 */
	private function assert_no_active_payload( $html ) {
		$this->assertDoesNotMatchRegularExpression( '/<script/i', $html );
		$this->assertDoesNotMatchRegularExpression( '/<img[^>]*onerror/i', $html );
		$this->assertDoesNotMatchRegularExpression( '/<(?:img|svg|iframe)\b/i', $html );
		$this->assertDoesNotMatchRegularExpression( '/<[^>]+\son\w+\s*=/i', $html, 'Event handler attribute.' );
		$this->assertDoesNotMatchRegularExpression( '/<[^>]*\s(?:href|src)\s*=\s*["\']?\s*javascript:/i', $html, 'javascript: URL in an attribute.' );
	}

	/**
	 * XSS payloads in block titles are never output (heading, TOC, navigation, head).
	 *
	 * @dataProvider data_xss_payloads
	 *
	 * @param string $payload Title.
	 */
	public function test_xss_in_block_title( $payload ) {
		$post_id = $this->create_post( PChap_Test_Corpus::paragraph( 'Intro' ) . "\n\n" . PChap_Test_Corpus::block( $payload ) . "\n\n" . PChap_Test_Corpus::paragraph( 'Two' ) . "\n\n" . PChap_Test_Corpus::block( 'Three' ) . "\n\n" . PChap_Test_Corpus::paragraph( 'Three' ) );

		foreach ( array( 1, 2, 3 ) as $page ) {
			$html = $this->render_subpage( $post_id, $page );
			$this->assert_no_active_payload( $html );
			$this->assertStringContainsString( 'Evil', $html );
		}

		$this->go_to_subpage( $post_id, 2 );
		$this->assert_no_active_payload( wp_get_document_title() );
		$this->assert_no_active_payload( (string) get_echo( array( pchap(), 'pchap_rel_links' ) ) );
	}

	/**
	 * XSS payloads in classic shortcode titles are never output as markup.
	 *
	 * @dataProvider data_xss_payloads
	 *
	 * @param string $payload Title.
	 */
	public function test_xss_in_shortcode_title( $payload ) {
		$post_id = $this->create_post( "Intro\n\n[nextpage title='" . str_replace( "'", '&#039;', $payload ) . "']\n\nTwo\n\n[nextpage title=\"Three\"]\n\nThree" );

		foreach ( array( 1, 2, 3 ) as $page ) {
			$html = $this->render_subpage( $post_id, $page );
			$this->assert_no_active_payload( $html );
		}

		$this->go_to_subpage( $post_id, 2 );
		$this->assert_no_active_payload( wp_get_document_title() );
	}
}
