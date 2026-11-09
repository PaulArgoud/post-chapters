<?php
/**
 * Request handling tests: document title, rel links, 404 and canonical redirects.
 *
 * @package Post Chapters
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Test classes use the required Test_PChap_ prefix and tests apply core and third-party hooks.

/**
 * Request lifecycle: pre_handle_404, redirect_canonical, wp, wp_head.
 *
 * @group multipage
 * @group request
 */
class Test_PChap_Request extends PChap_UnitTestCase {

	/**
	 * Three-page classic post: Intro, Deux, Trois.
	 *
	 * @var string
	 */
	const CLASSIC = "Intro\n\n[nextpage title=\"Deux\"]\n\nTwo\n\n[nextpage title=\"Trois\"]\n\nThree";

	/**
	 * Document title: unchanged on page 1, the subpage title replaces "Page N" on subpages >= 2.
	 */
	public function test_document_title() {
		$post_id = $this->create_post( self::CLASSIC, array( 'post_title' => 'Mon article' ) );

		$this->go_to_subpage( $post_id, 1 );
		$title = wp_get_document_title();
		$this->assertStringContainsString( 'Mon article', $title );
		$this->assertStringNotContainsString( 'Intro', $title );
		$this->assertFalse( has_filter( 'document_title_parts', array( pchap()->seo, 'filter_document_title_parts' ) ) );

		$this->go_to_subpage( $post_id, 2 );
		$title = wp_get_document_title();
		$this->assertStringContainsString( 'Mon article', $title );
		$this->assertStringContainsString( 'Deux', $title );
		$this->assertStringNotContainsString( 'Page 2', $title );

		$this->go_to_subpage( $post_id, 3 );
		$this->assertStringContainsString( 'Trois', wp_get_document_title() );
	}

	/**
	 * Document title of an untitled subpage keeps a "Chapter N" part.
	 */
	public function test_document_title_of_untitled_subpage() {
		$post_id = $this->create_post( "Intro\n\n[nextpage]\n\nTwo" );

		$this->go_to_subpage( $post_id, 2 );

		$this->assertStringContainsString( 'Chapter 2', wp_get_document_title() );
	}

	/**
	 * The title rewrite priority setting is used for the document title filter.
	 */
	public function test_document_title_priority_setting() {
		$this->set_options( array( '_mpp-rewrite-title-priority' => 50 ) );
		$post_id = $this->create_post( self::CLASSIC );

		$this->go_to_subpage( $post_id, 2 );

		$this->assertSame( 50, has_filter( 'document_title_parts', array( pchap()->seo, 'filter_document_title_parts' ) ) );
	}

	/**
	 * The content rewrite priority setting is used for the_content.
	 */
	public function test_content_priority_setting() {
		$this->set_options( array( '_mpp-rewrite-content-priority' => 5 ) );
		$post_id = $this->create_post( self::CLASSIC );

		$this->go_to_subpage( $post_id, 2 );

		$this->assertSame( 5, has_filter( 'the_content', array( pchap(), 'pchap_the_content' ) ) );
	}

	/**
	 * The rel prev/next links in wp_head.
	 */
	public function test_rel_links_in_wp_head() {
		$post_id = $this->create_post( self::CLASSIC );

		$this->go_to_subpage( $post_id, 1 );
		$head = (string) get_echo( array( pchap()->seo, 'print_rel_links' ) );
		$this->assertStringNotContainsString( 'rel="prev"', $head );
		$this->assertStringContainsString( '<link rel="next" href="' . esc_url( pchap_link_page_url( 2, '', $post_id ) ) . '" />', $head );
		$this->assertSame( 10, has_action( 'wp_head', array( pchap()->seo, 'print_rel_links' ) ) );

		$this->go_to_subpage( $post_id, 2 );
		$head = (string) get_echo( 'do_action', array( 'wp_head' ) );
		$this->assertStringContainsString( '<link rel="prev" href="' . esc_url( pchap_link_page_url( 1, '', $post_id ) ) . '" />', $head );
		$this->assertStringContainsString( '<link rel="next" href="' . esc_url( pchap_link_page_url( 3, '', $post_id ) ) . '" />', $head );

		$this->go_to_subpage( $post_id, 3 );
		$head = (string) get_echo( array( pchap()->seo, 'print_rel_links' ) );
		$this->assertStringContainsString( 'rel="prev"', $head );
		$this->assertStringNotContainsString( 'rel="next"', $head );
	}

	/**
	 * The rel links use the pretty subpage URLs.
	 */
	public function test_rel_links_with_pretty_permalinks() {
		$this->set_permalink_structure( '/%postname%/' );
		$post_id = $this->create_post( self::CLASSIC, array( 'post_name' => 'mon-article' ) );

		$this->go_to_subpage( $post_id, 2 );
		$head = (string) get_echo( array( pchap()->seo, 'print_rel_links' ) );

		$this->assertStringContainsString( '<link rel="prev" href="' . esc_url( home_url( '/mon-article/' ) ) . '" />', $head );
		$this->assertStringContainsString( '<link rel="next" href="' . esc_url( home_url( '/mon-article/3/' ) ) . '" />', $head );
	}

	/**
	 * No rel links for a post that is not multipage.
	 */
	public function test_no_rel_links_for_plain_post() {
		$post_id = $this->create_post( 'Plain' );

		$this->go_to_subpage( $post_id, 1 );

		$this->assertSame( '', (string) get_echo( array( pchap()->seo, 'print_rel_links' ) ) );
	}

	/**
	 * Out-of-range subpages are 404, valid subpages are not.
	 */
	public function test_404_handling() {
		$post_id = $this->create_post( self::CLASSIC );

		foreach ( array( 1, 2, 3 ) as $page ) {
			$this->go_to_subpage( $post_id, $page );
			$this->assertFalse( is_404(), "Subpage {$page}." );
			$this->assertTrue( is_single() );
			$this->assertTrue( pchap()->is_multipage_view() );
		}

		foreach ( array( 4, 99 ) as $page ) {
			$this->go_to_subpage( $post_id, $page );
			$this->assertTrue( is_404(), "Subpage {$page}." );
			$this->assertFalse( pchap()->is_multipage_view() );
			$this->assertFalse( has_filter( 'the_content', array( pchap(), 'pchap_the_content' ) ) );
		}
	}

	/**
	 * Valid subpages with pretty permalinks are not 404 (core counts the raw <!--nextpage--> only).
	 */
	public function test_404_handling_with_pretty_permalinks() {
		$this->set_permalink_structure( '/%postname%/' );
		$post_id = $this->create_post( PChap_Test_Corpus::items()['blocks 1.7']['content'] );

		$this->go_to( trailingslashit( get_permalink( $post_id ) ) . '3/' );
		$this->assertFalse( is_404() );
		$this->assertSame( 3, pchap()->get_current_page() );

		$this->reset_multipage();
		$this->go_to( trailingslashit( get_permalink( $post_id ) ) . '4/' );
		$this->assertTrue( is_404() );
	}

	/**
	 * The redirect_canonical filter keeps valid subpages.
	 */
	public function test_redirect_canonical_filter() {
		$post_id = $this->create_post( self::CLASSIC );

		$this->go_to_subpage( $post_id, 2 );
		$requested = pchap_link_page_url( 2, '', $post_id );

		// A redirect elsewhere (e.g. to the base URL) is cancelled.
		$this->assertFalse( apply_filters( 'redirect_canonical', get_permalink( $post_id ), $requested ) );
		$this->assertFalse( apply_filters( 'redirect_canonical', add_query_arg( 'page', 3, get_permalink( $post_id ) ), $requested ) );

		// A redirect to the same subpage is kept.
		$same = add_query_arg( 'page', 2, get_permalink( $post_id ) );
		$this->assertSame( $same, apply_filters( 'redirect_canonical', $same, $requested ) );

		// Page 1 and out-of-range pages are left to WordPress.
		$this->go_to_subpage( $post_id, 1 );
		$elsewhere = home_url( '/elsewhere/' );
		$this->assertSame( $elsewhere, apply_filters( 'redirect_canonical', $elsewhere, get_permalink( $post_id ) ) );

		$this->go_to_subpage( $post_id, 7 );
		$this->assertSame( $elsewhere, apply_filters( 'redirect_canonical', $elsewhere, $requested ) );
	}

	/**
	 * Core redirect_canonical() does not redirect valid pretty subpages of a shortcode post.
	 */
	public function test_core_redirect_canonical_keeps_valid_pretty_subpages() {
		$this->set_permalink_structure( '/%postname%/' );
		$post_id = $this->create_post( self::CLASSIC, array( 'post_name' => 'mon-article' ) );

		$url = home_url( '/mon-article/2/' );
		$this->go_to( $url );
		$this->assertFalse( is_404() );

		$redirect = redirect_canonical( $url, false );
		$this->assertTrue( null === $redirect || false === $redirect || $url === $redirect, 'Unexpected redirect to ' . var_export( $redirect, true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export -- Assertion failure message.
	}

	/**
	 * Feeds, archives and the admin are not multipage views.
	 */
	public function test_non_singular_requests_are_not_multipage_views() {
		$this->create_post( self::CLASSIC );

		$this->reset_multipage();
		$this->go_to( home_url( '/' ) );
		$this->assertFalse( pchap()->is_multipage_view() );

		$this->reset_multipage();
		$this->go_to( home_url( '/?feed=rss2' ) );
		$this->assertTrue( is_feed() );
		$this->assertFalse( pchap()->is_multipage_view() );
	}

	/**
	 * PChap::setup() is idempotent and only for the main query.
	 */
	public function test_setup_is_idempotent_and_main_query_only() {
		$post_id = $this->create_post( self::CLASSIC );
		$this->go_to_subpage( $post_id, 2 );

		$mpp = pchap();
		$this->assertTrue( $mpp->setup() );
		$this->assertTrue( $mpp->setup( $GLOBALS['wp_the_query'] ) );
		$this->assertSame( 2, $mpp->get_current_page() );
		$this->assertSame( 3, $mpp->max_num_pages );
		$this->assertSame( 'deux', $mpp->pchap_pagename );
		$this->assertSame( 1, $mpp->pchap_index );
		$this->assertSame( 'Deux', $mpp->page_title );
		$this->assertSame( $post_id, $mpp->post_id );

		$secondary = new WP_Query( array( 'p' => $post_id ) );
		$this->assertFalse( $mpp->setup( $secondary ) );
		$this->assertSame( 2, $mpp->get_current_page(), 'A secondary query does not reset the data.' );
	}

	/**
	 * Yoast SEO, Rank Math and SEOPress replacement filters (called directly: the plugins are not loaded).
	 */
	public function test_seo_replacement_filters() {
		$post_id = $this->create_post( self::CLASSIC );
		$mpp     = pchap();

		$this->go_to_subpage( $post_id, 1 );
		$this->assertSame(
			array(
				'%%page%%' => 'Page 1 of 3',
				'%%sep%%'  => '-',
			),
			$mpp->seo->get_integration( 'yoast' )->filter_replacements(
				array(
					'%%page%%' => 'Page 1 of 3',
					'%%sep%%'  => '-',
				)
			),
			'First page unchanged.'
		);

		$this->go_to_subpage( $post_id, 2 );
		$post = get_post( $post_id );

		$yoast = $mpp->seo->get_integration( 'yoast' )->filter_replacements(
			array(
				'%%page%%' => '- Page 2 of 3',
				'%%sep%%'  => '-',
			),
			$post
		);
		$this->assertSame( '- Deux', $yoast['%%page%%'] );

		// Another post's replacements are left alone.
		$other = self::factory()->post->create_and_get();
		$this->assertSame( array( '%%page%%' => 'x' ), $mpp->seo->get_integration( 'yoast' )->filter_replacements( array( '%%page%%' => 'x' ), $other ) );

		$rank_math = $mpp->seo->get_integration( 'rank_math' )->filter_replacements(
			array(
				'%page%' => 'Page 2 of 3',
				'%sep%'  => '|',
			),
			$post
		);
		$this->assertSame( '| Deux', $rank_math['%page%'] );

		$this->assertSame( ' - Deux', $mpp->seo->get_integration( 'seopress' )->filter_context_paged( '' ) );
		$this->assertSame( 'Deux', $mpp->seo->get_integration( 'seopress' )->filter_tag_page_value( '' ) );
		$this->assertSame( 'Page 2 of 3', $mpp->seo->get_integration( 'seopress' )->filter_tag_page_value( 'Page 2 of 3' ) );

		$this->assertSame( '', $mpp->seo->get_integration( 'yoast' )->filter_adjacent_rel_url( 'http://example.org/x' ) );
		$this->assertTrue( $mpp->seo->get_integration( 'rank_math' )->filter_disable_adjacent_rel_links( false ) );
	}

	/**
	 * Yoast SEO canonical: subpages >= 2 get their own URL instead of the first page's
	 * (Yoast only paginates the canonical of posts with core <!--nextpage--> markers).
	 */
	public function test_wpseo_canonical_filter() {
		$this->set_permalink_structure( '/%postname%/' );
		$post_id   = $this->create_post( self::CLASSIC, array( 'post_name' => 'mon-article' ) );
		$mpp       = pchap();
		$permalink = get_permalink( $post_id );

		$this->go_to_subpage( $post_id, 1 );
		$this->assertSame( $permalink, $mpp->seo->get_integration( 'yoast' )->filter_canonical( $permalink ), 'First page unchanged.' );

		$this->go_to_subpage( $post_id, 2 );
		$this->assertSame( 2, $mpp->get_current_page() );
		$this->assertSame( home_url( '/mon-article/2/' ), $mpp->seo->get_integration( 'yoast' )->filter_canonical( $permalink ) );
		$this->assertSame( home_url( '/mon-article/2/' ), $mpp->seo->get_integration( 'yoast' )->filter_canonical( untrailingslashit( $permalink ) ) );

		// A canonical set by the user, or pointing elsewhere, is kept.
		$custom = home_url( '/elsewhere/' );
		$this->assertSame( $custom, $mpp->seo->get_integration( 'yoast' )->filter_canonical( $custom ) );

		$presentation        = new stdClass();
		$presentation->model = (object) array( 'canonical' => $permalink );
		$this->assertSame( $permalink, $mpp->seo->get_integration( 'yoast' )->filter_canonical( $permalink, $presentation ) );

		$presentation->model->canonical = null;
		$this->assertSame( home_url( '/mon-article/2/' ), $mpp->seo->get_integration( 'yoast' )->filter_canonical( $permalink, $presentation ) );

		$this->assertSame( '', $mpp->seo->get_integration( 'yoast' )->filter_canonical( '' ), 'An empty canonical (noindex) stays empty.' );
	}

	/**
	 * The SEO integrations are only hooked for the active SEO plugins.
	 */
	public function test_seo_integrations_are_not_hooked_without_seo_plugins() {
		$post_id = $this->create_post( self::CLASSIC );
		$this->go_to_subpage( $post_id, 2 );

		if ( ! defined( 'WPSEO_VERSION' ) ) {
			$this->assertFalse( has_filter( 'wpseo_replacements', array( pchap()->seo->get_integration( 'yoast' ), 'filter_replacements' ) ) );
		}
		if ( ! defined( 'RANK_MATH_VERSION' ) ) {
			$this->assertFalse( has_filter( 'rank_math/replacements', array( pchap()->seo->get_integration( 'rank_math' ), 'filter_replacements' ) ) );
		}
		if ( ! defined( 'SEOPRESS_VERSION' ) ) {
			$this->assertFalse( has_filter( 'seopress_context_paged', array( pchap()->seo->get_integration( 'seopress' ), 'filter_context_paged' ) ) );
		}
	}
}
