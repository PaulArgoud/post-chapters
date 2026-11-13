<?php
/**
 * Rendering tests: page title, table of contents, navigation and settings.
 *
 * @package Paginova
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Test classes use the required Test_PChap_ prefix and tests apply core and third-party hooks.

/**
 * The content of a multipage post, rendered through the_content on real requests.
 *
 * @group multipage
 * @group rendering
 */
class Test_PChap_Rendering extends PChap_UnitTestCase {

	/**
	 * Three-page classic post: Intro, Deux, Trois.
	 *
	 * @var string
	 */
	const CLASSIC = "Intro BODY-1\n\n[nextpage title=\"Deux\"]\n\nBODY-2\n\n[nextpage title=\"Trois\"]\n\nBODY-3";

	/**
	 * Expected href attribute of a subpage link.
	 *
	 * The table of contents and the navigation go through wp_kses_post(), which
	 * writes the &#038; of esc_url() as &amp;.
	 *
	 * @param int    $post_id  Post ID.
	 * @param int    $page     Subpage.
	 * @param string $fragment Optional. Fragment.
	 * @return string
	 */
	private function href( $post_id, $page, $fragment = '' ) {
		return 'href="' . str_replace( '&#038;', '&amp;', esc_url( pchap_link_page_url( $page, $fragment, $post_id ) ) ) . '"';
	}

	/**
	 * Extract the table of contents HTML.
	 *
	 * @param string $html Rendered content.
	 * @return string
	 */
	private function toc_html( $html ) {
		$start = strpos( $html, 'mpp-toc-container' );
		$this->assertNotFalse( $start, 'No table of contents.' );
		$end = strpos( $html, '</ul>', $start );
		return substr( $html, $start, $end - $start );
	}

	/**
	 * Default settings, first page: title, TOC (top-right, numbers, header) then content, then "Continue".
	 */
	public function test_default_rendering_first_page() {
		$post_id = $this->create_post( self::CLASSIC );
		$html    = $this->render_subpage( $post_id, 1 );

		$this->assertSame( 1, substr_count( $html, '<h2>Intro</h2>' ), 'Page title once.' );
		$this->assertSame( 1, substr_count( $html, 'mpp-toc-container' ), 'TOC once.' );
		$this->assertStringContainsString( 'mpp-toc-container top-right', $html );
		$this->assertStringContainsString( 'mpp-toc-title', $html );
		$this->assertStringContainsString( 'Contents', $html );
		$this->assertStringContainsString( 'aria-label="Table of contents"', $html );
		$this->assertStringContainsString( '<li>1. <span class="current" aria-current="page">Intro</span></li>', $html );
		$this->assertStringContainsString( '<li>2. <a ' . $this->href( $post_id, 2 ) . '>Deux</a></li>', $html );
		$this->assertStringContainsString( '<li>3. <a ' . $this->href( $post_id, 3 ) . '>Trois</a></li>', $html );

		// Order: title, TOC, content, navigation.
		$title = strpos( $html, '<h2>Intro</h2>' );
		$toc   = strpos( $html, 'mpp-toc-container' );
		$body  = strpos( $html, 'BODY-1' );
		$nav   = strpos( $html, 'mpp-post-navigation' );
		$this->assertTrue( $title < $toc && $toc < $body && $body < $nav, 'Order: title, TOC, content, navigation.' );

		// "Continue" navigation to the next subpage.
		$this->assertStringContainsString( 'aria-label="Chapter navigation"', $html );
		$this->assertStringContainsString( 'mpp-post-navigation continue', $html );
		$this->assertStringContainsString( 'Continue: <a ' . $this->href( $post_id, 2 ) . ' rel="next">Deux</a>', $html );
	}

	/**
	 * Middle and last pages: current item, "Continue" then "Back to".
	 */
	public function test_default_rendering_middle_and_last_pages() {
		$post_id = $this->create_post( self::CLASSIC );

		$html = $this->render_subpage( $post_id, 2 );
		$this->assertSame( 1, substr_count( $html, '<h2>Deux</h2>' ) );
		$this->assertStringContainsString( '<span class="current" aria-current="page">Deux</span>', $html );
		$this->assertStringContainsString( '<a ' . $this->href( $post_id, 1 ) . '>Intro</a>', $html );
		$this->assertStringContainsString( 'Continue: <a ' . $this->href( $post_id, 3 ) . ' rel="next">Trois</a>', $html );

		$html = $this->render_subpage( $post_id, 3 );
		$this->assertSame( 1, substr_count( $html, '<h2>Trois</h2>' ) );
		$this->assertStringContainsString( '<span class="current" aria-current="page">Trois</span>', $html );
		$this->assertStringContainsString( 'Back to: <a ' . $this->href( $post_id, 1 ) . '>Intro</a>', $html );
		$this->assertStringNotContainsString( 'Continue:', $html );
	}

	/**
	 * "next-previous" navigation.
	 */
	public function test_next_previous_navigation() {
		$this->set_options( array( 'mpp-continue-or-prev-next' => 'next-previous' ) );
		$post_id = $this->create_post( self::CLASSIC );

		$html = $this->render_subpage( $post_id, 1 );
		$this->assertStringContainsString( 'mpp-post-navigation next-previous', $html );
		$this->assertStringContainsString( '<a ' . $this->href( $post_id, 2 ) . ' rel="next">Next page</a>', $html );
		$this->assertStringNotContainsString( 'rel="previous"', $html );
		$this->assertStringNotContainsString( 'Continue:', $html );

		$html = $this->render_subpage( $post_id, 2 );
		$this->assertStringContainsString( '<a ' . $this->href( $post_id, 1 ) . ' rel="previous">Previous page</a>', $html );
		$this->assertStringContainsString( '<a ' . $this->href( $post_id, 3 ) . ' rel="next">Next page</a>', $html );

		$html = $this->render_subpage( $post_id, 3 );
		$this->assertStringContainsString( '<a ' . $this->href( $post_id, 2 ) . ' rel="previous">Previous page</a>', $html );
		$this->assertStringNotContainsString( 'rel="next"', $html );
	}

	/**
	 * Hidden navigation.
	 */
	public function test_hidden_navigation() {
		$this->set_options( array( 'mpp-continue-or-prev-next' => 'hidden' ) );
		$post_id = $this->create_post( self::CLASSIC );

		foreach ( array( 1, 2, 3 ) as $page ) {
			$html = $this->render_subpage( $post_id, $page );
			$this->assertStringNotContainsString( 'mpp-post-navigation', $html );
			$this->assertStringNotContainsString( 'Continue:', $html );
			$this->assertStringNotContainsString( 'Back to:', $html );
			$this->assertStringContainsString( 'mpp-toc-container', $html, 'The TOC is still displayed.' );
		}
	}

	/**
	 * TOC positions.
	 *
	 * @dataProvider data_toc_positions
	 *
	 * @param string $position TOC position.
	 */
	public function test_toc_position_above_content( $position ) {
		$this->set_options( array( 'mpp-toc-position' => $position ) );
		$post_id = $this->create_post( self::CLASSIC );

		$html = $this->render_subpage( $post_id, 2 );

		$this->assertStringContainsString( 'mpp-toc-container ' . $position . '"', $html );
		$this->assertLessThan( strpos( $html, 'BODY-2' ), strpos( $html, 'mpp-toc-container' ) );
		$this->assertSame( 1, substr_count( $html, 'mpp-toc-container' ) );
	}

	/**
	 * Data provider for test_toc_position_above_content().
	 *
	 * @return array
	 */
	public function data_toc_positions() {
		return array(
			'top-left'  => array( 'top-left' ),
			'top-right' => array( 'top-right' ),
			'top'       => array( 'top' ),
		);
	}

	/**
	 * TOC at the bottom: after the content, before the navigation.
	 */
	public function test_toc_position_bottom() {
		$this->set_options( array( 'mpp-toc-position' => 'bottom' ) );
		$post_id = $this->create_post( self::CLASSIC );

		$html = $this->render_subpage( $post_id, 2 );
		$toc  = strpos( $html, 'mpp-toc-container bottom' );

		$this->assertNotFalse( $toc );
		$this->assertGreaterThan( strpos( $html, 'BODY-2' ), $toc );
		$this->assertLessThan( strpos( $html, 'mpp-post-navigation' ), $toc );
		$this->assertLessThan( strpos( $html, 'BODY-2' ), strpos( $html, '<h2>Deux</h2>' ) );
	}

	/**
	 * Hidden TOC.
	 */
	public function test_toc_position_hidden() {
		$this->set_options( array( 'mpp-toc-position' => 'hidden' ) );
		$post_id = $this->create_post( self::CLASSIC );

		$html = $this->render_subpage( $post_id, 1 );

		$this->assertStringNotContainsString( 'mpp-toc', $html );
		$this->assertStringContainsString( '<h2>Intro</h2>', $html );
		$this->assertStringContainsString( 'BODY-1', $html );
		$this->assertStringContainsString( 'mpp-post-navigation', $html );
	}

	/**
	 * TOC only on the first page.
	 */
	public function test_toc_only_on_the_first_page() {
		$this->set_options( array( 'mpp-toc-only-on-the-first-page' => 1 ) );
		$post_id = $this->create_post( self::CLASSIC );

		$this->assertStringContainsString( 'mpp-toc-container', $this->render_subpage( $post_id, 1 ) );

		$html = $this->render_subpage( $post_id, 2 );
		$this->assertStringNotContainsString( 'mpp-toc-container', $html );
		$this->assertStringContainsString( '<h2>Deux</h2>', $html );
		$this->assertStringContainsString( 'mpp-post-navigation', $html );
	}

	/**
	 * TOC row labels.
	 */
	public function test_toc_row_labels() {
		$post_id = $this->create_post( self::CLASSIC );

		$this->set_options( array( 'mpp-toc-row-labels' => 'number' ) );
		$toc = $this->toc_html( $this->render_subpage( $post_id, 1 ) );
		$this->assertStringContainsString( '<li>1. <span', $toc );
		$this->assertStringContainsString( '<li>2. <a', $toc );

		$this->set_options( array( 'mpp-toc-row-labels' => 'page' ) );
		$toc = $this->toc_html( $this->render_subpage( $post_id, 1 ) );
		$this->assertStringContainsString( '<li>Chapter 1: <span', $toc );
		$this->assertStringContainsString( '<li>Chapter 3: <a', $toc );

		$this->set_options( array( 'mpp-toc-row-labels' => 'hidden' ) );
		$toc = $this->toc_html( $this->render_subpage( $post_id, 1 ) );
		$this->assertStringContainsString( '<li><span class="current"', $toc );
		$this->assertStringContainsString( '<li><a ', $toc );
		$this->assertStringNotContainsString( '1. ', $toc );
		$this->assertStringNotContainsString( 'Chapter 1', $toc );
	}

	/**
	 * Hidden TOC header.
	 */
	public function test_hide_toc_header() {
		$this->set_options( array( 'mpp-hide-toc-header' => 1 ) );
		$post_id = $this->create_post( self::CLASSIC );

		$html = $this->render_subpage( $post_id, 1 );

		$this->assertStringContainsString( 'mpp-toc-container', $html );
		$this->assertStringNotContainsString( 'mpp-toc-title', $html );
		$this->assertStringNotContainsString( '<h2>Contents</h2>', $html );
	}

	/**
	 * Hidden intro title: only on the intro page.
	 */
	public function test_hide_intro_title() {
		$this->set_options( array( 'mpp-hide-intro-title' => 1 ) );
		$post_id = $this->create_post( self::CLASSIC );

		$html = $this->render_subpage( $post_id, 1 );
		$this->assertStringNotContainsString( '<h2>Intro</h2>', $html );
		$this->assertStringContainsString( '<span class="current" aria-current="page">Intro</span>', $html, 'The TOC still lists the intro.' );

		$html = $this->render_subpage( $post_id, 2 );
		$this->assertStringContainsString( '<h2>Deux</h2>', $html );
	}

	/**
	 * Hidden intro title does not hide the title of a first page that is not an intro.
	 */
	public function test_hide_intro_title_keeps_titled_first_page() {
		$this->set_options( array( 'mpp-hide-intro-title' => 1 ) );
		$post_id = $this->create_post( "[nextpage title=\"Un\"]\n\nBODY-1\n\n[nextpage title=\"Deux\"]\n\nBODY-2" );

		$this->assertStringContainsString( '<h2>Un</h2>', $this->render_subpage( $post_id, 1 ) );
	}

	/**
	 * Comments link in the TOC, for each "comments on page" setting.
	 */
	public function test_comments_toc_link_variants() {
		$post_id = $this->create_post( self::CLASSIC );

		// Disabled by default.
		$this->assertStringNotContainsString( '#comments', $this->render_subpage( $post_id, 2 ) );

		$this->set_options(
			array(
				'mpp-comments-toc-link' => 1,
				'mpp-comments-on-page'  => 'all',
			)
		);
		$toc = $this->toc_html( $this->render_subpage( $post_id, 2 ) );
		$this->assertStringContainsString( '<li><a href="#comments">Comments</a></li>', $toc );

		$this->set_options( array( 'mpp-comments-on-page' => 'first-page' ) );
		$toc = $this->toc_html( $this->render_subpage( $post_id, 2 ) );
		$this->assertStringContainsString( '<a ' . $this->href( $post_id, 1, 'comments' ) . '>Comments</a>', $toc );

		$this->set_options( array( 'mpp-comments-on-page' => 'last-page' ) );
		$toc = $this->toc_html( $this->render_subpage( $post_id, 2 ) );
		$this->assertStringContainsString( '<a ' . $this->href( $post_id, 3, 'comments' ) . '>Comments</a>', $toc );
	}

	/**
	 * Block content: same rendering, nothing left of the subpage blocks.
	 */
	public function test_block_content_rendering() {
		$item    = PChap_Test_Corpus::items()['blocks 1.7'];
		$post_id = $this->create_post( $item['content'] );

		$html = $this->render_subpage( $post_id, 2 );

		$this->assertSame( 1, substr_count( $html, '<h2>Deux</h2>' ) );
		$this->assertMatchesRegularExpression( '#<p[^>]*>BODY-2</p>#', $html );
		$this->assertStringNotContainsString( '[nextpage', $html );
		$this->assertStringNotContainsString( 'wp-block-multipage', $html );
		$this->assertStringNotContainsString( 'post-nav-links', $html, 'The standard page links are hidden.' );
	}

	/**
	 * Block theme path: the core/post-content block renders the TOC and title once,
	 * without the standard page links.
	 */
	public function test_post_content_block_renders_once() {
		$item    = PChap_Test_Corpus::items()['blocks 1.7'];
		$post_id = $this->create_post( $item['content'] );

		$this->go_to_subpage( $post_id, 2 );
		$this->assertTrue( have_posts() );
		the_post();

		$html = do_blocks( '<!-- wp:post-title /--><!-- wp:post-content /-->' );

		$this->assertSame( 1, substr_count( $html, '<h2>Deux</h2>' ), $html );
		$this->assertSame( 1, substr_count( $html, 'mpp-toc-container' ) );
		$this->assertSame( 1, substr_count( $html, 'mpp-post-navigation' ) );
		$this->assertStringContainsString( 'BODY-2', $html );
		$this->assertStringNotContainsString( 'BODY-1', $html );
		$this->assertStringNotContainsString( 'post-nav-links', $html, 'The standard page links are hidden.' );
	}

	/**
	 * The standard page links are displayed when the setting allows them.
	 */
	public function test_standard_pagination_can_be_enabled() {
		$this->set_options( array( 'mpp-disable-standard-pagination' => 0 ) );
		$post_id = $this->create_post( self::CLASSIC );

		$this->go_to_subpage( $post_id, 2 );
		the_post();

		$links = wp_link_pages( array( 'echo' => 0 ) );
		$this->assertStringContainsString( 'post-nav-links', $links );

		$this->set_options( array( 'mpp-disable-standard-pagination' => 1 ) );
		$this->go_to_subpage( $post_id, 2 );
		the_post();

		$this->assertSame( '', wp_link_pages( array( 'echo' => 0 ) ) );
	}

	/**
	 * Another post rendered in a secondary loop of the same request is never decorated.
	 */
	public function test_secondary_loop_post_is_not_decorated() {
		$main_id  = $this->create_post( self::CLASSIC );
		$other_id = $this->create_post( "Other intro OTHER-1\n\n[nextpage title=\"Autre\"]\n\nOTHER-2", array( 'post_title' => 'Other' ) );
		$plain_id = $this->create_post( 'Plain PLAIN-1', array( 'post_title' => 'Plain' ) );

		$this->go_to_subpage( $main_id, 2 );
		$this->assertTrue( have_posts() );
		the_post();

		foreach ( array( $other_id, $plain_id ) as $id ) {
			$secondary = new WP_Query(
				array(
					'p'         => $id,
					'post_type' => 'post',
				)
			);
			$this->assertTrue( $secondary->have_posts() );
			$secondary->the_post();

			$html = (string) get_echo( 'the_content' );

			$this->assertStringNotContainsString( 'mpp-toc', $html, 'TOC injected into another post.' );
			$this->assertStringNotContainsString( 'mpp-post-navigation', $html );
			$this->assertStringNotContainsString( '<h2>Deux</h2>', $html );
			$this->assertStringNotContainsString( '[nextpage', $html );

			wp_reset_postdata();
		}

		// The main post is still decorated afterwards.
		$html = (string) get_echo( 'the_content' );
		$this->assertStringContainsString( '<h2>Deux</h2>', $html );
		$this->assertSame( 1, substr_count( $html, 'mpp-toc-container' ) );
	}

	/**
	 * Content filtered outside the loop (widgets, sidebars) is not decorated.
	 */
	public function test_the_content_outside_the_loop_is_not_decorated() {
		$post_id = $this->create_post( self::CLASSIC );
		$this->go_to_subpage( $post_id, 2 );

		$html = apply_filters( 'the_content', 'Some widget text' );

		$this->assertStringNotContainsString( 'mpp-toc', $html );
	}

	/**
	 * Excerpts never contain the TOC or the navigation.
	 */
	public function test_excerpt_is_not_decorated() {
		$post_id = $this->create_post( self::CLASSIC );
		$this->go_to_subpage( $post_id, 1 );
		$this->assertTrue( have_posts() );
		the_post();

		$excerpt = get_the_excerpt();
		$this->assertStringNotContainsString( 'Contents', $excerpt );
		$this->assertStringNotContainsString( 'Continue', $excerpt );
		$this->assertStringContainsString( 'BODY-1', $excerpt );

		$excerpt = (string) get_echo( 'the_excerpt' );
		$this->assertStringNotContainsString( 'Contents', $excerpt );
		$this->assertStringNotContainsString( 'Continue', $excerpt );
	}

	/**
	 * Function wp_trim_excerpt() called directly (themes, plugins) does not contain the TOC either.
	 */
	public function test_wp_trim_excerpt_is_not_decorated() {
		$post_id = $this->create_post( self::CLASSIC );
		$this->go_to_subpage( $post_id, 1 );
		$this->assertTrue( have_posts() );
		the_post();

		$excerpt = wp_trim_excerpt( '' );

		$this->assertStringContainsString( 'BODY-1', $excerpt );
		$this->assertStringNotContainsString( 'Contents', $excerpt );
		$this->assertStringNotContainsString( 'Continue', $excerpt );
	}

	/**
	 * Password-protected posts are not decorated.
	 */
	public function test_password_protected_post_is_not_decorated() {
		$post_id = $this->create_post( self::CLASSIC, array( 'post_password' => 'secret' ) );

		$html = $this->render_subpage( $post_id, 2 );

		$this->assertStringNotContainsString( 'mpp-toc', $html );
		$this->assertStringNotContainsString( 'BODY-2', $html );
	}

	/**
	 * The pchap_page_title_template and pchap_the_content filters.
	 */
	public function test_title_template_and_content_filters() {
		add_filter(
			'pchap_page_title_template',
			static function () {
				return '<h3 class="custom-title">%s</h3>';
			}
		);

		$seen = array();
		add_filter(
			'pchap_the_content',
			static function ( $output, $page_title, $toc, $content ) use ( &$seen ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Read by compact().
				$seen = compact( 'page_title', 'toc', 'content' );
				return $output . '<!-- custom -->';
			},
			10,
			4
		);

		$post_id = $this->create_post( self::CLASSIC );
		$html    = $this->render_subpage( $post_id, 2 );

		$this->assertStringContainsString( '<h3 class="custom-title">Deux</h3>', $html );
		$this->assertStringNotContainsString( '<h2>Deux</h2>', $html );
		$this->assertSame( '<h3 class="custom-title">Deux</h3>', $seen['page_title'] );
		$this->assertStringContainsString( 'mpp-toc-container', $seen['toc'] );
		$this->assertStringContainsString( 'BODY-2', $seen['content'] );
		$this->assertStringContainsString( '<!-- custom -->', $html );
	}

	/**
	 * The filtered HTML of the title template, the table of contents and the
	 * navigation is escaped with wp_kses_post().
	 */
	public function test_filtered_markup_is_escaped() {
		add_filter(
			'pchap_page_title_template',
			static function () {
				return '<h3 class="custom-title" onclick="evil()">%s<script>evil("title")</script></h3>';
			}
		);
		add_filter(
			'pchap_toc',
			static function ( $html ) {
				return $html . '<script>evil("toc")</script><p class="toc-extra">TOC-EXTRA</p>';
			}
		);
		add_filter(
			'pchap_link_pages',
			static function ( $html ) {
				return $html . '<iframe src="https://example.com/"></iframe><span class="nav-extra">NAV-EXTRA</span>';
			}
		);

		$post_id = $this->create_post( self::CLASSIC );
		$html    = $this->render_subpage( $post_id, 2 );

		$this->assertStringContainsString( '<h3 class="custom-title">Deux', $html );
		$this->assertStringContainsString( '<p class="toc-extra">TOC-EXTRA</p>', $html );
		$this->assertStringContainsString( '<span class="nav-extra">NAV-EXTRA</span>', $html );
		$this->assertStringNotContainsString( 'onclick', $html );
		$this->assertStringNotContainsString( '<script', $html );
		$this->assertStringNotContainsString( '<iframe', $html );
		$this->assertStringContainsString( 'BODY-2', $html );
	}

	/**
	 * The 1.6 template filters still apply: pchap_toc_args, pchap_toc_pages_row, pchap_toc,
	 * pchap_link_pages_args, pchap_link_pages_link and pchap_link_pages.
	 */
	public function test_legacy_template_filters() {
		$rows = array();
		add_filter(
			'pchap_toc_pages_row',
			static function ( $row, $title, $i ) use ( &$rows ) {
				$rows[ $i ] = $title;
				return str_replace( '<li>', '<li class="row-' . $i . '">', $row );
			},
			10,
			3
		);
		add_filter(
			'pchap_toc_args',
			static function ( $args ) {
				$args['separator'] = ' :: ';
				return $args;
			}
		);
		add_filter(
			'pchap_toc',
			static function ( $html ) {
				return '<div class="toc-wrap">' . $html . '</div>';
			}
		);
		add_filter(
			'pchap_link_pages_args',
			static function ( $args ) {
				$args['link_before'] = '<span class="lb">';
				$args['link_after']  = '</span>';
				return $args;
			}
		);
		add_filter(
			'pchap_link_pages_link',
			static function ( $text ) {
				return '[' . $text . ']';
			}
		);
		add_filter(
			'pchap_link_pages',
			static function ( $html ) {
				return '<div class="nav-wrap">' . $html . '</div>';
			}
		);

		$post_id = $this->create_post( self::CLASSIC );
		$html    = $this->render_subpage( $post_id, 2 );

		$this->assertSame(
			array(
				1 => 'Intro',
				2 => 'Deux',
				3 => 'Trois',
			),
			$rows
		);
		$this->assertStringContainsString( '<li class="row-2">2 :: <span class="current"', $html );
		$this->assertStringContainsString( '<div class="toc-wrap">', $html );
		$this->assertStringContainsString( '<div class="nav-wrap">', $html );
		$this->assertStringContainsString( '[Continue: <a ' . $this->href( $post_id, 3 ) . ' rel="next"><span class="lb">Trois</span></a>]', $html );
	}

	/**
	 * Untitled pages render the translated fallback title, filterable.
	 */
	public function test_untitled_page_fallback_title() {
		$post_id = $this->create_post( PChap_Test_Corpus::items()['blocks legacy default title false']['content'] );

		$html = $this->render_subpage( $post_id, 2 );
		$this->assertStringContainsString( '<h2>Chapter 2</h2>', $html );
		$this->assertStringNotContainsString( 'false', wp_strip_all_tags( $html ) );

		add_filter(
			'pchap_untitled_subpage_title',
			static function ( $title, $number ) {
				return 'Part ' . $number;
			},
			10,
			2
		);

		$html = $this->render_subpage( $post_id, 3 );
		$this->assertStringContainsString( '<h2>Part 3</h2>', $html );
	}

	/**
	 * Rendering the same request twice gives the same output, never decorated twice.
	 */
	public function test_double_the_content_call_does_not_double_decorate() {
		$post_id = $this->create_post( self::CLASSIC );
		$this->go_to_subpage( $post_id, 2 );
		the_post();

		$first  = apply_filters( 'the_content', get_the_content() );
		$second = apply_filters( 'the_content', $first );

		$this->assertSame( 1, substr_count( $second, 'mpp-toc-container' ) );
		$this->assertSame( 1, substr_count( $second, '<h2>Deux</h2>' ) );
		$this->assertSame( array(), $this->content_runs(), 'Every the_content run is closed.' );
	}

	/**
	 * Rendering the same raw content twice decorates both outputs (e.g. a theme
	 * calling the_content() twice for an AMP or print view).
	 */
	public function test_same_raw_content_rendered_twice_is_decorated_twice() {
		$post_id = $this->create_post( self::CLASSIC );
		$this->go_to_subpage( $post_id, 2 );
		the_post();

		$first  = apply_filters( 'the_content', get_the_content() );
		$second = apply_filters( 'the_content', get_the_content() );

		$this->assertSame( $first, $second );
		$this->assertSame( 1, substr_count( $second, 'mpp-toc-container' ) );
		$this->assertSame( 1, substr_count( $second, '<h2>Deux</h2>' ) );
	}

	/**
	 * Block theme path: the_content applied again to the core/post-content output
	 * does not decorate it a second time.
	 */
	public function test_double_the_content_on_post_content_block_output() {
		$item    = PChap_Test_Corpus::items()['blocks 1.7'];
		$post_id = $this->create_post( $item['content'] );

		$this->go_to_subpage( $post_id, 2 );
		$this->assertTrue( have_posts() );
		the_post();

		$html  = do_blocks( '<!-- wp:post-content /-->' );
		$again = apply_filters( 'the_content', $html );

		$this->assertSame( 1, substr_count( $html, 'mpp-toc-container' ), $html );
		$this->assertSame( 1, substr_count( $again, 'mpp-toc-container' ), $again );
		$this->assertSame( 1, substr_count( $again, '<h2>Deux</h2>' ) );
		$this->assertSame( 1, substr_count( $again, 'mpp-post-navigation' ) );
		$this->assertSame( array(), $this->content_runs() );
	}

	/**
	 * Function wp_trim_excerpt() of another post, called from the current post's
	 * the_content (related posts shortcodes), is not decorated and does not stop the
	 * current post from being decorated.
	 */
	public function test_excerpt_of_another_post_inside_the_content() {
		$other_id = $this->create_post( 'Other OTHER-BODY text', array( 'post_title' => 'Other' ) );
		$excerpts = array();

		add_shortcode(
			'pchap_test_related',
			static function () use ( $other_id, &$excerpts ) {
				$excerpt    = wp_trim_excerpt( '', get_post( $other_id ) );
				$excerpts[] = $excerpt;
				return '<aside class="related">' . $excerpt . '</aside>';
			}
		);

		try {
			$post_id = $this->create_post( "Intro BODY-1\n\n[nextpage title=\"Deux\"]\n\nBODY-2\n\n[pchap_test_related]\n\n[nextpage title=\"Trois\"]\n\nBODY-3" );
			$html    = $this->render_subpage( $post_id, 2 );
		} finally {
			remove_shortcode( 'pchap_test_related' );
		}

		$this->assertCount( 1, $excerpts );
		$this->assertStringContainsString( 'OTHER-BODY', $excerpts[0] );
		$this->assertStringNotContainsString( 'Contents', $excerpts[0] );
		$this->assertStringNotContainsString( 'Continue', $excerpts[0] );

		$this->assertSame( 1, substr_count( $html, 'mpp-toc-container' ), $html );
		$this->assertSame( 1, substr_count( $html, '<h2>Deux</h2>' ) );
		$this->assertSame( 1, substr_count( $html, 'mpp-post-navigation' ) );
		$this->assertStringContainsString( '<aside class="related">', $html );
		$this->assertSame( array(), $this->content_runs() );
	}

	/**
	 * A the_content run nested in the current post's the_content (shortcodes that
	 * filter their inner content: boxes, tabs, accordions) is not decorated: the
	 * post is decorated once, around the whole content.
	 *
	 * @dataProvider data_content_priorities
	 *
	 * @param int $priority Priority of the the_content filter (_mpp-rewrite-content-priority).
	 */
	public function test_nested_the_content_run_decorates_once( $priority ) {
		$this->set_options( array( '_mpp-rewrite-content-priority' => $priority ) );

		add_shortcode(
			'pchap_test_box',
			static function ( $atts, $content = '' ) {
				return '<div class="box">' . apply_filters( 'the_content', (string) $content ) . '</div>';
			}
		);

		try {
			$post_id = $this->create_post( "Intro BODY-1\n\n[nextpage title=\"Deux\"]\n\nBODY-2\n\n[pchap_test_box]BOX-TEXT[/pchap_test_box]\n\n[nextpage title=\"Trois\"]\n\nBODY-3" );
			$html    = $this->render_subpage( $post_id, 2 );
		} finally {
			remove_shortcode( 'pchap_test_box' );
		}

		$this->assertStringContainsString( 'BOX-TEXT', $html );
		$this->assertSame( 1, substr_count( $html, 'mpp-toc-container' ), $html );
		$this->assertSame( 1, substr_count( $html, '<h2>Deux</h2>' ), $html );
		$this->assertSame( 1, substr_count( $html, 'mpp-post-navigation' ), $html );

		// Nothing of the decoration inside the box.
		$box_start = strpos( $html, '<div class="box">' );
		$this->assertNotFalse( $box_start );
		$box = substr( $html, $box_start, strpos( $html, '</div>', $box_start ) - $box_start );
		$this->assertStringNotContainsString( 'mpp-toc', $box );
		$this->assertStringNotContainsString( '<h2>', $box );

		$this->assertSame( array(), $this->content_runs() );
	}

	/**
	 * A the_content run nested in an excerpt (wp_trim_excerpt() strips the shortcodes,
	 * so here a the_content filter that filters an inner content) is not decorated either.
	 */
	public function test_nested_run_inside_excerpt_is_not_decorated() {
		$post_id = $this->create_post( "Intro BODY-1\n\n[nextpage title=\"Deux\"]\n\nBODY-2" );
		$this->go_to_subpage( $post_id, 1 );
		$this->assertTrue( have_posts() );
		the_post();

		$nesting = false;
		$box     = static function ( $content ) use ( &$nesting ) {
			if ( $nesting ) {
				return $content;
			}
			$nesting = true;
			try {
				return $content . '<div class="box">' . apply_filters( 'the_content', 'BOX-TEXT' ) . '</div>';
			} finally {
				$nesting = false;
			}
		};
		add_filter( 'the_content', $box, 11 );

		try {
			$excerpt = wp_trim_excerpt( '' );
		} finally {
			remove_filter( 'the_content', $box, 11 );
		}

		$this->assertStringContainsString( 'BOX-TEXT', $excerpt );
		$this->assertStringNotContainsString( 'Contents', $excerpt );
		$this->assertStringNotContainsString( 'Continue', $excerpt );
		$this->assertSame( array(), $this->content_runs() );
	}

	/**
	 * A the_content run interrupted by an exception does not leave the run stack out of
	 * sync: the next runs are still decorated, and still once.
	 */
	public function test_interrupted_the_content_run_does_not_break_later_runs() {
		$post_id = $this->create_post( self::CLASSIC );
		$this->go_to_subpage( $post_id, 2 );
		the_post();

		$thrower = static function () {
			throw new RuntimeException( 'Interrupted' );
		};
		add_filter( 'the_content', $thrower, 15 );

		try {
			apply_filters( 'the_content', get_the_content() );
			$this->fail( 'The the_content run was not interrupted.' );
		} catch ( RuntimeException $e ) {
			$this->assertSame( 'Interrupted', $e->getMessage() );
		} finally {
			remove_filter( 'the_content', $thrower, 15 );
			// Core does not pop the filter stack on an exception (as after a fatal error handler).
			global $wp_current_filter;
			array_pop( $wp_current_filter );
		}

		$this->assertCount( 1, $this->content_runs(), 'The interrupted run is left open.' );

		$html = apply_filters( 'the_content', get_the_content() );

		$this->assertSame( 1, substr_count( $html, 'mpp-toc-container' ), $html );
		$this->assertSame( 1, substr_count( $html, '<h2>Deux</h2>' ) );
		$this->assertSame( array(), $this->content_runs() );

		$again = apply_filters( 'the_content', $html );
		$this->assertSame( 1, substr_count( $again, 'mpp-toc-container' ) );
	}

	/**
	 * Content filter priorities: the default, before and after do_shortcode (11).
	 *
	 * @return array
	 */
	public function data_content_priorities() {
		return array(
			'default (20)' => array( 20 ),
			'early (5)'    => array( 5 ),
		);
	}

	/**
	 * The the_content runs in progress (private PChap::$content_runs).
	 *
	 * @return array
	 */
	private function content_runs() {
		$property = new ReflectionProperty( pchap(), 'content_runs' );
		if ( PHP_VERSION_ID < 80100 ) {
			$property->setAccessible( true );
		}

		return $property->getValue( pchap() );
	}

	/**
	 * The plugin stylesheet is enqueued on multipage views only.
	 */
	public function test_stylesheet_is_enqueued_on_multipage_views() {
		$post_id = $this->create_post( self::CLASSIC );
		$this->go_to_subpage( $post_id, 1 );

		do_action( 'wp_enqueue_scripts' );

		$this->assertTrue( wp_style_is( 'paginova', 'enqueued' ) );
		$this->assertStringContainsString( 'css/paginova', wp_styles()->registered['paginova']->src );

		wp_dequeue_style( 'paginova' );
		wp_deregister_style( 'paginova' );

		$plain_id = $this->create_post( 'Plain' );
		$this->go_to_subpage( $plain_id, 1 );
		do_action( 'wp_enqueue_scripts' );
		$this->assertFalse( wp_style_is( 'paginova', 'enqueued' ) );
	}

	/**
	 * A classic shortcode wrapped in a styled paragraph (TinyMCE "justify") leaves no
	 * paragraph open on either side of the cut, after wpautop().
	 */
	public function test_shortcode_in_styled_paragraph_is_balanced() {
		$content = "<p style=\"text-align: justify;\">Intro BODY-1</p>\n<p style=\"text-align: justify;\">[nextpage title=\"Mazda: un moteur essence suralimenté\"]</p>\n<p style=\"text-align: justify;\">BODY-2</p>\n\nBODY-2b";
		$post_id = $this->create_post( $content );

		foreach ( array( 1, 2 ) as $page ) {
			$html = $this->render_subpage( $post_id, $page );
			$this->assertStringContainsString( 'BODY-' . $page, $html );
			$this->assertTagBalanced( 'p', $html, "Page {$page}." );
		}
	}

	/**
	 * With a persistent object cache, a parse stored by another build of the same version
	 * (key "parse-{PCHAP_VERSION}-{md5}" up to 1.7.0) is never served.
	 */
	public function test_stale_parse_cache_entry_is_ignored() {
		$post_id = $this->create_post( self::CLASSIC );
		$content = get_post( $post_id )->post_content;

		$stale = array(
			'is_multipage' => true,
			'has_intro'    => true,
			'pages'        => array(
				array(
					'slug'    => 'intro',
					'title'   => '%%intro%%',
					'content' => 'STALE-1',
					'kind'    => 'intro',
				),
				array(
					'slug'    => 'deux',
					'title'   => 'Deux',
					'content' => '<p style="text-align: justify;">STALE-2',
					'kind'    => 'shortcode',
				),
			),
		);

		// go_to() flushes the object cache: store the entry during the request.
		add_action(
			'parse_request',
			static function () use ( $content, $stale ) {
				wp_cache_set( 'parse-' . PCHAP_VERSION . '-' . md5( $content ), $stale, 'paginova' );
			}
		);

		$html = $this->render_subpage( $post_id, 2 );
		$this->assertStringContainsString( 'BODY-2', $html );
		$this->assertStringNotContainsString( 'STALE', $html );
	}
}
