<?php
/**
 * SEO: the chapter title in the titles of SEO plugins, the URLs and robots of the chapters,
 * and the integrations of Yoast SEO, Rank Math, SEOPress, All in One SEO and The SEO Framework.
 *
 * @package Post Chapters
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Test classes use the required Test_PChap_ prefix.

/**
 * PChap_SEO and the PChap_SEO_Integration classes.
 *
 * @group seo
 */
class Test_PChap_SEO extends PChap_UnitTestCase {

	/**
	 * Intro, then the chapters "Un" and "Deux".
	 */
	const CONTENT = 'Intro[nextpage title="Un"]Premier[nextpage title="Deux"]Second';

	/**
	 * ID of the post of the test.
	 *
	 * @var int
	 */
	private $post_id = 0;

	/**
	 * Create the post.
	 */
	public function set_up() {
		parent::set_up();

		$this->set_permalink_structure( '/%postname%/' );
		$this->post_id = $this->create_post(
			self::CONTENT,
			array(
				'post_title' => 'Mon article',
				'post_name'  => 'mon-article',
			)
		);
	}

	/**
	 * Go to a chapter.
	 *
	 * @param int $page Page number, 1-based.
	 * @return PChap_SEO
	 */
	private function seo_on_page( $page ) {
		$this->go_to_subpage( $this->post_id, $page );

		return pchap()->seo;
	}

	/**
	 * Titles built by SEO plugins on the second page ("Un"), and the title they must become.
	 *
	 * @return array Title, expected title, whether the title is HTML.
	 */
	public function data_titles() {
		return array(
			'Rank Math / SEOPress default: no page variable' => array( 'Mon article - Site', 'Mon article - Un - Site', false ),
			'another separator'                           => array( 'Mon article | Site', 'Mon article | Un | Site', false ),
			'The SEO Framework: "Page 2" before the site' => array( 'Mon article - Page 2 - Site', 'Mon article - Un - Site', false ),
			'All in One SEO: "Page 2" at the end'         => array( 'Mon article - Site&nbsp;- Page 2', 'Mon article - Un - Site', false ),
			'already has the chapter'                     => array( 'Mon article - Un - Site', 'Mon article - Un - Site', false ),
			'post title not found'                        => array( 'Other', 'Other - Un', false ),
			'HTML title'                                  => array( 'Mon article &#8211; Site', 'Mon article &#8211; Un &#8211; Site', true ),
			'empty'                                       => array( '', '', false ),
		);
	}

	/**
	 * The chapter title goes after the title of the post, with its separator.
	 *
	 * @dataProvider data_titles
	 *
	 * @param string $title    Title.
	 * @param string $expected Expected title.
	 * @param bool   $html     Whether the title is HTML.
	 */
	public function test_ensure_chapter_title( $title, $expected, $html ) {
		$seo = $this->seo_on_page( 2 );

		$this->assertSame( $expected, $seo->ensure_chapter_title( $title, $html ) );
	}

	/**
	 * The first page, and posts that are not rendered by the plugin, keep their titles.
	 */
	public function test_ensure_chapter_title_only_on_chapters() {
		$seo = $this->seo_on_page( 1 );
		$this->assertSame( 'Mon article - Site', $seo->ensure_chapter_title( 'Mon article - Site' ) );

		$other = self::factory()->post->create( array( 'post_title' => 'Autre' ) );
		$this->go_to( get_permalink( $other ) );
		$this->assertSame( 'Autre - Site', pchap()->seo->ensure_chapter_title( 'Autre - Site' ) );
	}

	/**
	 * A title built for another post is left alone.
	 */
	public function test_ensure_chapter_title_other_post_context() {
		$seo = $this->seo_on_page( 2 );

		$this->assertSame( 'Mon article - Site', $seo->ensure_chapter_title( 'Mon article - Site', false, array( 'ID' => $this->post_id + 100 ) ) );
		$this->assertSame( 'Mon article - Un - Site', $seo->ensure_chapter_title( 'Mon article - Site', false, array( 'ID' => $this->post_id ) ) );
	}

	/**
	 * Characters of the title are not double-encoded.
	 */
	public function test_ensure_chapter_title_html_escapes_the_chapter() {
		$post_id = $this->create_post(
			'Intro[nextpage title="A & B"]Texte',
			array(
				'post_title' => 'Mon article',
				'post_name'  => 'autre',
			)
		);
		$this->go_to_subpage( $post_id, 2 );

		$this->assertSame( 'Mon article - A &amp; B - Site', pchap()->seo->ensure_chapter_title( 'Mon article - Site', true ) );
		$this->assertSame( 'Mon article - A & B - Site', pchap()->seo->ensure_chapter_title( 'Mon article - Site', false ) );
	}

	/**
	 * The "Page N" part of a text, and the separator before it.
	 */
	public function test_strip_page_number() {
		$seo = $this->seo_on_page( 2 );

		$this->assertSame( 'Description', $seo->strip_page_number( 'Description - Page 2' ) );
		$this->assertSame( 'Description', $seo->strip_page_number( 'Description&nbsp;- Page 2' ) );
		$this->assertSame( 'Description', $seo->strip_page_number( "Description\xc2\xa0&#8211; Page 2" ) );
		$this->assertSame( 'Description', $seo->strip_page_number( 'Description | Page 2' ) );
		$this->assertSame( 'A - B', $seo->strip_page_number( 'A - Page 2 - B' ) );
		$this->assertSame( 'Page 20 of text', $seo->strip_page_number( 'Page 20 of text' ), 'Only the number of this page.' );
		$this->assertSame( 'No number', $seo->strip_page_number( 'No number' ) );
	}

	/**
	 * The URL of the post given by an SEO plugin becomes the URL of the chapter.
	 */
	public function test_maybe_chapter_url() {
		$seo       = $this->seo_on_page( 2 );
		$permalink = get_permalink( $this->post_id );

		$this->assertSame( home_url( '/mon-article/2/' ), $seo->maybe_chapter_url( $permalink ) );
		$this->assertSame( home_url( '/mon-article/2/' ), $seo->maybe_chapter_url( untrailingslashit( $permalink ) ) );
		$this->assertSame( 'http://example.org/custom/', $seo->maybe_chapter_url( 'http://example.org/custom/' ), 'Another URL is kept.' );
		$this->assertSame( '', $seo->maybe_chapter_url( '' ) );

		$seo = $this->seo_on_page( 1 );
		$this->assertSame( $permalink, $seo->maybe_chapter_url( $permalink ), 'The first page is the post.' );
	}

	/**
	 * The last word on the document title is given after the plugins, on template_redirect.
	 */
	public function test_document_title_filter() {
		$seo = $this->seo_on_page( 2 );

		$this->assertSame( PHP_INT_MAX, has_action( 'template_redirect', array( $seo, 'add_title_filter' ) ) );
		$this->assertFalse( has_filter( 'pre_get_document_title', array( $seo, 'filter_pre_document_title' ) ) );

		$seo->add_title_filter();

		$this->assertSame( PHP_INT_MAX, has_filter( 'pre_get_document_title', array( $seo, 'filter_pre_document_title' ) ) );
		$this->assertSame( 'Mon article &#8211; Un &#8211; Site', apply_filters( 'pre_get_document_title', 'Mon article &#8211; Site' ) );
		$this->assertSame( '', apply_filters( 'pre_get_document_title', '' ), 'Without an SEO plugin, WordPress builds the title.' );
	}

	/**
	 * Yoast SEO: Open Graph URL and schema WebPage URL are the ones of the chapter.
	 */
	public function test_yoast_urls() {
		$seo       = $this->seo_on_page( 2 );
		$yoast     = $seo->get_integration( 'yoast' );
		$permalink = get_permalink( $this->post_id );
		$chapter   = home_url( '/mon-article/2/' );

		$this->assertSame( $chapter, $yoast->filter_opengraph_url( $permalink ) );
		$this->assertSame( 'http://example.org/', $yoast->filter_opengraph_url( 'http://example.org/' ) );

		$webpage = $yoast->filter_schema_webpage(
			array(
				'@id' => $permalink . '#webpage',
				'url' => $permalink,
			)
		);
		$this->assertSame( $chapter, $webpage['url'] );
		$this->assertSame( $permalink . '#webpage', $webpage['@id'], 'The ids of the graph are kept.' );

		$this->assertSame( array( 'name' => 'x' ), $yoast->filter_schema_webpage( array( 'name' => 'x' ) ) );
		$this->assertSame( 'not an array', $yoast->filter_schema_webpage( 'not an array' ) );
	}

	/**
	 * Rank Math: the Open Graph and Twitter titles get the chapter title.
	 */
	public function test_rank_math_titles() {
		$seo = $this->seo_on_page( 2 );

		$this->assertSame( 'Mon article - Un - Site', $seo->get_integration( 'rank_math' )->filter_title( 'Mon article - Site' ) );
	}

	/**
	 * SEOPress: the chapter title goes after %%post_title%% in the title templates.
	 */
	public function test_seopress_titles() {
		$seopress = $this->seo_on_page( 2 )->get_integration( 'seopress' );

		$this->assertSame( '%%post_title%% %%sep%% Un %%sep%% %%sitetitle%%', $seopress->filter_title( '%%post_title%% %%sep%% %%sitetitle%%' ) );
		$this->assertSame( '%%post_title%% %%page%% %%sep%% %%sitetitle%%', $seopress->filter_title( '%%post_title%% %%page%% %%sep%% %%sitetitle%%' ), 'A template with %%page%% is kept.' );
		$this->assertSame( '%%tag_title%% %%sitetitle%%', $seopress->filter_title( '%%tag_title%% %%sitetitle%%' ), 'Another template is kept.' );
		$this->assertSame( 'Mon article - Un - Site', $seopress->filter_title( 'Mon article - Site' ), 'A title without variables.' );
		$this->assertSame( '%%post_title%% %%sep%% Un', $seopress->filter_title( '%%post_title%%' ) );

		$post_id = $this->create_post(
			'Intro[nextpage title="100%% sûr"]Texte',
			array(
				'post_title' => 'T',
				'post_name'  => 'autre',
			)
		);
		$this->go_to_subpage( $post_id, 2 );
		$this->assertStringNotContainsString( '%%%%', pchap()->seo->get_integration( 'seopress' )->filter_title( '%%post_title%% %%sitetitle%%' ) );
	}

	/**
	 * SEOPress: the canonical tag points to the chapter, unless the user chose a canonical.
	 */
	public function test_seopress_canonical() {
		$seopress  = $this->seo_on_page( 2 )->get_integration( 'seopress' );
		$permalink = get_permalink( $this->post_id );

		$this->assertSame( '<link rel="canonical" href="' . home_url( '/mon-article/2/' ) . '">', $seopress->filter_canonical( '<link rel="canonical" href="' . $permalink . '">' ) );
		$this->assertSame( '<link rel="canonical" href="https://example.org/mine/">', $seopress->filter_canonical( '<link rel="canonical" href="https://example.org/mine/">' ) );
		$this->assertSame( 'no tag', $seopress->filter_canonical( 'no tag' ) );

		$seopress = $this->seo_on_page( 1 )->get_integration( 'seopress' );
		$this->assertSame( '<link rel="canonical" href="' . $permalink . '">', $seopress->filter_canonical( '<link rel="canonical" href="' . $permalink . '">' ) );
	}

	/**
	 * All in One SEO: descriptions lose the "Page N" suffix, its rel links are dropped, the WebPage URL is the chapter's.
	 */
	public function test_aioseo_filters() {
		$permalink = get_permalink( $this->post_id );
		$aioseo    = $this->seo_on_page( 2 )->get_integration( 'aioseo' );

		$this->assertSame( 'Mon article - Un - Site', $aioseo->filter_title( 'Mon article - Site&nbsp;- Page 2' ) );
		$this->assertSame( 'A description', $aioseo->filter_description( 'A description - Page 2' ) );
		$this->assertSame( '', $aioseo->filter_adjacent_link( 'http://example.org/next/' ) );

		$graph = $aioseo->filter_schema_output(
			array(
				array(
					'@type' => 'WebPage',
					'url'   => $permalink,
				),
				array(
					'@type' => 'WebSite',
					'url'   => home_url( '/' ),
				),
			)
		);
		$this->assertSame( home_url( '/mon-article/2/' ), $graph[0]['url'] );
		$this->assertSame( home_url( '/' ), $graph[1]['url'] );

		$aioseo = $this->seo_on_page( 1 )->get_integration( 'aioseo' );
		$this->assertSame( 'A description - Page 2', $aioseo->filter_description( 'A description - Page 2' ) );
	}

	/**
	 * All in One SEO: the robots are only changed when AIOSEO can tell what the post has.
	 */
	public function test_aioseo_robots_without_aioseo() {
		$aioseo     = $this->seo_on_page( 2 )->get_integration( 'aioseo' );
		$attributes = array(
			'noindex'  => 'noindex',
			'nofollow' => 'nofollow',
		);

		$this->assertSame( $attributes, $aioseo->filter_robots_meta( $attributes ) );
		$this->assertSame( array(), $aioseo->filter_robots_meta( array() ) );
		$this->assertSame( 'x', $aioseo->filter_robots_meta( 'x' ) );
	}

	/**
	 * The SEO Framework: no rel links of its own, the chapter title in the social titles.
	 */
	public function test_seo_framework_render_data() {
		$framework = $this->seo_on_page( 2 )->get_integration( 'seo_framework' );

		$tags = $framework->filter_meta_render_data(
			array(
				'prev'          => array( 'tag' => 'link' ),
				'next'          => array( 'tag' => 'link' ),
				'canonical'     => array( 'tag' => 'link' ),
				'og:title'      => array( 'attributes' => array( 'content' => 'Mon article - Page 2' ) ),
				'twitter:title' => array( 'attributes' => array( 'content' => 'Mon article - Page 2' ) ),
				'og:locale'     => array( 'attributes' => array( 'content' => 'fr_FR' ) ),
			)
		);

		$this->assertArrayNotHasKey( 'prev', $tags );
		$this->assertArrayNotHasKey( 'next', $tags );
		$this->assertArrayHasKey( 'canonical', $tags );
		$this->assertSame( 'Mon article - Un', $tags['og:title']['attributes']['content'] );
		$this->assertSame( 'Mon article - Un', $tags['twitter:title']['attributes']['content'] );
		$this->assertSame( 'fr_FR', $tags['og:locale']['attributes']['content'] );
		$this->assertSame( 'x', $framework->filter_meta_render_data( 'x' ) );
	}

	/**
	 * No integration registers its hooks when its plugin is not active.
	 */
	public function test_inactive_integrations_register_nothing() {
		$this->go_to_subpage( $this->post_id, 2 );

		foreach ( array( 'yoast', 'rank_math', 'seopress', 'aioseo', 'seo_framework' ) as $key ) {
			$integration = pchap()->seo->get_integration( $key );
			$this->assertInstanceOf( 'PChap_SEO_Integration', $integration, $key );

			if ( ! $integration->is_active() ) {
				$this->assertFalse( has_filter( 'aioseo_title', array( $integration, 'filter_title' ) ), $key );
			}
		}

		$this->assertNull( pchap()->seo->get_integration( 'nope' ) );
	}

	/**
	 * An integration added with the pchap_seo_integrations filter is registered when it is active.
	 */
	public function test_custom_integration() {
		$integration = new class( pchap(), pchap()->seo ) extends PChap_SEO_Integration {

			/**
			 * Whether the hooks were registered.
			 *
			 * @var bool
			 */
			public $registered = false;

			/**
			 * Always active.
			 *
			 * @return bool
			 */
			public function is_active() {
				return true;
			}

			/**
			 * Remember the call.
			 */
			public function register_hooks() {
				$this->registered = true;
			}
		};

		add_filter(
			'pchap_seo_integrations',
			static function ( $integrations ) use ( $integration ) {
				$integrations['custom'] = $integration;
				return $integrations;
			}
		);

		$this->go_to_subpage( $this->post_id, 2 );

		$this->assertTrue( $integration->registered );
	}
}
