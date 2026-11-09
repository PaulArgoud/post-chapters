<?php
/**
 * Splitting HTML at subpage markers: every page is balanced HTML, without the empty
 * elements a cut would leave, and the table of contents / navigation never end up
 * inside a heading, a paragraph or a link.
 *
 * @package Post Chapters
 */

/**
 * Split HTML tests.
 */
class Test_PChap_Split_Html extends WP_UnitTestCase {

	/**
	 * Post Chapters methods hooked once at boot (kept when the request state is reset).
	 *
	 * @var string[]
	 */
	private static $boot_methods = array( 'save_post', 'pchap_pre_handle_404', 'pchap_redirect_canonical', 'pchap_post' );

	/**
	 * Void HTML elements (no end tag).
	 *
	 * @var string[]
	 */
	private static $void_elements = array( 'area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'keygen', 'link', 'meta', 'param', 'source', 'track', 'wbr' );

	/**
	 * Elements whose content must not reach the TOC / navigation containers.
	 *
	 * @var string[]
	 */
	private static $forbidden_ancestors = array( 'H1', 'H2', 'H3', 'H4', 'H5', 'H6', 'P', 'A' );

	/**
	 * Reset the plugin state before each test.
	 */
	public function set_up() {
		parent::set_up();
		$this->reset_multipage();
		if ( function_exists( 'pchap_clear_options_cache' ) ) {
			pchap_clear_options_cache();
		}
	}

	/**
	 * Reset the plugin state after each test.
	 */
	public function tear_down() {
		$this->reset_multipage();
		parent::tear_down();
		if ( function_exists( 'pchap_clear_options_cache' ) ) {
			pchap_clear_options_cache();
		}
	}

	/**
	 * Split cases: content, expected raw titles, expected page contents.
	 *
	 * @return array
	 */
	public static function data_split_cases() {
		$heading_block = "<!-- wp:heading {\"level\":3} -->\n<h3 class=\"wp-block-heading\">[nextpage title=\"X\"]</h3>\n<!-- /wp:heading -->";

		return array(
			// The user's production cases.
			'p containing only the marker'                 => array(
				"Intro.\n\n<p>[nextpage title=\"Deux\"]</p>\n\nTexte deux.",
				array( '%%intro%%', 'Deux' ),
				array( 'Intro.', 'Texte deux.' ),
			),
			'h3 with style containing only the marker'     => array(
				"Intro text.\n\n<h3 style=\"color:red\">[nextpage title=\"Deux\"]</h3>\n\nTexte deux.",
				array( '%%intro%%', 'Deux' ),
				array( 'Intro text.', 'Texte deux.' ),
			),
			'h2 containing only the marker'                => array(
				"<p>Intro</p>\n<h2>[nextpage title=\"Deux\"]</h2>\n<p>Texte deux.</p>",
				array( '%%intro%%', 'Deux' ),
				array( '<p>Intro</p>', '<p>Texte deux.</p>' ),
			),
			'div containing only the marker'               => array(
				"Intro text.\n\n<div class=\"x\">\n[nextpage title=\"Deux\"]\n</div>\n\nTexte deux.",
				array( '%%intro%%', 'Deux' ),
				array( 'Intro text.', 'Texte deux.' ),
			),
			'h3 containing the marker then text'           => array(
				"Intro.\n\n<h3 style=\"text-align: center;\">[nextpage title=\"Deux\"] Sous-titre</h3>\n\nTexte.",
				array( '%%intro%%', 'Deux' ),
				array( 'Intro.', "<h3 style=\"text-align: center;\">Sous-titre</h3>\n\nTexte." ),
			),
			'already clean content'                        => array(
				"Intro.\n\n[nextpage title=\"Un\"]\n\nTexte un.\n\n[nextpage title=\"Deux\"]\n\nTexte deux.",
				array( '%%intro%%', 'Un', 'Deux' ),
				array( 'Intro.', 'Texte un.', 'Texte deux.' ),
			),

			// More markup around the marker.
			'marker in the middle of a paragraph'          => array(
				'<p>Avant [nextpage title="Deux"] après</p>',
				array( '%%intro%%', 'Deux' ),
				array( '<p>Avant</p>', '<p>après</p>' ),
			),
			'marker inside strong, with text'              => array(
				'<p>Un <strong>gras [nextpage title="Deux"] suite</strong> fin</p>',
				array( '%%intro%%', 'Deux' ),
				array( '<p>Un <strong>gras</strong></p>', '<p><strong>suite</strong> fin</p>' ),
			),
			'marker alone inside strong'                   => array(
				'<p>Un</p><p><strong>[nextpage title="Deux"]</strong></p><p>Deux</p>',
				array( '%%intro%%', 'Deux' ),
				array( '<p>Un</p>', '<p>Deux</p>' ),
			),
			'nested div and p, with siblings'              => array(
				"<div class=\"a\"><p>A</p>\n<div class=\"b\"><p>[nextpage title=\"Deux\"]</p></div>\n<p>B</p></div>",
				array( '%%intro%%', 'Deux' ),
				array( '<div class="a"><p>A</p></div>', '<div class="a"><p>B</p></div>' ),
			),
			'nested wrappers holding only the marker'      => array(
				"Intro\n<div class=\"a\"><div class=\"b\"><p>[nextpage title=\"Deux\"]</p></div></div>\nTexte",
				array( '%%intro%%', 'Deux' ),
				array( 'Intro', 'Texte' ),
			),
			'core/heading block holding only the marker'   => array(
				"<!-- wp:paragraph -->\n<p>Intro</p>\n<!-- /wp:paragraph -->\n\n" . $heading_block . "\n\n<!-- wp:paragraph -->\n<p>Deux</p>\n<!-- /wp:paragraph -->",
				array( '%%intro%%', 'X' ),
				array( "<!-- wp:paragraph -->\n<p>Intro</p>\n<!-- /wp:paragraph -->", "<!-- wp:paragraph -->\n<p>Deux</p>\n<!-- /wp:paragraph -->" ),
			),
			'core/heading block inside a group block'      => array(
				"<!-- wp:paragraph --><p>Intro</p><!-- /wp:paragraph -->\n\n<!-- wp:group -->\n<div class=\"wp-block-group\"><!-- wp:paragraph --><p>A</p><!-- /wp:paragraph -->\n\n" . $heading_block . "\n\n<!-- wp:paragraph --><p>B</p><!-- /wp:paragraph --></div>\n<!-- /wp:group -->",
				array( '%%intro%%', 'X' ),
				array(
					"<!-- wp:paragraph --><p>Intro</p><!-- /wp:paragraph -->\n\n<!-- wp:group -->\n<div class=\"wp-block-group\"><!-- wp:paragraph --><p>A</p><!-- /wp:paragraph --></div><!-- /wp:group -->",
					"<!-- wp:group --><div class=\"wp-block-group\"><!-- wp:paragraph --><p>B</p><!-- /wp:paragraph --></div>\n<!-- /wp:group -->",
				),
			),
			'group block holding only the heading block'   => array(
				"<!-- wp:paragraph --><p>Intro</p><!-- /wp:paragraph -->\n\n<!-- wp:group {\"layout\":{\"type\":\"constrained\"}} -->\n<div class=\"wp-block-group\">" . $heading_block . "</div>\n<!-- /wp:group -->\n\n<!-- wp:paragraph --><p>Deux</p><!-- /wp:paragraph -->",
				array( '%%intro%%', 'X' ),
				array( '<!-- wp:paragraph --><p>Intro</p><!-- /wp:paragraph -->', '<!-- wp:paragraph --><p>Deux</p><!-- /wp:paragraph -->' ),
			),
			'core/paragraph block holding only the marker' => array(
				"<!-- wp:paragraph --><p>Intro</p><!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<p>[nextpage title=\"Deux\"]</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph --><p>Deux</p><!-- /wp:paragraph -->",
				array( '%%intro%%', 'Deux' ),
				array( '<!-- wp:paragraph --><p>Intro</p><!-- /wp:paragraph -->', '<!-- wp:paragraph --><p>Deux</p><!-- /wp:paragraph -->' ),
			),
			'core/paragraph block, marker mid-text'        => array(
				"<!-- wp:paragraph -->\n<p>Avant [nextpage title=\"Deux\"] après</p>\n<!-- /wp:paragraph -->",
				array( '%%intro%%', 'Deux' ),
				array( "<!-- wp:paragraph -->\n<p>Avant</p><!-- /wp:paragraph -->", "<!-- wp:paragraph --><p>après</p>\n<!-- /wp:paragraph -->" ),
			),
			'core/paragraph block, marker then text'       => array(
				"<!-- wp:paragraph --><p>Intro</p><!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<p>[nextpage title=\"Deux\"] Texte deux.</p>\n<!-- /wp:paragraph -->",
				array( '%%intro%%', 'Deux' ),
				array( '<!-- wp:paragraph --><p>Intro</p><!-- /wp:paragraph -->', "<!-- wp:paragraph --><p>Texte deux.</p>\n<!-- /wp:paragraph -->" ),
			),
			'list item holding only the marker'            => array(
				'<ul><li>Un</li><li>[nextpage title="Deux"]</li><li>Deux</li></ul>',
				array( '%%intro%%', 'Deux' ),
				array( '<ul><li>Un</li></ul>', '<ul><li>Deux</li></ul>' ),
			),
			'list item, marker mid-text'                   => array(
				'<ul><li>Un [nextpage title="Deux"] deux</li></ul>',
				array( '%%intro%%', 'Deux' ),
				array( '<ul><li>Un</li></ul>', '<ul><li>deux</li></ul>' ),
			),
			'blockquote holding only the marker'           => array(
				'<p>Intro</p><blockquote>[nextpage title="Deux"]</blockquote><p>Texte</p>',
				array( '%%intro%%', 'Deux' ),
				array( '<p>Intro</p>', '<p>Texte</p>' ),
			),
			'blockquote, marker mid-paragraph'             => array(
				'<blockquote><p>Citation [nextpage title="Deux"] suite</p></blockquote>',
				array( '%%intro%%', 'Deux' ),
				array( '<blockquote><p>Citation</p></blockquote>', '<blockquote><p>suite</p></blockquote>' ),
			),
			'CRLF line endings'                            => array(
				"Intro.\r\n\r\n<h3 style=\"color:red\">\r\n[nextpage title=\"Deux\"]\r\n</h3>\r\n\r\nTexte deux.\r\n\r\n<p>Avant\r\n[nextpage title=\"Trois\"]\r\naprès</p>",
				array( '%%intro%%', 'Deux', 'Trois' ),
				array( 'Intro.', "Texte deux.\r\n\r\n<p>Avant</p>", '<p>après</p>' ),
			),
			'attributes with quotes and ">"'               => array(
				"<p>Intro</p>\n<h3 data-x='a\"b' title=\"c > d\">[nextpage title='L’été \"chaud\"']</h3>\n<p>Texte</p>",
				array( '%%intro%%', 'L’été "chaud"' ),
				array( '<p>Intro</p>', '<p>Texte</p>' ),
			),
			'quoted style attribute, marker then text'     => array(
				"<p>Intro</p>\n<h3 style=\"font-family: 'Open Sans', sans-serif;\">[nextpage title=\"Deux\"] Titre <em>deux</em></h3>\n<p>Texte</p>",
				array( '%%intro%%', 'Deux' ),
				array( '<p>Intro</p>', "<h3 style=\"font-family: 'Open Sans', sans-serif;\">Titre <em>deux</em></h3>\n<p>Texte</p>" ),
			),
			// Invisible fillers typed next to the marker in the visual editor.
			'h3 holding the marker between nbsp'           => array(
				"Intro.\n<h3 style=\"font-size:24px;font-weight:700\">&nbsp;[nextpage title=\"Deux\"]&nbsp;</h3>\nTexte deux.",
				array( '%%intro%%', 'Deux' ),
				array( 'Intro.', 'Texte deux.' ),
			),
			'h3 holding the marker and a line break'       => array(
				"Intro.\n<h3 style=\"font-size:24px\">[nextpage title=\"Deux\"]<br /></h3>\nTexte deux.",
				array( '%%intro%%', 'Deux' ),
				array( 'Intro.', 'Texte deux.' ),
			),
			'p holding the marker and nbsp'                => array(
				"<p>Intro.</p>\n<p>[nextpage title=\"Deux\"]&nbsp;</p>\n<p>Texte deux.</p>",
				array( '%%intro%%', 'Deux' ),
				array( '<p>Intro.</p>', '<p>Texte deux.</p>' ),
			),
			'h3 holding text and the marker then nbsp'     => array(
				"<p>Intro.</p>\n<h3>Sous-titre [nextpage title=\"Deux\"]&#160;</h3>\n<p>Texte deux.</p>",
				array( '%%intro%%', 'Deux' ),
				array( "<p>Intro.</p>\n<h3>Sous-titre</h3>", '<p>Texte deux.</p>' ),
			),
			'two markers in the same wrapper'              => array(
				'<p>Intro</p><div class="x">[nextpage title="Un"][nextpage title="Deux"] Texte deux.</div>',
				array( '%%intro%%', 'Un', 'Deux' ),
				array( '<p>Intro</p>', PChap_Parser::EMPTY_PAGE_PLACEHOLDER, '<div class="x">Texte deux.</div>' ),
			),
		);
	}

	/**
	 * Every page is balanced, without empty elements left by the cut, with the right title.
	 *
	 * @dataProvider data_split_cases
	 *
	 * @param string   $content  Post content.
	 * @param string[] $titles   Expected raw titles.
	 * @param string[] $contents Expected page contents.
	 */
	public function test_split_pages( $content, $titles, $contents ) {
		$parsed = PChap_Parser::parse( $content );

		$this->assertTrue( $parsed['is_multipage'] );
		$this->assertSame( $titles, array_column( $parsed['pages'], 'title' ), 'Titles' );
		$this->assertSame( $contents, array_column( $parsed['pages'], 'content' ), 'Page contents' );

		foreach ( $parsed['pages'] as $index => $page ) {
			$label = sprintf( 'Page %d (%s)', $index + 1, $page['title'] );
			$this->assert_balanced_html( $page['content'], $label );
			$this->assertSame( array(), self::find_empty_elements( $page['content'] ), $label . ': empty element left by the split.' );
		}

		// The pages survive WordPress' own splitting unchanged.
		$joined = PChap_Parser::build_paginated_content( $parsed['pages'] );
		$this->assertCount( count( $titles ), explode( '<!--nextpage-->', $joined ) );
	}

	/**
	 * Elements that mean something when empty are never dropped, and stay balanced.
	 */
	public function test_meaningful_empty_elements_are_kept() {
		$parsed = PChap_Parser::parse( '<p>Intro</p><table><tbody><tr><td>[nextpage title="Deux"]</td></tr></tbody></table><p>Texte</p>' );

		$this->assertSame(
			array( '<p>Intro</p><table><tbody><tr><td></td></tr></tbody></table>', '<table><tbody><tr><td></td></tr></tbody></table><p>Texte</p>' ),
			array_column( $parsed['pages'], 'content' )
		);

		foreach ( $parsed['pages'] as $page ) {
			$this->assert_balanced_html( $page['content'], $page['title'] );
		}
	}

	/**
	 * Whitespace is kept inside preformatted text.
	 */
	public function test_whitespace_is_kept_in_pre() {
		$parsed = PChap_Parser::parse( "<p>Intro</p><pre>a = 1; [nextpage title=\"Deux\"]\n  b = 2;</pre>" );

		$this->assertSame( array( '<p>Intro</p><pre>a = 1; </pre>', "<pre>\n  b = 2;</pre>" ), array_column( $parsed['pages'], 'content' ) );
	}

	/**
	 * The descendant check used below detects the 1.6.0 symptom (sanity check of the helper).
	 */
	public function test_ancestor_helper_detects_nesting_in_a_heading() {
		$html = '<h3 style="color:red">Texte <div class="mpp-toc-container top-right"><nav class="mpp-toc toc"><ul><li>x</li></ul></nav></div>';

		$ancestors = self::get_ancestors_by_class( $html, 'mpp-toc-container' );

		$this->assertCount( 1, $ancestors );
		$this->assertContains( 'H3', $ancestors[0] );
	}

	/**
	 * Classic content (wpautop) through a real request on every subpage.
	 */
	public function test_full_pipeline_classic_post() {
		$content = implode(
			"\n\n",
			array(
				'Intro du "chapitre".',
				'<h3 style="text-align: center;">[nextpage title="Chapitre 2"]</h3>',
				'Texte du chapitre deux.',
				'<p>Avant [nextpage title="Chapitre 3"] Texte trois.</p>',
				"<div class=\"encadre\">\n[nextpage title=\"Chapitre 4\"]\n</div>\nTexte quatre.",
				'<h2>[nextpage title="Chapitre 5"]</h2>',
				"Texte cinq,\nsur deux lignes.",
				'<h3 style="color: #c00;">[nextpage title="Chapitre 6"] Sous-titre six</h3>',
				'Texte six.',
			)
		);

		$this->assert_full_pipeline(
			$content,
			array(
				1 => array( 'Intro', 'Intro du' ),
				2 => array( 'Chapitre 2', 'Texte du chapitre deux.' ),
				3 => array( 'Chapitre 3', 'Texte trois.' ),
				4 => array( 'Chapitre 4', 'Texte quatre.' ),
				5 => array( 'Chapitre 5', 'sur deux lignes.' ),
				6 => array( 'Chapitre 6', 'Sous-titre six' ),
			)
		);
	}

	/**
	 * Block content through a real request on every subpage.
	 */
	public function test_full_pipeline_block_post() {
		$content = implode(
			"\n\n",
			array(
				"<!-- wp:paragraph -->\n<p>Intro du bloc.</p>\n<!-- /wp:paragraph -->",
				"<!-- wp:heading {\"level\":3} -->\n<h3 class=\"wp-block-heading\">[nextpage title=\"Deux\"]</h3>\n<!-- /wp:heading -->",
				"<!-- wp:paragraph -->\n<p>Texte deux.</p>\n<!-- /wp:paragraph -->",
				"<!-- wp:paragraph -->\n<p>Avant [nextpage title=\"Trois\"] Texte trois.</p>\n<!-- /wp:paragraph -->",
				"<!-- wp:multipage/subpage {\"title\":\"Quatre\"} -->\n[nextpage title=\"Quatre\"]\n<!-- /wp:multipage/subpage -->",
				"<!-- wp:group {\"layout\":{\"type\":\"constrained\"}} -->\n<div class=\"wp-block-group\"><!-- wp:paragraph -->\n<p>Texte quatre.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:heading {\"level\":2} -->\n<h2 class=\"wp-block-heading\">[nextpage title=\"Cinq\"]</h2>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph -->\n<p>Texte cinq.</p>\n<!-- /wp:paragraph --></div>\n<!-- /wp:group -->",
			)
		);

		$this->assert_full_pipeline(
			$content,
			array(
				1 => array( 'Intro', 'Intro du bloc.' ),
				2 => array( 'Deux', 'Texte deux.' ),
				3 => array( 'Trois', 'Texte trois.' ),
				4 => array( 'Quatre', 'Texte quatre.' ),
				5 => array( 'Cinq', 'Texte cinq.' ),
			)
		);
	}

	/**
	 * A titled subpage without body renders its title, TOC and navigation, without the placeholder.
	 */
	public function test_empty_subpage_placeholder_is_not_printed() {
		$content = "Intro.\n\n<div class=\"x\">[nextpage title=\"Vide\"][nextpage title=\"Plein\"] Texte plein.</div>";

		$this->assert_full_pipeline(
			$content,
			array(
				1 => array( 'Intro', 'Intro.' ),
				2 => array( 'Vide', '' ),
				3 => array( 'Plein', 'Texte plein.' ),
			)
		);
	}

	/**
	 * Render every subpage of a post and check the output.
	 *
	 * @param string $content  Post content.
	 * @param array  $expected Page number => array( title text, a text of the page body ).
	 */
	private function assert_full_pipeline( $content, $expected ) {
		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id ); // Keep the markup as written (unfiltered_html).

		$post_id = self::factory()->post->create(
			array(
				'post_content' => $content,
				'post_status'  => 'publish',
			)
		);

		wp_set_current_user( 0 );

		$bodies = array();
		foreach ( $expected as $expect ) {
			if ( '' !== $expect[1] ) {
				$bodies[] = $expect[1];
			}
		}

		foreach ( $expected as $number => $expect ) {
			$output = $this->render_subpage( $post_id, $number );
			$label  = sprintf( 'Subpage %d', $number );

			$this->assertSame( count( $expected ), pchap()->max_num_pages, $label . ': number of subpages.' );
			$this->assertSame( $number, pchap()->get_current_page(), $label . ': current page.' );

			$this->assert_balanced_html( $output, $label );
			$this->assertSame( array(), self::find_empty_elements( $output ), $label . ': empty element in ' . $output );
			$this->assertStringNotContainsString( '<!--', $output, $label . ': HTML comment printed.' );

			// Title of the subpage, and its own body only.
			$this->assertStringContainsString( '<h2>' . $expect[0] . '</h2>', $output, $label . ': page title.' );
			foreach ( $bodies as $body ) {
				if ( $body === $expect[1] ) {
					$this->assertStringContainsString( $body, $output, $label . ': page body.' );
				} else {
					$this->assertStringNotContainsString( $body, $output, $label . ': body of another page.' );
				}
			}

			// The navigation links to the next subpage (plain permalinks: ?page=N, like core).
			if ( $number < count( $expected ) ) {
				$next_url = add_query_arg( 'page', $number + 1, get_permalink( $post_id ) );
				$this->assertStringContainsString( '<a href="' . esc_url( $next_url ) . '" rel="next">', $output, $label . ': link to the next subpage.' );
			}

			// The TOC and the navigation are never nested in a heading, a paragraph or a link.
			foreach ( array( 'mpp-toc-container', 'mpp-post-navigation' ) as $class ) {
				$ancestors = self::get_ancestors_by_class( $output, $class );
				$this->assertCount( 1, $ancestors, $label . ': one .' . $class . ' in ' . $output );
				$this->assertSame( array(), array_values( array_intersect( $ancestors[0], self::$forbidden_ancestors ) ), $label . ': .' . $class . ' nested in ' . implode( ' > ', $ancestors[0] ) );
			}
		}
	}

	/**
	 * Simulate a front-end request of a subpage and render the main loop content.
	 *
	 * Like a real request: WP::main() runs the query, pre_handle_404 and the "wp" action
	 * (PChap::pchap_post()); then the loop applies the_content.
	 *
	 * @param int $post_id Post ID.
	 * @param int $number  Subpage number (1-based).
	 * @return string
	 */
	private function render_subpage( $post_id, $number ) {
		$this->reset_multipage();

		$url = get_permalink( $post_id );
		if ( $number > 1 ) {
			$url = add_query_arg( 'page', $number, $url );
		}

		$this->go_to( $url );

		$this->assertTrue( is_singular(), 'Singular view expected for ' . $url );
		$this->assertFalse( is_404(), 'Unexpected 404 for ' . $url );
		$this->assertTrue( pchap()->is_multipage_view(), 'Post Chapters view expected for ' . $url );

		$output = '';
		while ( have_posts() ) {
			the_post();
			$output .= apply_filters( 'the_content', get_the_content() );
		}

		return $output;
	}

	/**
	 * Reset the per-request state of the Post Chapters singleton and remove the request hooks.
	 *
	 * The singleton keeps the state of one request (one PHP process per request in
	 * production); the tests run many requests in one process.
	 */
	private function reset_multipage() {
		global $wp_filter;

		$mpp = pchap();
		$ref = new ReflectionObject( $mpp );

		$private = array(
			'setup_key'          => null,
			'is_multipage'       => false,
			'is_valid_page'      => false,
			'hooks_registered'   => false,
			'rendering'          => false,
			'rendered_hashes'    => array(),
			'post_content_depth' => 0,
		);

		foreach ( $private as $name => $value ) {
			if ( ! $ref->hasProperty( $name ) ) {
				continue;
			}
			$property = $ref->getProperty( $name );
			if ( PHP_VERSION_ID < 80100 ) {
				$property->setAccessible( true );
			}
			$property->setValue( $mpp, $value );
		}

		if ( $ref->hasMethod( 'reset_data' ) ) {
			$method = $ref->getMethod( 'reset_data' );
			if ( PHP_VERSION_ID < 80100 ) {
				$method->setAccessible( true );
			}
			$method->invoke( $mpp );
		}

		$to_remove = array();
		foreach ( (array) $wp_filter as $hook_name => $hook ) {
			if ( ! $hook instanceof WP_Hook ) {
				continue;
			}
			foreach ( $hook->callbacks as $priority => $callbacks ) {
				foreach ( $callbacks as $callback ) {
					$function = $callback['function'];
					if ( is_array( $function ) && isset( $function[0], $function[1] ) && $function[0] === $mpp && ! in_array( $function[1], self::$boot_methods, true ) ) {
						$to_remove[] = array( $hook_name, $function, $priority );
					}
				}
			}
		}

		foreach ( $to_remove as $item ) {
			remove_filter( $item[0], $item[1], $item[2] );
		}
	}

	/**
	 * Assert that HTML is balanced: every start tag is closed, in order, and no end tag is stray.
	 *
	 * Void elements and self-closing tags are ignored. Block delimiters are checked the
	 * same way (opener / closer / self-closing).
	 *
	 * @param string $html  HTML.
	 * @param string $label Assertion label.
	 */
	private function assert_balanced_html( $html, $label ) {
		$this->assertSame( array(), self::get_balance_errors( $html ), $label . ': unbalanced HTML: ' . $html );
	}

	/**
	 * Balance errors of an HTML fragment (see assert_balanced_html()).
	 *
	 * @param string $html HTML.
	 * @return string[]
	 */
	private static function get_balance_errors( $html ) {
		$errors = array();
		$tags   = array();
		$blocks = array();

		preg_match_all( '/<!--.*?-->|<(\/?)([a-zA-Z][a-zA-Z0-9:-]*)((?:[^>"\']++|"[^"]*+"|\'[^\']*+\')*+)>/s', $html, $matches, PREG_SET_ORDER );

		foreach ( $matches as $match ) {
			if ( 0 === strpos( $match[0], '<!--' ) ) {
				if ( preg_match( '/^<!--\s+(\/)?wp:([a-z][a-z0-9_-]*\/)?([a-z][a-z0-9_-]*)\s+(?:\{.*\}\s+)?(\/)?-->$/s', $match[0], $block ) ) {
					$name = ( '' !== $block[2] ? $block[2] : 'core/' ) . $block[3];
					if ( isset( $block[4] ) && '/' === $block[4] ) {
						continue;
					}
					if ( '/' === $block[1] ) {
						$top = array_pop( $blocks );
						if ( $top !== $name ) {
							$errors[] = 'stray block closer ' . $name . ( null === $top ? '' : ' (open: ' . $top . ')' );
						}
					} else {
						$blocks[] = $name;
					}
				}
				continue;
			}

			$name = strtolower( $match[2] );

			if ( '/' === $match[1] ) {
				$top = array_pop( $tags );
				if ( $top !== $name ) {
					$errors[] = 'stray </' . $name . '>' . ( null === $top ? '' : ' (open: <' . $top . '>)' );
					if ( null !== $top ) {
						$tags[] = $top;
					}
				}
				continue;
			}

			if ( in_array( $name, self::$void_elements, true ) || '/' === substr( rtrim( $match[3] ), -1 ) ) {
				continue;
			}

			$tags[] = $name;
		}

		foreach ( $tags as $name ) {
			$errors[] = 'unclosed <' . $name . '>';
		}
		foreach ( $blocks as $name ) {
			$errors[] = 'unclosed block ' . $name;
		}

		return $errors;
	}

	/**
	 * Empty elements and empty blocks of an HTML fragment.
	 *
	 * An element is empty when it only holds whitespace, `&nbsp;` or `<br>`; elements that mean something
	 * when empty (table cells, media...) are not reported.
	 *
	 * @param string $html HTML.
	 * @return string[]
	 */
	private static function find_empty_elements( $html ) {
		$found = array();

		if ( preg_match_all( '/<([a-zA-Z][a-zA-Z0-9-]*)(?:\s(?:[^>"\']++|"[^"]*+"|\'[^\']*+\')*+)?>(?:\s|&nbsp;|&#0*160;|<br\s*\/?>)*<\/\1\s*>/', $html, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				if ( ! in_array( strtolower( $match[1] ), PChap_Parser::KEPT_WHEN_EMPTY_ELEMENTS, true ) ) {
					$found[] = $match[0];
				}
			}
		}

		if ( preg_match_all( '/<!--\s+wp:([a-z][a-z0-9_\/-]*)\s+(?:\{.*?\}\s+)?-->\s*<!--\s+\/wp:\1\s+-->/s', $html, $matches ) ) {
			$found = array_merge( $found, $matches[0] );
		}

		return $found;
	}

	/**
	 * Ancestors (upper-case tag names, outermost first) of each element with a class.
	 *
	 * Uses the HTML5 parser of WordPress (WP_HTML_Processor), which nests elements as
	 * browsers do (a <div> start tag does not close an open <h3>); falls back to
	 * DOMDocument when the processor does not support the markup.
	 *
	 * @param string $html       HTML fragment.
	 * @param string $class_name Class name.
	 * @return array[] One list of ancestors per matching element.
	 */
	private static function get_ancestors_by_class( $html, $class_name ) {
		$found = array();

		if ( class_exists( 'WP_HTML_Processor' ) ) {
			$processor = WP_HTML_Processor::create_fragment( $html );
			if ( null !== $processor ) {
				while ( $processor->next_tag() ) {
					if ( $processor->has_class( $class_name ) ) {
						$breadcrumbs = $processor->get_breadcrumbs();
						array_pop( $breadcrumbs );
						$found[] = array_values( array_diff( $breadcrumbs, array( 'HTML', 'BODY' ) ) );
					}
				}

				if ( null === $processor->get_last_error() ) {
					return $found;
				}
			}
		}

		$found    = array();
		$previous = libxml_use_internal_errors( true );
		$document = new DOMDocument();
		$document->loadHTML( '<!DOCTYPE html><html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"></head><body>' . $html . '</body></html>' );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );

		$xpath = new DOMXPath( $document );
		$nodes = $xpath->query( '//*[contains(concat(" ", normalize-space(@class), " "), " ' . $class_name . ' ")]' );

		foreach ( $nodes as $node ) {
			$ancestors = array();
			for ( $parent = $node->parentNode; $parent instanceof DOMElement; $parent = $parent->parentNode ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM API.
				$name = strtoupper( $parent->nodeName ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM API.
				if ( 'HTML' !== $name && 'BODY' !== $name ) {
					array_unshift( $ancestors, $name );
				}
			}
			$found[] = $ancestors;
		}

		return $found;
	}
}
