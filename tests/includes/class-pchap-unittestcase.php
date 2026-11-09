<?php
/**
 * Base test case of the Post Chapters test suite.
 *
 * @package Post Chapters
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Test classes use the required Test_PChap_ prefix and tests apply core and third-party hooks.

/**
 * Shared helpers: resets the Post Chapters singleton between requests, creates
 * posts, simulates front-end requests and renders the main loop.
 *
 * The Post Chapters instance is a singleton that keeps per-request state (one
 * PHP process = one request in production). The test suite runs many
 * requests in one process, so the state and the hooks registered by
 * PChap::pchap_post() are reset before every simulated request.
 */
abstract class PChap_UnitTestCase extends WP_UnitTestCase {

	/**
	 * Post Chapters methods hooked once at boot (kept when the request state is reset).
	 *
	 * @var string[]
	 */
	private static $boot_methods = array( 'save_post', 'pchap_pre_handle_404', 'pchap_redirect_canonical', 'pchap_post' );

	/**
	 * Reset the plugin state before each test.
	 */
	public function set_up() {
		parent::set_up();
		$this->reset_multipage();
		pchap_clear_options_cache();
		pchap_rebuild_held_lock( false );
	}

	/**
	 * Reset the plugin state after each test.
	 *
	 * The database transaction is rolled back without firing the option
	 * hooks, so the in-memory options cache must be cleared explicitly.
	 */
	public function tear_down() {
		$this->reset_multipage();
		pchap_clear_options_cache();
		pchap_rebuild_held_lock( false );
		parent::tear_down();
		pchap_clear_options_cache();
	}

	/**
	 * Reset the per-request state of the Post Chapters singleton and remove the
	 * hooks registered by PChap::pchap_post().
	 */
	protected function reset_multipage() {
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
			'rendered_outputs'   => array(),
			'content_runs'       => array(),
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

		// Remove the request hooks (title, content, styles, SEO...) of the previous request,
		// registered by the instance and by its components.
		$owners = array( $mpp, $mpp->seo, $mpp->comments, $mpp->speculation );
		foreach ( array( 'yoast', 'rank_math', 'seopress', 'aioseo', 'seo_framework' ) as $key ) {
			$owners[] = $mpp->seo->get_integration( $key );
		}
		$owners    = array_filter( $owners );
		$to_remove = array();
		foreach ( (array) $wp_filter as $hook_name => $hook ) {
			if ( ! $hook instanceof WP_Hook ) {
				continue;
			}
			foreach ( $hook->callbacks as $priority => $callbacks ) {
				foreach ( $callbacks as $callback ) {
					$function = $callback['function'];
					if ( is_array( $function ) && isset( $function[0], $function[1] ) && in_array( $function[0], $owners, true ) && ! in_array( $function[1], self::$boot_methods, true ) ) {
						$to_remove[] = array( $hook_name, $function, $priority );
					}
				}
			}
		}

		foreach ( $to_remove as $entry ) {
			remove_filter( $entry[0], $entry[1], $entry[2] );
		}
	}

	/**
	 * Create a post. The content is slashed, as wp_insert_post() expects.
	 *
	 * The post is saved by an administrator (unfiltered_html, as on the sites
	 * using the plugin), so kses does not alter the content; the requests
	 * are then made logged out.
	 *
	 * @param string $content Post content (raw, unslashed).
	 * @param array  $args    Optional. More wp_insert_post() arguments.
	 * @return int Post ID.
	 */
	protected function create_post( $content, $args = array() ) {
		static $admin_id = 0;

		if ( ! $admin_id || ! get_userdata( $admin_id ) ) {
			$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		}

		$args = array_merge(
			array(
				'post_title'   => 'Post Chapters test post',
				'post_status'  => 'publish',
				'post_type'    => 'post',
				'post_author'  => $admin_id,
				'post_excerpt' => '',
				'post_content' => wp_slash( $content ),
			),
			$args
		);

		$previous_user = get_current_user_id();
		wp_set_current_user( $admin_id );

		try {
			$post_id = self::factory()->post->create( $args );
		} finally {
			wp_set_current_user( $previous_user );
		}

		return $post_id;
	}

	/**
	 * URL of a subpage, as built by WordPress for the current permalink structure.
	 *
	 * @param int $post_id Post ID.
	 * @param int $page    Subpage (1-based).
	 * @return string
	 */
	protected function subpage_url( $post_id, $page ) {
		$permalink = get_permalink( $post_id );

		if ( $page <= 1 ) {
			return $permalink;
		}

		if ( '' === (string) get_option( 'permalink_structure' ) ) {
			return add_query_arg( 'page', $page, $permalink );
		}

		return trailingslashit( $permalink ) . user_trailingslashit( (string) $page, 'single_paged' );
	}

	/**
	 * Simulate a front-end request for a subpage (WP::main(): parse_request,
	 * query_posts, handle_404 and the "wp" action).
	 *
	 * @param int $post_id Post ID.
	 * @param int $page    Optional. Subpage (1-based). Default 1.
	 */
	protected function go_to_subpage( $post_id, $page = 1 ) {
		$this->reset_multipage();
		$this->go_to( $this->subpage_url( $post_id, $page ) );
	}

	/**
	 * Start the main loop and return the filtered content of the current post,
	 * as a classic theme does with the_content().
	 *
	 * @return string
	 */
	protected function render_main_loop_content() {
		$this->assertTrue( have_posts(), 'The main query has no post.' );
		the_post();

		return (string) get_echo( 'the_content' );
	}

	/**
	 * Request a subpage and return its filtered content.
	 *
	 * @param int $post_id Post ID.
	 * @param int $page    Optional. Subpage (1-based). Default 1.
	 * @return string
	 */
	protected function render_subpage( $post_id, $page = 1 ) {
		$this->go_to_subpage( $post_id, $page );

		return $this->render_main_loop_content();
	}

	/**
	 * Update several Post Chapters options.
	 *
	 * @param array $options Option name => value.
	 */
	protected function set_options( $options ) {
		foreach ( $options as $name => $value ) {
			update_option( $name, $value );
		}
		pchap_clear_options_cache();
	}

	/**
	 * Number of scheduled events of a cron hook.
	 *
	 * @param string $hook Hook name.
	 * @return int
	 */
	protected function count_cron_events( $hook ) {
		$count = 0;
		$crons = _get_cron_array();

		foreach ( (array) $crons as $hooks ) {
			if ( isset( $hooks[ $hook ] ) ) {
				$count += count( $hooks[ $hook ] );
			}
		}

		return $count;
	}

	/**
	 * Display titles of the current multipage post (fallbacks applied).
	 *
	 * @return string[]
	 */
	protected function current_display_titles() {
		$data = pchap()->pchap_data;

		return is_array( $data ) ? array_values( $data ) : array();
	}

	/**
	 * Assert that an element is balanced (as many start tags as end tags) in an HTML string.
	 *
	 * @param string $tag     Tag name.
	 * @param string $html    HTML.
	 * @param string $message Optional. Failure message.
	 */
	protected function assertTagBalanced( $tag, $html, $message = '' ) {
		$open  = preg_match_all( '/<' . preg_quote( $tag, '/' ) . '[\s>]/i', $html );
		$close = preg_match_all( '/<\/' . preg_quote( $tag, '/' ) . '\s*>/i', $html );

		$this->assertSame( $open, $close, ( '' !== $message ? $message . ' ' : '' ) . "Unbalanced <{$tag}> in: {$html}" );
	}
}
