<?php
/**
 * Main Paginova class: bootstrap, request setup and frontend rendering.
 *
 * @package Paginova
 * @since 0.6
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Don't access directly.
}

/**
 * Load the Main Paginova Class
 *
 * @since 0.6
 */
class PChap {

	/** Versions **************************************************************/

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	public $version = '';

	/**
	 * Database version.
	 *
	 * @var int
	 */
	public $db_version = 0;

	/**
	 * Raw database version.
	 *
	 * @var int
	 */
	public $db_version_raw = 0;

	/** Admin *****************************************************************/

	/**
	 * Admin instance.
	 *
	 * @var PChap_Admin|null
	 */
	public $admin = null;

	/** Components ************************************************************/

	/**
	 * SEO component: document title, rel links, SEO plugin integrations.
	 *
	 * @since 2.1.0
	 * @var PChap_SEO|null
	 */
	public $seo = null;

	/**
	 * Comments component: comments on all, the first or the last subpage.
	 *
	 * @since 2.1.0
	 * @var PChap_Comments|null
	 */
	public $comments = null;

	/**
	 * Speculative loading component: the next chapter is prefetched.
	 *
	 * @since 2.1.0
	 * @var PChap_Speculation|null
	 */
	public $speculation = null;

	/** Paths *****************************************************************/

	/**
	 * Main plugin file.
	 *
	 * @var string
	 */
	public $file = '';

	/**
	 * Plugin basename.
	 *
	 * @var string
	 */
	public $basename = '';

	/**
	 * Plugin directory.
	 *
	 * @var string
	 */
	public $plugin_dir = '';

	/**
	 * Plugin URL.
	 *
	 * @var string
	 */
	public $plugin_url = '';

	/** Paginova Data ********************************************************/

	/**
	 * Subpages of the current post: slug => display title (fallbacks applied), or false.
	 *
	 * @var array|false
	 */
	public $pchap_data = false;

	/**
	 * 0-based index of the current subpage, or false.
	 *
	 * @var int|false
	 */
	public $pchap_index = false;

	/**
	 * Raw value of the "page" query var (0 when absent). See $current_page for the 1-based page.
	 *
	 * @var int
	 */
	public $page = 0;

	/**
	 * Display title of the current subpage.
	 *
	 * @var string
	 */
	public $page_title = '';

	/**
	 * Number of subpages.
	 *
	 * @var int
	 */
	public $max_num_pages = 0;

	/**
	 * Slug of the current subpage.
	 *
	 * @var string
	 */
	public $pchap_pagename = '';

	/**
	 * ID of the multipage post being viewed (0 when none).
	 *
	 * @since 1.7.0
	 * @var int
	 */
	public $post_id = 0;

	/**
	 * Current subpage, 1-based (the first page is 1, whether requested as /post/ or /post/1/).
	 *
	 * @since 1.7.0
	 * @var int
	 */
	public $current_page = 1;

	/**
	 * Parsed pages of the current post (see PChap_Parser::parse()).
	 *
	 * @since 1.7.0
	 * @var array
	 */
	public $pages = array();

	/**
	 * Key of the last setup, to make setup() idempotent.
	 *
	 * @var string|null
	 */
	private $setup_key = null;

	/**
	 * Whether the main query is a multipage post.
	 *
	 * @var bool
	 */
	private $is_multipage = false;

	/**
	 * Whether the requested subpage exists.
	 *
	 * @var bool
	 */
	private $is_valid_page = false;

	/**
	 * Whether the frontend filters have been registered.
	 *
	 * @var bool
	 */
	private $hooks_registered = false;

	/**
	 * Re-entrancy guard for pchap_the_content().
	 *
	 * @var bool
	 */
	private $rendering = false;

	/**
	 * Hashes of the contents produced by pchap_the_content() and of the final the_content
	 * outputs it decorated, to never decorate twice.
	 *
	 * @var array
	 */
	private $rendered_hashes = array();

	/**
	 * The final the_content outputs decorated by pchap_the_content(), see close_content_run().
	 *
	 * @var string[]
	 */
	private $rendered_outputs = array();

	/**
	 * The the_content runs in progress (innermost last), see open_content_run().
	 *
	 * Each entry: 'known' (the input already contains a decorated output) and 'decorated'
	 * (pchap_the_content() decorated this run).
	 *
	 * @var array
	 */
	private $content_runs = array();

	/**
	 * Depth of core/post-content blocks being rendered.
	 *
	 * @var int
	 */
	private $post_content_depth = 0;

	/**
	 * Main Paginova Instance.
	 *
	 * @since 1.4
	 */
	public static function instance() {

		// Store the instance locally to avoid private static replication.
		static $instance = null;

		// Only run these methods if they haven't been run previously.
		if ( null === $instance ) {
			$instance = new PChap();
			$instance->constants();
			$instance->setup_globals();
			$instance->includes();
			$instance->setup_actions();
			$instance->frontend_init();
		}

		// Always return the instance.
		return $instance;
	}

	/**
	 * Use PChap::instance().
	 */
	private function __construct() {
		// Do nothing.
	}

	/**
	 * Bootstrap constants.
	 *
	 * @since 1.4
	 */
	private function constants() {

		// Path and URL.
		if ( ! defined( 'PCHAP_PLUGIN_DIR' ) ) {
			define( 'PCHAP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
		}

		if ( ! defined( 'PCHAP_PLUGIN_URL' ) ) {
			define( 'PCHAP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
		}

		// MPP pattern constant (kept for backward compatibility; the parser uses its own patterns).
		if ( ! defined( 'PCHAP_PATTERN' ) ) {
			define( 'PCHAP_PATTERN', '/\[nextpage[^\]]*\]/' );
		}

		// MPP Gutenberg constant (kept for backward compatibility).
		if ( ! defined( 'PCHAP_GUTENBERG_PATTERN' ) ) {
			define( 'PCHAP_GUTENBERG_PATTERN', '/<!-- wp:multipage\/subpage[\s\S]*?<!-- \/wp:multipage\/subpage -->/' );
		}
	}

	/**
	 * Component global variables.
	 *
	 * @since 1.4
	 */
	private function setup_globals() {

		// Versions.

		$this->version    = PCHAP_VERSION;
		$this->db_version = 2000;

		// Paths.

		$this->file       = PCHAP_PLUGIN_DIR . 'paginova.php';
		$this->basename   = basename( PCHAP_PLUGIN_DIR ) . '/paginova.php';
		$this->plugin_dir = PCHAP_PLUGIN_DIR;
		$this->plugin_url = PCHAP_PLUGIN_URL;

		// Paginova data.

		$this->pchap_data  = false;
		$this->pchap_index = false;
	}

	/**
	 * Include required files.
	 *
	 * @since 1.4
	 */
	private function includes() {
		// Setup the versions.
		$this->versions();

		// Load the admin.
		if ( is_admin() ) {
			add_action( 'init', 'pchap_admin' );
		}

		require_once $this->plugin_dir . 'classes/class-pchap-admin.php';
		require_once $this->plugin_dir . 'classes/class-pchap-shortcodes.php';
		require_once $this->plugin_dir . 'classes/seo/class-pchap-seo-integration.php';
		require_once $this->plugin_dir . 'classes/seo/class-pchap-seo-yoast.php';
		require_once $this->plugin_dir . 'classes/seo/class-pchap-seo-rank-math.php';
		require_once $this->plugin_dir . 'classes/seo/class-pchap-seo-seopress.php';
		require_once $this->plugin_dir . 'classes/seo/class-pchap-seo-aioseo.php';
		require_once $this->plugin_dir . 'classes/seo/class-pchap-seo-framework.php';
		require_once $this->plugin_dir . 'classes/class-pchap-seo.php';
		require_once $this->plugin_dir . 'classes/class-pchap-comments.php';
		require_once $this->plugin_dir . 'classes/class-pchap-speculation.php';

		require_once $this->plugin_dir . 'inc/pchap-admin.php';
		require_once $this->plugin_dir . 'inc/pchap-functions.php';
		require_once $this->plugin_dir . 'inc/pchap-options.php';
		require_once $this->plugin_dir . 'inc/pchap-parser.php';
		require_once $this->plugin_dir . 'inc/pchap-shortcodes.php';
		require_once $this->plugin_dir . 'inc/pchap-template.php';
		require_once $this->plugin_dir . 'inc/pchap-update.php';
		require_once $this->plugin_dir . 'inc/pchap-blocks.php';
		require_once $this->plugin_dir . 'inc/pchap-cli.php';

		// Bundled translations: GitHub build only (see inc/pchap-l10n.php).
		if ( file_exists( $this->plugin_dir . 'inc/pchap-l10n.php' ) ) {
			require_once $this->plugin_dir . 'inc/pchap-l10n.php';
		}

		$this->seo         = new PChap_SEO( $this );
		$this->comments    = new PChap_Comments( $this );
		$this->speculation = new PChap_Speculation( $this );
	}

	/**
	 * Set up the hooks, actions, and filters.
	 *
	 * @since 1.5
	 */
	private function setup_actions() {

		// Add actions to plugin activation and deactivation hooks.
		add_action( 'activate_' . $this->basename, 'pchap_activation' );
		add_action( 'deactivate_' . $this->basename, 'pchap_deactivation' );

		// If the plugin is being deactivated, do not add any actions.
		if ( pchap_is_deactivation( $this->basename ) ) {
			return;
		}

		// Add action on save post, moved outside is_admin to preserve working for Rest API (Gutenberg).
		add_action( 'save_post', array( $this, 'save_post' ), 10, 1 );

		// Decide whether the requested subpage exists (valid subpages are not 404).
		add_filter( 'pre_handle_404', array( $this, 'pchap_pre_handle_404' ), 100, 2 );

		// Prevent WordPress from redirecting valid multipage subpages to the base URL.
		add_filter( 'redirect_canonical', array( $this, 'pchap_redirect_canonical' ), 10, 2 );
	}

	/**
	 * Register frontend hooks.
	 *
	 * @since 1.5
	 */
	private function frontend_init() {
		add_action( 'wp', array( $this, 'pchap_post' ) );
	}

	/**
	 * Load the multipage data of the main query (idempotent).
	 *
	 * Parses the live post content (the single source of truth; the `_mpp_data` meta is
	 * only kept for backward compatibility) and computes the current subpage. Used by
	 * pre_handle_404, redirect_canonical and the "wp" action, so the plugin keeps working
	 * when another plugin short-circuits one of them.
	 *
	 * @since 1.7.0
	 *
	 * @param WP_Query|null $query Optional. The main query. Default: the global main query.
	 * @return bool Whether the main query is a multipage post (the requested subpage may still be out of range).
	 */
	public function setup( $query = null ) {
		$main_query = self::get_main_query();

		if ( ! ( $query instanceof WP_Query ) ) {
			$query = $main_query;
		}

		// Only the main query.
		if ( null === $query || $query !== $main_query ) {
			return false;
		}

		$post     = ( isset( $query->post ) && $query->post instanceof WP_Post ) ? $query->post : null;
		$page_var = $query->get( 'page' );
		$raw_page = is_numeric( $page_var ) ? max( 0, (int) $page_var ) : 0;
		$key      = null === $post ? 'none' : $post->ID . ':' . $raw_page . ':' . spl_object_id( $post );

		if ( $key === $this->setup_key ) {
			return $this->is_multipage;
		}

		$this->reset_data();
		$this->setup_key = $key;

		if ( null === $post || is_admin() || ! $query->is_singular() || $query->is_404() || $query->is_feed() || $query->is_embed() || $query->is_trackback() || $query->is_robots() ) {
			return false;
		}

		$parsed = self::parse_content( $post->post_content );

		if ( ! $parsed['is_multipage'] ) {
			return false;
		}

		// A page builder renders the whole post by itself and ignores the pages: the plugin steps aside.
		if ( self::is_builder_post( $post->ID ) ) {
			return false;
		}

		$this->is_multipage  = true;
		$this->post_id       = (int) $post->ID;
		$this->pages         = $parsed['pages'];
		$this->page          = $raw_page;
		$this->current_page  = max( 1, $raw_page );
		$this->max_num_pages = count( $this->pages );
		$this->is_valid_page = $this->current_page <= $this->max_num_pages;

		$this->pchap_data = array();
		foreach ( $this->pages as $index => $page ) {
			$this->pchap_data[ $page['slug'] ] = $this->get_display_title( $page, $index + 1 );
		}

		if ( $this->is_valid_page ) {
			$this->pchap_index    = $this->current_page - 1;
			$this->pchap_pagename = $this->pages[ $this->pchap_index ]['slug'];
			$this->page_title     = $this->pchap_data[ $this->pchap_pagename ];
		}

		return true;
	}

	/**
	 * Parse post content, with an object cache keyed by the content hash.
	 *
	 * Without a persistent object cache this only avoids parsing the same content twice
	 * in a request; with one (Redis, Memcached...), the parse is shared across requests
	 * until the content changes. The key also depends on the parser code itself, so an
	 * updated parser never serves a page cut by a previous build, even one released
	 * under the same version number.
	 *
	 * @since 1.7.0
	 * @since 1.7.1 The cache key depends on the parser code, not only on PCHAP_VERSION.
	 *
	 * @param string $content The post content.
	 * @return array See PChap_Parser::parse().
	 */
	private static function parse_content( $content ) {
		$content = (string) $content;

		// Cheap bail out, before hashing: no plugin marker at all.
		if ( false === strpos( $content, 'wp:multipage/subpage' ) && false === strpos( $content, '[nextpage' ) ) {
			return PChap_Parser::parse( '' );
		}

		$key    = 'parse-' . self::parser_revision() . '-' . md5( $content );
		$parsed = wp_cache_get( $key, 'paginova' );

		if ( is_array( $parsed ) && isset( $parsed['is_multipage'], $parsed['pages'] ) && is_array( $parsed['pages'] ) ) {
			return $parsed;
		}

		$parsed = PChap_Parser::parse( $content );
		wp_cache_set( $key, $parsed, 'paginova', DAY_IN_SECONDS );

		return $parsed;
	}

	/**
	 * Whether a page builder renders the content of a post.
	 *
	 * Elementor, Divi, Beaver Builder and Bricks replace the content of a post, on the front end, by
	 * the output of their own layout. That output holds every chapter: shown on each chapter page, it
	 * would make the pages copies of each other. Chapters need the content of the post itself (the block
	 * editor, the classic editor), so the plugin does not split these posts.
	 *
	 * @since 2.1.0
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public static function is_builder_post( $post_id ) {
		$post_id = (int) $post_id;

		// Post meta of each builder (Elementor is tested; the others are recognized by their well-known meta).
		$beaver     = (string) get_post_meta( $post_id, '_fl_builder_enabled', true );
		$is_builder = 'builder' === get_post_meta( $post_id, '_elementor_edit_mode', true )
			|| 'on' === get_post_meta( $post_id, '_et_pb_use_builder', true )
			|| ( '' !== $beaver && '0' !== $beaver )
			|| 'bricks' === get_post_meta( $post_id, '_bricks_editor_mode', true );

		/**
		 * Filters whether a page builder renders the content of a post, which the plugin then does not split.
		 *
		 * Return false to split such a post anyway, true to leave another builder's posts alone.
		 *
		 * @since 2.1.0
		 *
		 * @param bool $is_builder Whether the post is rendered by Elementor, Divi, Beaver Builder or Bricks.
		 * @param int  $post_id    Post ID.
		 */
		return (bool) pchap_apply_filters( 'pchap_is_builder_post', $is_builder, $post_id );
	}

	/**
	 * Revision of the parser code, for the parse cache key.
	 *
	 * @since 1.7.1
	 *
	 * @return string
	 */
	private static function parser_revision() {
		static $revision = null;

		if ( null === $revision ) {
			$hash     = is_readable( PCHAP_PLUGIN_DIR . 'inc/pchap-parser.php' ) ? md5_file( PCHAP_PLUGIN_DIR . 'inc/pchap-parser.php' ) : false;
			$revision = PCHAP_VERSION . '-' . ( is_string( $hash ) ? substr( $hash, 0, 12 ) : '0' );
		}

		return $revision;
	}

	/**
	 * The main query.
	 *
	 * @since 1.7.0
	 *
	 * @return WP_Query|null
	 */
	private static function get_main_query() {
		if ( isset( $GLOBALS['wp_the_query'] ) && $GLOBALS['wp_the_query'] instanceof WP_Query ) {
			return $GLOBALS['wp_the_query'];
		}

		if ( isset( $GLOBALS['wp_query'] ) && $GLOBALS['wp_query'] instanceof WP_Query ) {
			return $GLOBALS['wp_query'];
		}

		return null;
	}

	/**
	 * Reset the per-request multipage data.
	 *
	 * @since 1.7.0
	 */
	private function reset_data() {
		$this->is_multipage   = false;
		$this->is_valid_page  = false;
		$this->post_id        = 0;
		$this->pages          = array();
		$this->page           = 0;
		$this->current_page   = 1;
		$this->max_num_pages  = 0;
		$this->pchap_data     = false;
		$this->pchap_index    = false;
		$this->pchap_pagename = '';
		$this->page_title     = '';
	}

	/**
	 * Display title of a parsed page (translated fallbacks for the intro and untitled pages).
	 *
	 * @since 1.7.0
	 *
	 * @param array $page   Parsed page.
	 * @param int   $number Page number (1-based).
	 * @return string
	 */
	private function get_display_title( $page, $number ) {
		if ( 'intro' === $page['kind'] ) {
			return __( 'Intro', 'paginova' );
		}

		if ( '' !== $page['title'] ) {
			return $page['title'];
		}

		/* translators: %d: chapter number. */
		$title = sprintf( __( 'Chapter %d', 'paginova' ), $number );

		/**
		 * Filters the fallback title of an untitled subpage.
		 *
		 * @since 1.7.0
		 *
		 * @param string $title  The fallback title.
		 * @param int    $number The subpage number (1-based).
		 * @param array  $page   The parsed page.
		 */
		return (string) pchap_apply_filters( 'pchap_untitled_subpage_title', $title, $number, $page );
	}

	/**
	 * Format of a subpage title, for pchap_get_subpage_title_html() and pchap_get_subpage_title_text().
	 *
	 * Classic [nextpage] titles may be raw text ('text'); block titles are HTML-encoded
	 * RichText strings, and so are the translated fallbacks ('html').
	 *
	 * @since 1.7.0
	 *
	 * @param int $index 0-based subpage index.
	 * @return string 'html' or 'text'.
	 */
	public function get_title_format( $index ) {
		$index = (int) $index;

		if ( isset( $this->pages[ $index ]['kind'], $this->pages[ $index ]['title'] ) && 'shortcode' === $this->pages[ $index ]['kind'] && '' !== $this->pages[ $index ]['title'] ) {
			return 'text';
		}

		return 'html';
	}

	/**
	 * Format of the current subpage title.
	 *
	 * @since 1.7.0
	 * @since 2.1.0 Public.
	 *
	 * @return string 'html' or 'text'.
	 */
	public function get_current_title_format() {
		return false === $this->pchap_index ? 'html' : $this->get_title_format( $this->pchap_index );
	}

	/**
	 * Whether the current request is a valid multipage subpage being rendered by the plugin.
	 *
	 * @since 1.7.0
	 *
	 * @return bool
	 */
	public function is_multipage_view() {
		return $this->hooks_registered && $this->is_multipage && $this->is_valid_page;
	}

	/**
	 * Current subpage, 1-based.
	 *
	 * @since 1.7.0
	 *
	 * @return int
	 */
	public function get_current_page() {
		return max( 1, (int) $this->current_page );
	}

	/**
	 * Set up multipage post data and register content filters.
	 *
	 * Runs on the 'wp' action. Rebuilds the post content as the subpages joined with
	 * core page breaks, then registers title, content, style, and SEO filters.
	 *
	 * @since 1.5
	 */
	public function pchap_post() {
		if ( $this->hooks_registered ) {
			return;
		}

		if ( ! $this->setup() || ! $this->is_valid_page ) {
			return;
		}

		// No need to process.
		if ( is_feed() || is_404() ) {
			return;
		}

		$query = self::get_main_query();
		$post  = null !== $query ? $query->post : null;

		if ( ! ( $post instanceof WP_Post ) || (int) $post->ID !== $this->post_id ) {
			return;
		}

		// Rebuild the content so that WordPress' own splitting yields exactly our pages.
		// $wp_query->post, $wp_query->posts[0] and the global $post are the same object.
		$post->post_content = PChap_Parser::build_paginated_content( $this->pages );

		$this->hooks_registered = true;

		// Same pages for any other WP_Post instance of this post (e.g. a fresh get_post()).
		add_filter( 'content_pagination', array( $this, 'filter_content_pagination' ), 10, 2 );

		// Check whether or not to hide the standard WordPress pagination.
		if ( pchap_disable_standard_pagination() ) {
			add_filter( 'wp_link_pages_args', array( $this, 'hide_standard_pagination' ) );
			add_filter( 'wp_link_pages', array( $this, 'filter_wp_link_pages' ) );
		}

		// Comments on all, the first or the last subpage (classic and block themes).
		$this->comments->register_hooks();

		add_filter( 'the_content', array( $this, 'open_content_run' ), PHP_INT_MIN, 1 );
		add_filter( 'the_content', array( $this, 'pchap_the_content' ), pchap_get_rewrite_content_priority(), 1 );
		add_filter( 'the_content', array( $this, 'close_content_run' ), PHP_INT_MAX, 1 );
		add_filter( 'pre_render_block', array( $this, 'track_post_content_block' ), PHP_INT_MAX, 2 );
		add_filter( 'render_block_core/post-content', array( $this, 'untrack_post_content_block' ), PHP_INT_MAX, 1 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );

		// Document title, rel prev/next links and SEO plugins.
		$this->seo->register_hooks();

		// The links of the chapters are prefetched before the click (WordPress 6.8+).
		$this->speculation->register_hooks();
	}

	/**
	 * Output rel=prev and rel=next links in the <head>.
	 *
	 * @since 1.5.13
	 * @deprecated 2.1.0 Use pchap()->seo->print_rel_links().
	 */
	public function pchap_rel_links() {
		_deprecated_function( __METHOD__, '2.1.0', 'pchap()->seo->print_rel_links()' );

		$this->seo->print_rel_links();
	}

	/**
	 * Store the subpages of a post in the `_mpp_data` meta (backward compatibility).
	 *
	 * @since 0.6
	 *
	 * @param int $post_id Post ID.
	 */
	public function save_post( $post_id ) {
		// Skip revisions and autosaves.
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		$key  = '_mpp_data';
		$post = get_post( $post_id );
		if ( ! $post ) {
			return;
		}

		$_mpp_data = PChap_Parser::multipage_return_array( $post->post_content );

		if ( empty( $_mpp_data ) ) {
			delete_post_meta( $post_id, $key );
			return;
		}

		// Add the post meta. update_metadata() unslashes the value, so slash it
		// first to preserve backslashes in subpage titles.
		update_post_meta( $post_id, $key, wp_slash( $_mpp_data ) );
	}

	/**
	 * Delegates to PChap_Parser.
	 *
	 * @deprecated 1.5.14 Use PChap_Parser::multipage_return_array() directly.
	 *
	 * @param string $content The post content.
	 * @return array|false
	 */
	public static function multipage_return_array( $content ) {
		_deprecated_function( __METHOD__, '1.5.14', 'PChap_Parser::multipage_return_array()' );

		return PChap_Parser::multipage_return_array( $content );
	}

	/**
	 * Hide the standard pagination.
	 *
	 * @since 0.6
	 *
	 * @param array $args The wp_link_pages() arguments.
	 * @return array Modified arguments with echo disabled.
	 */
	public static function hide_standard_pagination( $args ) {
		if ( is_array( $args ) ) {
			$args['echo'] = 0;
		}

		return $args;
	}

	/**
	 * Empty the standard pagination output of the multipage post.
	 *
	 * The core/post-content block appends wp_link_pages( array( 'echo' => 0 ) ), which
	 * hide_standard_pagination() alone cannot hide.
	 *
	 * @since 1.7.0
	 *
	 * @param string $output The wp_link_pages() HTML.
	 * @return string
	 */
	public function filter_wp_link_pages( $output ) {
		if ( $this->is_multipage_view() && (int) get_the_ID() === $this->post_id ) {
			return '';
		}

		return $output;
	}

	/**
	 * Return the subpages of the multipage post to WP_Query::generate_postdata().
	 *
	 * @since 1.7.0
	 *
	 * @param string[] $pages Pages split by WordPress.
	 * @param WP_Post  $post  The post.
	 * @return string[]
	 */
	public function filter_content_pagination( $pages, $post ) {
		if ( $this->is_multipage_view() && $post instanceof WP_Post && (int) $post->ID === $this->post_id ) {
			return array_values( array_column( $this->pages, 'content' ) );
		}

		return $pages;
	}

	/**
	 * Private method to align the active and database versions.
	 *
	 * @since 1.4
	 */
	private function versions() {
		// Get the possible DB versions.
		$versions = array();

		// If we found a multipage option then it's an update from the 1.3 version.
		$versions['1.3'] = false !== get_option( 'multipage' ) ? 999 : null;

		// Remove empty array items.
		$versions             = array_filter( $versions );
		$this->db_version_raw = ! empty( $versions ) ? (int) max( $versions ) : 0;
	}

	/**
	 * Filter the document title for Paginova pages.
	 *
	 * No longer hooked: SEO plugins are handled by dedicated integrations
	 * (see register_seo_integrations()). Kept for backward compatibility.
	 *
	 * @since 0.6
	 * @deprecated 1.7.0
	 *
	 * @see wp_title()
	 *
	 * @param string $title Original page title.
	 * @return string The title, unchanged.
	 */
	public function pchap_the_title( $title ) {
		return $title;
	}

	/**
	 * Replace the "Page N" part of the document title with the subpage title.
	 *
	 * @since 1.4
	 * @deprecated 2.1.0 Use pchap()->seo->filter_document_title_parts().
	 *
	 * @param array $title The WordPress document title parts.
	 * @return array
	 */
	public function pchap_document_title_parts( $title ) {
		_deprecated_function( __METHOD__, '2.1.0', 'pchap()->seo->filter_document_title_parts()' );

		return $this->seo->filter_document_title_parts( $title );
	}

	/**
	 * Yoast SEO: %%page%% replacement.
	 *
	 * @since 1.7.0
	 * @deprecated 2.1.0 Use pchap()->seo->get_integration( 'yoast' )->filter_replacements().
	 *
	 * @param array $replacements Replacement values keyed by %%var%%.
	 * @param mixed $args         The object the replacements are computed for.
	 * @return array
	 */
	public function filter_wpseo_replacements( $replacements, $args = null ) {
		_deprecated_function( __METHOD__, '2.1.0', "pchap()->seo->get_integration( 'yoast' )->filter_replacements()" );

		return $this->seo->get_integration( 'yoast' )->filter_replacements( $replacements, $args );
	}

	/**
	 * Rank Math: %page% replacement.
	 *
	 * @since 1.7.0
	 * @deprecated 2.1.0 Use pchap()->seo->get_integration( 'rank_math' )->filter_replacements().
	 *
	 * @param array $replacements Replacement values keyed by %var%.
	 * @param mixed $args         The object the replacements are computed for.
	 * @return array
	 */
	public function filter_rank_math_replacements( $replacements, $args = null ) {
		_deprecated_function( __METHOD__, '2.1.0', "pchap()->seo->get_integration( 'rank_math' )->filter_replacements()" );

		return $this->seo->get_integration( 'rank_math' )->filter_replacements( $replacements, $args );
	}

	/**
	 * SEOPress (legacy dynamic variables): %%page%% on singular subpages.
	 *
	 * @since 1.7.0
	 * @deprecated 2.1.0 Use pchap()->seo->get_integration( 'seopress' )->filter_context_paged().
	 *
	 * @param string $value " {sep} Page X of Y" or empty.
	 * @return string
	 */
	public function filter_seopress_context_paged( $value ) {
		_deprecated_function( __METHOD__, '2.1.0', "pchap()->seo->get_integration( 'seopress' )->filter_context_paged()" );

		return $this->seo->get_integration( 'seopress' )->filter_context_paged( $value );
	}

	/**
	 * SEOPress (Tags engine): %%page%% on singular subpages.
	 *
	 * @since 1.7.0
	 * @deprecated 2.1.0 Use pchap()->seo->get_integration( 'seopress' )->filter_tag_page_value().
	 *
	 * @param string $value "Page X of Y" or empty.
	 * @return string
	 */
	public function filter_seopress_tag_page_value( $value ) {
		_deprecated_function( __METHOD__, '2.1.0', "pchap()->seo->get_integration( 'seopress' )->filter_tag_page_value()" );

		return $this->seo->get_integration( 'seopress' )->filter_tag_page_value( $value );
	}

	/**
	 * Yoast SEO: drop its rel prev/next on the multipage post.
	 *
	 * @since 1.7.0
	 * @deprecated 2.1.0 Use pchap()->seo->get_integration( 'yoast' )->filter_adjacent_rel_url().
	 *
	 * @param string $url The adjacent URL.
	 * @return string
	 */
	public function filter_seo_adjacent_rel_url( $url ) {
		_deprecated_function( __METHOD__, '2.1.0', "pchap()->seo->get_integration( 'yoast' )->filter_adjacent_rel_url()" );

		return $this->seo->get_integration( 'yoast' )->filter_adjacent_rel_url( $url );
	}

	/**
	 * Yoast SEO: canonical URL of subpages >= 2.
	 *
	 * @since 1.7.0
	 * @deprecated 2.1.0 Use pchap()->seo->get_integration( 'yoast' )->filter_canonical().
	 *
	 * @param string $canonical    The canonical URL.
	 * @param mixed  $presentation Optional. The Yoast indexable presentation.
	 * @return string
	 */
	public function filter_wpseo_canonical( $canonical, $presentation = null ) {
		_deprecated_function( __METHOD__, '2.1.0', "pchap()->seo->get_integration( 'yoast' )->filter_canonical()" );

		return $this->seo->get_integration( 'yoast' )->filter_canonical( $canonical, $presentation );
	}

	/**
	 * Rank Math: disable its rel prev/next on the multipage post.
	 *
	 * @since 1.7.0
	 * @deprecated 2.1.0 Use pchap()->seo->get_integration( 'rank_math' )->filter_disable_adjacent_rel_links().
	 *
	 * @param bool $disable Whether to disable the links.
	 * @return bool
	 */
	public function filter_disable_seo_adjacent_rel_links( $disable ) {
		_deprecated_function( __METHOD__, '2.1.0', "pchap()->seo->get_integration( 'rank_math' )->filter_disable_adjacent_rel_links()" );

		return $this->seo->get_integration( 'rank_math' )->filter_disable_adjacent_rel_links( $disable );
	}

	/**
	 * Whether the_content should be decorated (title, TOC, navigation).
	 *
	 * Only for the multipage post itself, rendered by the main query: never for related
	 * posts, Query Loop blocks, widgets, excerpts or SEO descriptions.
	 *
	 * @since 1.7.0
	 *
	 * @return bool
	 */
	private function should_filter_content() {
		if ( ! $this->is_multipage_view() || $this->rendering ) {
			return false;
		}

		if ( is_admin() || is_feed() || ! is_singular() ) {
			return false;
		}

		// Excerpts (wp_trim_excerpt() applies the_content) and <head> builders.
		if ( doing_filter( 'get_the_excerpt' ) || doing_filter( 'the_excerpt' ) || doing_action( 'wp_head' ) ) {
			return false;
		}

		if ( (int) get_the_ID() !== $this->post_id ) {
			return false;
		}

		if ( ! is_main_query() ) {
			return false;
		}

		// Classic themes: the main loop. Block themes: get_the_block_template_html() runs the
		// main loop too, except for templates injected by plugins, hence the post-content check.
		if ( ! in_the_loop() && $this->post_content_depth < 1 ) {
			return false;
		}

		if ( post_password_required( $this->post_id ) ) {
			return false;
		}

		// A the_content run nested in another one (shortcodes filtering their inner content:
		// boxes, tabs, accordions): only the outermost run, around the whole content.
		if ( self::content_filter_depth() > 1 ) {
			return false;
		}

		// Last, as the most expensive check: wp_trim_excerpt() called directly by a theme or plugin.
		return ! self::is_excerpt_content_run();
	}

	/**
	 * Whether the current the_content run was started by wp_trim_excerpt().
	 *
	 * Called directly (not through the 'get_the_excerpt' filter), wp_trim_excerpt() applies
	 * the_content without any excerpt hook running: only the call stack tells it apart.
	 *
	 * @since 1.7.0
	 *
	 * @return bool
	 */
	private static function is_excerpt_content_run() {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace -- Detects the caller of apply_filters( 'the_content' ); no output.
		$frames = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 12 );
		$count  = count( $frames );

		for ( $i = 0; $i < $count; $i++ ) {
			// The innermost global apply_filters() call: the current the_content run.
			if ( isset( $frames[ $i ]['function'] ) && 'apply_filters' === $frames[ $i ]['function'] && ! isset( $frames[ $i ]['class'] ) ) {
				return isset( $frames[ $i + 1 ]['function'] ) && 'wp_trim_excerpt' === $frames[ $i + 1 ]['function'] && ! isset( $frames[ $i + 1 ]['class'] );
			}
		}

		return false;
	}

	/**
	 * Start of a the_content run (earliest priority).
	 *
	 * Remembers whether the input is a final the_content output that was already
	 * decorated: themes and page builders sometimes apply the_content to rendered HTML,
	 * which the filters running before pchap_the_content() (wpautop, wptexturize...) alter
	 * again, so only the run's original input can be recognized.
	 *
	 * @since 1.7.0
	 *
	 * @param string $content The content.
	 * @return string Unchanged.
	 */
	public function open_content_run( $content ) {
		// Drop the runs left open by an interrupted the_content (exception, removed filter).
		$depth = self::content_filter_depth();
		if ( count( $this->content_runs ) >= $depth ) {
			$this->content_runs = array_slice( $this->content_runs, 0, max( 0, $depth - 1 ) );
		}

		$this->content_runs[] = array(
			'known'     => $this->contains_rendered_output( $content ),
			'decorated' => false,
		);

		return $content;
	}

	/**
	 * Whether a content contains a final the_content output already decorated, e.g. the
	 * core/post-content block output (the decorated content in its wrapper) filtered again.
	 *
	 * @since 1.7.0
	 *
	 * @param mixed $content The content.
	 * @return bool
	 */
	private function contains_rendered_output( $content ) {
		if ( ! is_string( $content ) || '' === $content ) {
			return false;
		}

		if ( isset( $this->rendered_hashes[ md5( $content ) ] ) ) {
			return true;
		}

		foreach ( $this->rendered_outputs as $output ) {
			if ( false !== strpos( $content, $output ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Number of nested the_content runs in progress (1 in a the_content run not nested in another).
	 *
	 * @since 1.7.0
	 *
	 * @global string[] $wp_current_filter Stack of the current filters.
	 *
	 * @return int
	 */
	private static function content_filter_depth() {
		global $wp_current_filter;

		return is_array( $wp_current_filter ) ? count( array_keys( $wp_current_filter, 'the_content', true ) ) : 0;
	}

	/**
	 * End of a the_content run (latest priority): remembers the decorated final output.
	 *
	 * @since 1.7.0
	 *
	 * @param string $content The final content.
	 * @return string Unchanged.
	 */
	public function close_content_run( $content ) {
		$run = array_pop( $this->content_runs );

		if ( is_array( $run ) && $run['decorated'] && is_string( $content ) && '' !== $content ) {
			$this->rendered_hashes[ md5( $content ) ] = true;
			if ( ! in_array( $content, $this->rendered_outputs, true ) ) {
				$this->rendered_outputs[] = $content;
			}
		}

		return $content;
	}

	/**
	 * Track the start of a core/post-content block rendering.
	 *
	 * @since 1.7.0
	 *
	 * @param string|null $pre_render   The pre-rendered content.
	 * @param array       $parsed_block The block being rendered.
	 * @return string|null Unchanged.
	 */
	public function track_post_content_block( $pre_render, $parsed_block ) {
		if ( null === $pre_render && is_array( $parsed_block ) && isset( $parsed_block['blockName'] ) && 'core/post-content' === $parsed_block['blockName'] ) {
			++$this->post_content_depth;
		}

		return $pre_render;
	}

	/**
	 * Track the end of a core/post-content block rendering.
	 *
	 * @since 1.7.0
	 *
	 * @param string $block_content The block content.
	 * @return string Unchanged.
	 */
	public function untrack_post_content_block( $block_content ) {
		if ( $this->post_content_depth > 0 ) {
			--$this->post_content_depth;
		}

		return $block_content;
	}

	/**
	 * Filter the WordPress post content for Paginova pages.
	 *
	 * @since 0.6
	 *
	 * @param string $content Original page content.
	 * @return string the content enhanced by Paginova.
	 */
	public function pchap_the_content( $content ) {
		// Table of contents should not be the only content in the post.
		if ( ! is_string( $content ) || '' === $content ) {
			return $content;
		}

		// The placeholder of a titled subpage without body is never printed.
		$content = self::strip_empty_page_placeholder( $content );

		if ( ! $this->should_filter_content() ) {
			return $content;
		}

		// Never decorate our own output twice (see open_content_run()).
		$run = empty( $this->content_runs ) ? null : count( $this->content_runs ) - 1;
		if ( ( null !== $run && $this->content_runs[ $run ]['known'] ) || isset( $this->rendered_hashes[ md5( $content ) ] ) ) {
			return $content;
		}

		$this->rendering = true;
		try {
			$output = $this->render_content( $content );
		} finally {
			$this->rendering = false;
		}

		$this->rendered_hashes[ md5( $output ) ] = true;
		if ( null !== $run ) {
			$this->content_runs[ $run ]['decorated'] = true;
		}

		return $output;
	}

	/**
	 * Remove the empty-page placeholder (see PChap_Parser::EMPTY_PAGE_PLACEHOLDER).
	 *
	 * The placeholder is an HTML comment that keeps a titled subpage without body alive
	 * through WordPress' splitting; it may have been wrapped in a paragraph by wpautop().
	 *
	 * @since 1.7.0
	 *
	 * @param string $content The content.
	 * @return string
	 */
	private static function strip_empty_page_placeholder( $content ) {
		if ( false === strpos( $content, PChap_Parser::EMPTY_PAGE_PLACEHOLDER ) ) {
			return $content;
		}

		$stripped = preg_replace( '#(?:<p>\s*)?' . preg_quote( PChap_Parser::EMPTY_PAGE_PLACEHOLDER, '#' ) . '(?:\s*</p>)?\s*#', '', $content );

		return null === $stripped ? str_replace( PChap_Parser::EMPTY_PAGE_PLACEHOLDER, '', $content ) : $stripped;
	}

	/**
	 * Assemble page title + table of contents + content + navigation.
	 *
	 * @since 1.7.0
	 *
	 * @param string $content The page content.
	 * @return string
	 */
	private function render_content( $content ) {
		$current_page = $this->get_current_page();

		/**
		 * Filters the subpage title HTML template.
		 *
		 * @since 1.4
		 *
		 * @param string $template The template, `%s` is replaced by the title HTML.
		 */
		$page_title_template = pchap_apply_filters( 'pchap_page_title_template', '<h2>%s</h2>' );

		// The template is filterable: the title HTML is escaped as post content.
		$is_intro   = 1 === $current_page && ! empty( $this->pages ) && 'intro' === $this->pages[0]['kind'];
		$page_title = pchap_hide_intro_title() && $is_intro ? '' : wp_kses_post( sprintf( (string) $page_title_template, pchap_get_subpage_title_html( $this->page_title, $this->get_current_title_format() ) ) );

		$toc_labels   = pchap_get_toc_row_labels();
		$toc_position = pchap_get_toc_position();

		switch ( $toc_labels ) {
			case 'page':
				$toc_row_separator = ': ';
				/* translators: %: chapter number (literal percent sign, replaced by the number). */
				$toc_row_pagelink = esc_html__( 'Chapter %', 'paginova' );
				break;
			case 'hidden':
				$toc_row_separator = '';
				$toc_row_pagelink  = '';
				break;
			default:
				$toc_row_separator = '. ';
				$toc_row_pagelink  = '%';
				break;
		}

		$continue_or_prev_next = pchap_get_continue_or_prev_next();
		if ( 'hidden' !== $continue_or_prev_next ) {
			$navigation = pchap_link_pages(
				$this,
				array(
					'before'                => '<nav class="mpp-post-navigation ' . sanitize_html_class( $continue_or_prev_next ) . '" aria-label="' . esc_attr__( 'Chapter navigation', 'paginova' ) . '"><div class="nav-links">',
					'after'                 => '</div></nav>',
					'continue_or_prev_next' => $continue_or_prev_next,
				)
			);
		} else {
			$navigation = '';
		}

		// Get comments link.
		$comments_link = '';
		if ( pchap_comments_toc_link() ) {
			switch ( pchap_get_comments_on_page() ) {
				case 'all':
					$comments_link = '<a href="#comments">';
					break;
				case 'first-page':
					$comments_link = pchap_link_page( 1, '', 'comments' );
					break;
				case 'last-page':
					$comments_link = pchap_link_page( $this->max_num_pages, '', 'comments' );
					break;
				default:
					$comments_link = '';
					break;
			}
		}

		$toc = pchap_toc(
			$this,
			array(
				'hide_header' => pchap_hide_toc_header(),
				'comments'    => $comments_link,
				'position'    => $toc_position,
				'before'      => '<nav class="mpp-toc toc" aria-label="' . esc_attr__( 'Table of contents', 'paginova' ) . '"><ul>',
				'after'       => '</ul></nav>',
				'separator'   => $toc_row_separator,
				'pagelink'    => $toc_row_pagelink,
			)
		);

		// Add the title.
		$output = $page_title;

		// Add the table of contents.
		$show_toc = 'hidden' !== $toc_position && ! ( pchap_toc_only_on_the_first_page() && $current_page > 1 );

		if ( ! $show_toc ) {
			$output .= $content;
		} elseif ( 'bottom' === $toc_position ) {
			$output .= $content . $toc;
		} else {
			$output .= $toc . $content;
		}

		/**
		 * Filters the multipage post content.
		 *
		 * @since 1.5.4
		 *
		 * @param string $output     The enhanced content.
		 * @param string $page_title The subpage title HTML.
		 * @param string $toc        The table of contents HTML.
		 * @param string $content    The original content.
		 */
		// The parts built by the plugin (title, table of contents, navigation) are escaped
		// with wp_kses_post(). The post content itself is never escaped, as in core:
		// kses would strip the embeds, iframes and scripts that authors with the
		// unfiltered_html capability are allowed to publish.
		$output = pchap_apply_filters( 'pchap_the_content', $output, $page_title, $toc, $content );

		// Add the page navigation.
		$output .= $navigation;

		return $output;
	}

	/**
	 * Whether comments are displayed on the current subpage.
	 *
	 * @since 1.7.0
	 * @deprecated 2.1.0 Use pchap()->comments->allowed_on_current_page().
	 *
	 * @return bool
	 */
	public function comments_allowed_on_current_page() {
		_deprecated_function( __METHOD__, '2.1.0', 'pchap()->comments->allowed_on_current_page()' );

		return $this->comments->allowed_on_current_page();
	}

	/**
	 * Replace the comments template with an empty file.
	 *
	 * @since 1.0
	 * @deprecated 2.1.0 Use pchap()->comments->filter_comments_template().
	 *
	 * @return string
	 */
	public function hide_comments() {
		_deprecated_function( __METHOD__, '2.1.0', 'pchap()->comments->filter_comments_template()' );

		return $this->comments->filter_comments_template();
	}

	/**
	 * Hide the comment blocks of block themes on subpages without comments.
	 *
	 * @since 1.7.0
	 * @deprecated 2.1.0 Use pchap()->comments->filter_comment_blocks().
	 *
	 * @param string|null $pre_render   The pre-rendered content.
	 * @param array       $parsed_block The block being rendered.
	 * @return string|null
	 */
	public function filter_comment_blocks( $pre_render, $parsed_block ) {
		_deprecated_function( __METHOD__, '2.1.0', 'pchap()->comments->filter_comment_blocks()' );

		return $this->comments->filter_comment_blocks( $pre_render, $parsed_block );
	}

	/**
	 * Styles applied to public-facing pages
	 *
	 * A theme can override the stylesheet with css/paginova[-rtl][.min].css
	 * (child theme first, then parent theme). In each theme, the
	 * css/post-chapters[-rtl][.min].css name of
	 * Post Chapters 2.x and the css/multipage[-rtl][.min].css name of Multipage
	 * are still honoured, in this order, after the new one.
	 *
	 * @since 0.6
	 * @since 2.0.0 Looks for css/post-chapters*.css first, css/multipage*.css as a fallback.
	 * @since 2.2.0 Looks for css/paginova*.css first, then the former names.
	 */
	public function enqueue_styles() {
		// Use minified libraries if SCRIPT_DEBUG is turned off.
		$suffix = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';
		$rtl    = is_rtl() ? '-rtl' : '';

		$file     = 'css/paginova' . $rtl . $suffix . '.css';
		$location = '';
		$handle   = '';
		$path     = '';

		$themes = array(
			array( get_stylesheet_directory(), get_stylesheet_directory_uri(), get_stylesheet() ),
			array( get_template_directory(), get_template_directory_uri(), get_template() ),
		);
		foreach ( $themes as $theme ) {
			foreach ( array( 'paginova', 'post-chapters', 'multipage' ) as $basename ) {
				$candidate = 'css/' . $basename . $rtl . $suffix . '.css';
				if ( file_exists( trailingslashit( $theme[0] ) . $candidate ) ) {
					$file     = $candidate;
					$location = trailingslashit( $theme[1] );
					$handle   = $theme[2] . '-paginova';
					$path     = trailingslashit( $theme[0] ) . $candidate;
					break 2;
				}
			}
		}

		if ( '' === $location ) {
			// Paginova default style.
			$location = trailingslashit( PCHAP_PLUGIN_URL );
			$handle   = 'paginova';

			// Never point to a missing file.
			if ( ! file_exists( PCHAP_PLUGIN_DIR . $file ) ) {
				$file = 'css/paginova' . $suffix . '.css';
			}

			$path = PCHAP_PLUGIN_DIR . $file;
		}

		// Enqueue the Paginova styling.
		wp_enqueue_style( $handle, $location . $file, array(), $this->version, 'screen' );

		// Small stylesheet: with its path, WordPress prints it inline (no extra request),
		// within the limit of the styles_inline_size_limit filter.
		wp_style_add_data( $handle, 'path', $path );
	}

	/**
	 * Prevent WordPress canonical redirect from redirecting valid multipage subpages.
	 *
	 * Valid subpages >= 2 are never redirected elsewhere (core would otherwise count the
	 * <!--nextpage--> markers of the raw content, which a block or shortcode post lacks).
	 * A redirect to the canonical URL of the same subpage is kept, and so is the core
	 * redirect of /post/1/ to /post/.
	 *
	 * @since 1.5.13
	 *
	 * @param string $redirect_url  The redirect URL.
	 * @param string $requested_url The requested URL.
	 * @return string|false The redirect URL, or false to cancel the redirect.
	 */
	public function pchap_redirect_canonical( $redirect_url, $requested_url ) {
		unset( $requested_url );

		if ( ! $redirect_url || ! $this->setup() || ! $this->is_valid_page || $this->get_current_page() < 2 ) {
			return $redirect_url;
		}

		$expected = pchap_link_page_url( $this->get_current_page(), '', $this->post_id );

		if ( '' !== $expected && self::is_same_page_url( $redirect_url, $expected ) ) {
			return $redirect_url;
		}

		return false;
	}

	/**
	 * Whether two URLs point to the same host, path and "page" query var.
	 *
	 * @since 1.7.0
	 *
	 * @param string $a URL.
	 * @param string $b URL.
	 * @return bool
	 */
	private static function is_same_page_url( $a, $b ) {
		$parts_a = wp_parse_url( (string) $a );
		$parts_b = wp_parse_url( (string) $b );

		if ( ! is_array( $parts_a ) || ! is_array( $parts_b ) ) {
			return false;
		}

		$normalize = static function ( $parts ) {
			$query = array();
			if ( isset( $parts['query'] ) ) {
				wp_parse_str( $parts['query'], $query );
			}

			return array(
				isset( $parts['host'] ) ? strtolower( $parts['host'] ) : '',
				isset( $parts['path'] ) ? untrailingslashit( $parts['path'] ) : '',
				isset( $query['page'] ) && is_scalar( $query['page'] ) ? (int) $query['page'] : 0,
			);
		};

		return $normalize( $parts_a ) === $normalize( $parts_b );
	}

	/**
	 * Filters whether to short-circuit default header status handling.
	 *
	 * Valid subpages return 200, out-of-range subpages return 404. Also loads the
	 * multipage data, even when another plugin already short-circuited the filter.
	 *
	 * @since 1.5
	 *
	 * @param bool     $preempt  Whether to short-circuit default header status handling. Default false.
	 * @param WP_Query $wp_query WordPress Query object.
	 * @return bool Whether to short-circuit default header status handling.
	 */
	public function pchap_pre_handle_404( $preempt, $wp_query ) {
		if ( ! $this->setup( $wp_query ) ) {
			return $preempt;
		}

		// Another plugin took over the status handling.
		if ( false !== $preempt ) {
			return $preempt;
		}

		// Valid subpage: skip WP's check, which counts <!--nextpage--> in the raw content.
		if ( $this->is_valid_page ) {
			return true;
		}

		// Out-of-range subpage.
		if ( $wp_query instanceof WP_Query ) {
			$wp_query->set_404();
			status_header( 404 );
			nocache_headers();
			return true;
		}

		return $preempt;
	}
}
