<?php
/**
 * Tests of PChap_Parser.
 *
 * @package Paginova
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Test classes use the required Test_PChap_ prefix and tests apply core and third-party hooks.

/**
 * PChap_Parser::parse(), multipage_return_array() and build_paginated_content().
 *
 * @group multipage
 * @group parser
 */
class Test_PChap_Parser extends PChap_UnitTestCase {

	/**
	 * Titles of the parsed pages.
	 *
	 * @param array $parsed Result of PChap_Parser::parse().
	 * @return string[]
	 */
	private function titles( $parsed ) {
		return array_column( $parsed['pages'], 'title' );
	}

	/**
	 * Slugs of the parsed pages.
	 *
	 * @param array $parsed Result of PChap_Parser::parse().
	 * @return string[]
	 */
	private function slugs( $parsed ) {
		return array_column( $parsed['pages'], 'slug' );
	}

	/**
	 * Kinds of the parsed pages.
	 *
	 * @param array $parsed Result of PChap_Parser::parse().
	 * @return string[]
	 */
	private function kinds( $parsed ) {
		return array_column( $parsed['pages'], 'kind' );
	}

	/**
	 * Data provider: the shared corpus.
	 *
	 * @return array
	 */
	public function data_corpus() {
		$data = array();
		foreach ( PChap_Test_Corpus::items() as $name => $item ) {
			$data[ $name ] = array( $item['content'], $item['titles'], $item['kinds'] );
		}
		return $data;
	}

	/**
	 * Every corpus item gives the expected pages, each with its own body only.
	 *
	 * @dataProvider data_corpus
	 *
	 * @param string   $content Post content.
	 * @param string[] $titles  Expected display titles.
	 * @param string[] $kinds   Expected kinds.
	 */
	public function test_corpus_pages( $content, $titles, $kinds ) {
		$parsed = PChap_Parser::parse( $content );

		$this->assertTrue( $parsed['is_multipage'] );
		$this->assertSame( $kinds, $this->kinds( $parsed ), 'Page kinds.' );
		$this->assertCount( count( $titles ), $parsed['pages'] );
		$this->assertSame( 'intro' === $kinds[0], $parsed['has_intro'] );

		$count = count( $parsed['pages'] );
		foreach ( $parsed['pages'] as $index => $page ) {
			$number = $index + 1;
			$this->assertStringContainsString( 'BODY-' . $number, $page['content'], "Page {$number} body." );
			for ( $other = 1; $other <= $count; $other++ ) {
				if ( $other !== $number ) {
					$this->assertStringNotContainsString( 'BODY-' . $other, $page['content'], "Page {$number} must not contain the body of page {$other}." );
				}
			}
			$this->assertStringNotContainsString( '[nextpage', $page['content'], "Page {$number} still holds a marker." );
			$this->assertStringNotContainsString( '<!--nextpage-->', $page['content'] );
			$this->assertStringNotContainsString( 'wp:multipage/subpage', $page['content'] );
			$this->assertStringNotContainsString( 'wp:nextpage', $page['content'] );
		}

		$slugs = $this->slugs( $parsed );
		$this->assertSame( $slugs, array_unique( $slugs ), 'Slugs must be unique.' );
		foreach ( $slugs as $slug ) {
			$this->assertNotSame( '', $slug );
		}
	}

	/**
	 * Splitting the joined pages the way WordPress does returns exactly the parsed pages.
	 *
	 * @dataProvider data_corpus
	 *
	 * @param string $content Post content.
	 */
	public function test_build_paginated_content_round_trips_through_wordpress_split( $content ) {
		$parsed = PChap_Parser::parse( $content );
		$built  = PChap_Parser::build_paginated_content( $parsed['pages'] );

		$post = new WP_Post(
			(object) array(
				'ID'           => 0,
				'post_content' => $built,
				'post_type'    => 'post',
				'post_status'  => 'publish',
				'filter'       => 'raw',
			)
		);

		$query    = new WP_Query();
		$postdata = $query->generate_postdata( $post );

		$this->assertSame( count( $parsed['pages'] ), $postdata['numpages'] );
		$this->assertSame( array_column( $parsed['pages'], 'content' ), $postdata['pages'] );
	}

	/**
	 * Blocks with JSON attributes: title, slug and kind.
	 */
	public function test_subpage_blocks_with_attributes() {
		$content = PChap_Test_Corpus::paragraph( 'Intro' ) . "\n\n" . PChap_Test_Corpus::block( 'First' ) . "\n\n" . PChap_Test_Corpus::paragraph( 'One' ) . "\n\n<!-- wp:multipage/subpage {\"title\":\"Second\",\"slug\":\"my-custom-slug\"} -->\n[nextpage title=\"Second\"]\n<!-- /wp:multipage/subpage -->\n\n" . PChap_Test_Corpus::paragraph( 'Two' );

		$parsed = PChap_Parser::parse( $content );

		$this->assertSame( array( '%%intro%%', 'First', 'Second' ), $this->titles( $parsed ) );
		$this->assertSame( array( 'intro', 'first', 'my-custom-slug' ), $this->slugs( $parsed ) );
		$this->assertSame( array( 'intro', 'block', 'block' ), $this->kinds( $parsed ) );
	}

	/**
	 * The exact 1.6 serialization: the shortcode saved inside the block is never counted twice.
	 */
	public function test_legacy_block_serialization_is_counted_once() {
		$content = "<!-- wp:paragraph -->\n<p>Intro</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:multipage/subpage {\"title\":\"Deux\"} -->\n[nextpage title=\"Deux\"]\n<!-- /wp:multipage/subpage -->\n\n<!-- wp:paragraph -->\n<p>Texte deux</p>\n<!-- /wp:paragraph -->";

		$parsed = PChap_Parser::parse( $content );

		$this->assertCount( 2, $parsed['pages'] );
		$this->assertSame(
			array(
				'intro' => '%%intro%%',
				'deux'  => 'Deux',
			),
			PChap_Parser::multipage_return_array( $content )
		);
		$this->assertStringContainsString( 'Texte deux', $parsed['pages'][1]['content'] );
		$this->assertStringNotContainsString( '[nextpage', $parsed['pages'][1]['content'] );
	}

	/**
	 * An attribute-less block (default title false) saved [nextpage title="false"]: the title is empty, not "false".
	 */
	public function test_legacy_default_false_title_is_untitled() {
		$content = PChap_Test_Corpus::paragraph( 'Intro' ) . "\n\n<!-- wp:multipage/subpage -->\n[nextpage title=\"false\"]\n<!-- /wp:multipage/subpage -->\n\n" . PChap_Test_Corpus::paragraph( 'Two' );

		$parsed = PChap_Parser::parse( $content );

		$this->assertSame( array( '%%intro%%', '' ), $this->titles( $parsed ) );
		$this->assertSame( array( 'intro', 'page-2' ), $this->slugs( $parsed ) );
		$this->assertSame( 'block', $parsed['pages'][1]['kind'] );
	}

	/**
	 * A block with "title": false in its JSON is untitled too.
	 */
	public function test_block_with_false_title_attribute_is_untitled() {
		$content = "<!-- wp:multipage/subpage {\"title\":false} /-->\n" . PChap_Test_Corpus::paragraph( 'One' );

		$parsed = PChap_Parser::parse( $content );

		$this->assertTrue( $parsed['is_multipage'] );
		$this->assertSame( array( '' ), $this->titles( $parsed ) );
		$this->assertSame( array( 'page-1' ), $this->slugs( $parsed ) );
	}

	/**
	 * Self-closing subpage blocks.
	 */
	public function test_self_closing_blocks() {
		$content = "<!-- wp:multipage/subpage {\"title\":\"Un\"} /-->\n" . PChap_Test_Corpus::paragraph( 'One' ) . "\n<!-- wp:multipage/subpage {\"title\":\"Deux\"} /-->\n" . PChap_Test_Corpus::paragraph( 'Two' );

		$parsed = PChap_Parser::parse( $content );

		$this->assertFalse( $parsed['has_intro'] );
		$this->assertSame( array( 'Un', 'Deux' ), $this->titles( $parsed ) );
	}

	/**
	 * Intro detection.
	 *
	 * @dataProvider data_intro_detection
	 *
	 * @param string $before          Content before the first marker.
	 * @param bool   $expected_intro  Whether an intro page is expected.
	 */
	public function test_intro_detection( $before, $expected_intro ) {
		$content = $before . "\n\n" . PChap_Test_Corpus::block( 'Un' ) . "\n\n" . PChap_Test_Corpus::paragraph( 'One' );

		$parsed = PChap_Parser::parse( $content );

		$this->assertTrue( $parsed['is_multipage'] );
		$this->assertSame( $expected_intro, $parsed['has_intro'] );
		$this->assertCount( $expected_intro ? 2 : 1, $parsed['pages'] );
	}

	/**
	 * Data provider for test_intro_detection().
	 *
	 * @return array
	 */
	public function data_intro_detection() {
		return array(
			'text'              => array( PChap_Test_Corpus::paragraph( 'Some intro text' ), true ),
			'plain text'        => array( 'Hello', true ),
			'image only'        => array( "<!-- wp:image -->\n<figure class=\"wp-block-image\"><img src=\"https://example.org/a.jpg\" alt=\"\"/></figure>\n<!-- /wp:image -->", true ),
			'embed iframe'      => array( '<iframe src="https://example.org/embed"></iframe>', true ),
			'dynamic block'     => array( '<!-- wp:latest-posts /-->', true ),
			'empty paragraph'   => array( "<!-- wp:paragraph -->\n<p></p>\n<!-- /wp:paragraph -->", false ),
			'nbsp paragraph'    => array( '<p>&nbsp;</p>', false ),
			'whitespace only'   => array( " \n\t\r\n ", false ),
			'nothing'           => array( '', false ),
			'html comment only' => array( '<!-- a comment -->', false ),
			'br only'           => array( '<br />', false ),
		);
	}

	/**
	 * A script or style before the first marker is not an intro, but is kept on page 1.
	 *
	 * @dataProvider data_non_meaningful_kept
	 *
	 * @param string $before Content before the first marker.
	 * @param string $needle What must be kept on page 1.
	 */
	public function test_script_and_style_before_first_marker_are_kept_on_page_one( $before, $needle ) {
		$content = $before . "\n[nextpage title=\"Un\"]\n\nOne\n\n[nextpage title=\"Deux\"]\n\nTwo";

		$parsed = PChap_Parser::parse( $content );

		$this->assertFalse( $parsed['has_intro'] );
		$this->assertSame( array( 'Un', 'Deux' ), $this->titles( $parsed ) );
		$this->assertStringContainsString( $needle, $parsed['pages'][0]['content'] );
		$this->assertStringContainsString( 'One', $parsed['pages'][0]['content'] );
		$this->assertStringNotContainsString( $needle, $parsed['pages'][1]['content'] );
	}

	/**
	 * Data provider for test_script_and_style_before_first_marker_are_kept_on_page_one().
	 *
	 * @return array
	 */
	public function data_non_meaningful_kept() {
		return array(
			'script'    => array( '<script>window.a = 1;</script>', 'window.a = 1;' ),
			'style'     => array( '<style>.x{color:red}</style>', '.x{color:red}' ),
			'separator' => array( "<!-- wp:separator -->\n<hr class=\"wp-block-separator\"/>\n<!-- /wp:separator -->", 'wp-block-separator' ),
		);
	}

	/**
	 * Untitled shortcodes get an empty title and a page-N slug.
	 */
	public function test_untitled_shortcodes() {
		$parsed = PChap_Parser::parse( "Intro\n\n[nextpage]\n\nTwo\n\n[nextpage title=\"\"]\n\nThree\n\n[nextpage title=\"Four\"]\n\nFour" );

		$this->assertSame( array( '%%intro%%', '', '', 'Four' ), $this->titles( $parsed ) );
		$this->assertSame( array( 'intro', 'page-2', 'page-3', 'four' ), $this->slugs( $parsed ) );
		$this->assertSame( array( 'intro', 'shortcode', 'shortcode', 'shortcode' ), $this->kinds( $parsed ) );
	}

	/**
	 * Mixed subpage blocks, shortcodes and core page breaks, in document order.
	 */
	public function test_mixed_markers_in_document_order() {
		$content = "Intro\n\n[nextpage title=\"A\"]\n\nBody A\n\n<!--nextpage-->\n\nBody core\n\n<!-- wp:multipage/subpage {\"title\":\"C\"} /-->\n\nBody C\n\n[nextpage title=\"D\"]\n\nBody D";

		$parsed = PChap_Parser::parse( $content );

		$this->assertSame( array( '%%intro%%', 'A', '', 'C', 'D' ), $this->titles( $parsed ) );
		$this->assertSame( array( 'intro', 'shortcode', 'core', 'block', 'shortcode' ), $this->kinds( $parsed ) );
		$this->assertStringContainsString( 'Body core', $parsed['pages'][2]['content'] );
		$this->assertStringContainsString( 'Body C', $parsed['pages'][3]['content'] );
	}

	/**
	 * Posts with core page breaks only keep the pure core behavior.
	 *
	 * @dataProvider data_not_multipage
	 *
	 * @param string $content Post content.
	 */
	public function test_not_multipage( $content ) {
		$parsed = PChap_Parser::parse( $content );

		$this->assertFalse( $parsed['is_multipage'] );
		$this->assertSame( array(), $parsed['pages'] );
		$this->assertFalse( PChap_Parser::multipage_return_array( $content ) );
	}

	/**
	 * Data provider for test_not_multipage().
	 *
	 * @return array
	 */
	public function data_not_multipage() {
		return array(
			'empty'                         => array( '' ),
			'plain text'                    => array( 'Hello world' ),
			'core page breaks only'         => array( "One\n<!--nextpage-->\nTwo\n<!--nextpage-->\nThree" ),
			'core nextpage blocks only'     => array( PChap_Test_Corpus::paragraph( 'One' ) . "\n<!-- wp:nextpage -->\n<!--nextpage-->\n<!-- /wp:nextpage -->\n" . PChap_Test_Corpus::paragraph( 'Two' ) ),
			'escaped shortcode'             => array( "Intro\n\n[[nextpage title=\"Deux\"]]\n\nText" ),
			'shortcode in HTML comment'     => array( "Intro\n<!-- [nextpage title=\"Deux\"] -->\nText" ),
			'shortcode in attribute'        => array( "<p><span data-x=\"[nextpage title='Deux']\">Intro</span> text</p>" ),
			'shortcode in title attribute'  => array( '<p><a href="#" title="[nextpage]">link</a></p>' ),
			'shortcode in script'           => array( "<script>var s = '[nextpage title=\"Deux\"]';</script><p>Text</p>" ),
			'shortcode in textarea'         => array( '<textarea>[nextpage title="Deux"]</textarea>' ),
			'other shortcode'               => array( 'Intro [nextpages title="x"] text [nextpage-foo]' ),
			'non-subpage block named alike' => array( '<!-- wp:multipage/other {"title":"x"} /-->' ),
		);
	}

	/**
	 * Escaped and commented shortcodes do not split, real ones still do.
	 */
	public function test_escaped_and_commented_markers_are_ignored_next_to_real_ones() {
		$content = "Intro [[nextpage title=\"Escaped\"]]\n<!-- [nextpage title=\"Commented\"] -->\n<span title=\"[nextpage title=Attr]\">x</span>\n\n[nextpage title=\"Real\"]\n\nBody";

		$parsed = PChap_Parser::parse( $content );

		$this->assertSame( array( '%%intro%%', 'Real' ), $this->titles( $parsed ) );
		$this->assertStringContainsString( '[[nextpage title="Escaped"]]', $parsed['pages'][0]['content'] );
	}

	/**
	 * Duplicate titles give unique slugs, in order.
	 */
	public function test_duplicate_titles_get_unique_slugs() {
		$content = "[nextpage title=\"Same\"]\n\nA\n\n[nextpage title=\"Same\"]\n\nB\n\n<!-- wp:multipage/subpage {\"title\":\"Same\"} /-->\n\nC\n\n[nextpage title=\"Other\" slug=\"same\"]\n\nD";

		$parsed = PChap_Parser::parse( $content );

		$this->assertSame( array( 'same', 'same-1', 'same-2', 'same-3' ), $this->slugs( $parsed ) );

		$array = PChap_Parser::multipage_return_array( $content );
		$this->assertSame( array( 'same', 'same-1', 'same-2', 'same-3' ), array_keys( $array ) );
		$this->assertSame( array( 'Same', 'Same', 'Same', 'Other' ), array_values( $array ) );
	}

	/**
	 * A title which is also "intro" does not collide with the intro slug.
	 */
	public function test_title_intro_does_not_collide_with_intro_page() {
		$parsed = PChap_Parser::parse( "Hello\n\n[nextpage title=\"Intro\"]\n\nBody" );

		$this->assertSame( array( 'intro', 'intro-1' ), $this->slugs( $parsed ) );
	}

	/**
	 * The slug attribute wins over the title, and is sanitized.
	 */
	public function test_slug_attribute() {
		$parsed = PChap_Parser::parse( "[nextpage title=\"Deux\" slug=\"Custom Slug!\"]\n\nA\n\n<!-- wp:multipage/subpage {\"title\":\"Trois\",\"slug\":\"block-slug\"} /-->\n\nB" );

		$this->assertSame( array( 'custom-slug', 'block-slug' ), $this->slugs( $parsed ) );
		$this->assertSame( array( 'Deux', 'Trois' ), $this->titles( $parsed ) );
	}

	/**
	 * CRLF line endings.
	 */
	public function test_crlf_line_endings() {
		$content = "<p>Intro</p>\r\n<p>[nextpage title=\"Deux\"]</p>\r\n<p>Two</p>\r\n" . str_replace( "\n", "\r\n", PChap_Test_Corpus::block( 'Trois' ) ) . "\r\n<p>Three</p>";

		$parsed = PChap_Parser::parse( $content );

		$this->assertSame( array( '%%intro%%', 'Deux', 'Trois' ), $this->titles( $parsed ) );
		$this->assertStringContainsString( 'Three', $parsed['pages'][2]['content'] );
		$this->assertStringNotContainsString( '[nextpage', $parsed['pages'][2]['content'] );
	}

	/**
	 * Unicode and emoji titles are kept as is; slugs are not empty.
	 */
	public function test_unicode_and_emoji_titles() {
		$content = "Intro\n\n[nextpage title=\"Éléphant 🐘\"]\n\nA\n\n" . PChap_Test_Corpus::block( 'Ünïcödé — 日本' ) . "\n\nB\n\n[nextpage title=\"🎉\"]\n\nC";

		$parsed = PChap_Parser::parse( $content );

		$this->assertSame( array( '%%intro%%', 'Éléphant 🐘', 'Ünïcödé — 日本', '🎉' ), $this->titles( $parsed ) );
		$slugs = $this->slugs( $parsed );
		$this->assertSame( $slugs, array_unique( $slugs ) );
		$this->assertSame( sanitize_title( 'Éléphant 🐘' ), $slugs[1] );
	}

	/**
	 * Titles with quotes, ampersands and HTML, as saved by both editors.
	 */
	public function test_titles_with_special_characters() {
		$content = PChap_Test_Corpus::block( 'Tom &amp; Jerry' ) . "\n\nA\n\n" . PChap_Test_Corpus::block( 'Say "hi"' ) . "\n\nB\n\n[nextpage title='He said \"yes\"']\n\nC\n\n[nextpage title=\"a < b & c\"]\n\nD";

		$parsed = PChap_Parser::parse( $content );

		$this->assertSame( array( 'Tom &amp; Jerry', 'Say "hi"', 'He said "yes"', 'a < b & c' ), $this->titles( $parsed ) );
	}

	/**
	 * A marker inside a group block closes and reopens the group: every page is self-contained.
	 */
	public function test_marker_inside_group_block_keeps_pages_balanced() {
		$item   = PChap_Test_Corpus::items()['blocks inside a group'];
		$parsed = PChap_Parser::parse( $item['content'] );

		foreach ( $parsed['pages'] as $page ) {
			$this->assertSame( substr_count( $page['content'], '<!-- wp:group' ), substr_count( $page['content'], '<!-- /wp:group -->' ) );
			$this->assertTagBalanced( 'div', $page['content'] );
			$blocks = parse_blocks( $page['content'] );
			$this->assertNotEmpty( $blocks );
		}
	}

	/**
	 * Inline markers inside an element close and reopen it (the real-world h3 case).
	 */
	public function test_marker_inside_heading_leaves_balanced_pages() {
		$item   = PChap_Test_Corpus::items()['classic shortcodes inside h3 style'];
		$parsed = PChap_Parser::parse( $item['content'] );

		$this->assertCount( 3, $parsed['pages'] );
		foreach ( $parsed['pages'] as $page ) {
			$this->assertTagBalanced( 'h3', $page['content'] );
			$this->assertTagBalanced( 'p', $page['content'] );
		}
	}

	/**
	 * Method multipage_return_array() is the slug => title format of the 1.6 `_mpp_data` meta.
	 */
	public function test_multipage_return_array_format() {
		$content = "Intro\n\n[nextpage title=\"Deux\"]\n\nA\n\n[nextpage]\n\nB";

		$this->assertSame(
			array(
				'intro'  => '%%intro%%',
				'deux'   => 'Deux',
				'page-3' => '',
			),
			PChap_Parser::multipage_return_array( $content )
		);
	}

	/**
	 * Non-string input never errors.
	 */
	public function test_non_string_input() {
		foreach ( array( null, false, 0, array() ) as $input ) {
			$parsed = PChap_Parser::parse( $input );
			$this->assertFalse( $parsed['is_multipage'] );
		}
	}

	/**
	 * Method build_paginated_content(): block break for block content, bare break for classic content.
	 */
	public function test_build_paginated_content_separators() {
		$this->assertSame( 'a<!--nextpage-->b', PChap_Parser::build_paginated_content( array( 'a', 'b' ) ) );
		$this->assertSame( '<!-- wp:paragraph --><p>a</p><!-- /wp:paragraph -->' . PChap_Parser::BLOCK_PAGE_BREAK . 'b', PChap_Parser::build_paginated_content( array( '<!-- wp:paragraph --><p>a</p><!-- /wp:paragraph -->', 'b' ) ) );
		$this->assertSame( 'a<!--nextpage-->b', PChap_Parser::build_paginated_content( array( array( 'content' => 'a' ), array( 'content' => 'b' ) ), false ) );
		$this->assertSame( 'a' . PChap_Parser::BLOCK_PAGE_BREAK . 'b', PChap_Parser::build_paginated_content( array( 'a', 'b' ), true ) );
	}

	/**
	 * A titled subpage without body is kept (placeholder), so the page count does not change.
	 */
	public function test_titled_empty_subpage_is_kept() {
		$parsed = PChap_Parser::parse( "Intro\n\n[nextpage title=\"Empty\"]\n\n[nextpage title=\"Full\"]\n\nBody" );

		$this->assertSame( array( '%%intro%%', 'Empty', 'Full' ), $this->titles( $parsed ) );
		$this->assertNotSame( '', $parsed['pages'][1]['content'] );
	}

	/**
	 * Two adjacent untitled breaks do not create an empty page.
	 */
	public function test_adjacent_untitled_breaks_do_not_create_empty_pages() {
		$parsed = PChap_Parser::parse( "Intro\n\n[nextpage]\n\n[nextpage]\n\nBody" );

		$this->assertCount( 2, $parsed['pages'] );
		$this->assertStringContainsString( 'Body', $parsed['pages'][1]['content'] );
	}
}
