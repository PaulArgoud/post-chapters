<?php
/**
 * Post Chapters Admin Class.
 *
 * Admin menus, settings registration and editor integrations
 * (TinyMCE button and Quicktags button).
 *
 * @package Post Chapters
 * @subpackage CoreAdministration
 * @since 1.4
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Loads the Post Chapters admin area.
 *
 * @since 1.4
 */
class PChap_Admin {

	/** Directory *************************************************************/

	/**
	 * Path to the Post Chapters admin directory.
	 *
	 * @since 1.5
	 * @var string $admin_dir
	 */
	public $admin_dir = '';

	/** URLs ******************************************************************/

	/**
	 * URL to the Post Chapters admin directory.
	 *
	 * @since 1.5
	 * @var string $admin_url
	 */
	public $admin_url = '';

	/** Other *****************************************************************/

	/**
	 * Main settings page slug.
	 *
	 * @since 1.5
	 * @var string $settings_page
	 */
	public $settings_page = '';

	/**
	 * Whether the Quicktags button script has already been added.
	 *
	 * @since 1.7.0
	 * @var bool
	 */
	private static $quicktags_added = false;

	/** Methods ***************************************************************/

	/**
	 * The main Post Chapters admin loader.
	 *
	 * @since 1.4
	 */
	public function __construct() {
		$this->setup_globals();
		$this->includes();
		$this->setup_actions();
	}

	/**
	 * Set admin-related globals.
	 *
	 * @since 1.4
	 */
	private function setup_globals() {
		$mpp = pchap();

		// Paths and URLs.
		$this->admin_dir = trailingslashit( $mpp->plugin_dir . 'inc/admin' ); // Admin path.
		$this->admin_url = trailingslashit( $mpp->plugin_url . 'inc/admin' ); // Admin url.

		// Main settings page.
		$this->settings_page = 'options-general.php';
	}

	/**
	 * Include required files.
	 *
	 * @since 1.4
	 */
	private function includes() {
		require_once $this->admin_dir . 'admin-actions.php';
		require_once $this->admin_dir . 'admin-functions.php';
		require_once $this->admin_dir . 'admin-settings.php';
		require_once $this->admin_dir . 'admin-advanced-settings.php';
	}

	/**
	 * Set up the admin hooks, actions, and filters.
	 *
	 * The Subpage block is registered for every request by pchap_register_blocks()
	 * (inc/pchap-blocks.php), whatever the editor in use.
	 *
	 * @since 1.4
	 */
	private function setup_actions() {
		// Add some page specific output to the <head>.
		add_action( 'admin_head', array( $this, 'admin_head' ), 999 );

		// Add menu item to settings menu.
		add_action( 'admin_menu', array( $this, 'admin_menus' ), 5 ); // Priority 5.

		// Add settings.
		add_action( 'pchap_register_admin_settings', array( $this, 'register_admin_settings' ) );

		// Add styles to the admin.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		// Add link to settings page.
		add_filter( 'plugin_action_links', array( $this, 'modify_plugin_action_links' ), 10, 2 );
		add_filter( 'network_admin_plugin_action_links', array( $this, 'modify_plugin_action_links' ), 10, 2 );

		/*
		 * TinyMCE "Subpage" button: classic editor (Classic Editor plugin, post
		 * types without block editor support) and the Classic block.
		 *
		 * These filters only run when WordPress actually loads TinyMCE, which
		 * core already restricts with user_can_richedit() (it honors the user
		 * "rich_editing" option, the browser and the 'user_can_richedit' filter).
		 */
		if ( ! pchap_disable_tinymce_buttons() ) {
			add_filter( 'mce_css', array( $this, 'pchap_mce_css' ) );
			add_filter( 'mce_buttons', array( $this, 'pchap_mce_button' ) );
			add_filter( 'mce_external_plugins', array( $this, 'pchap_mce_external_plugin' ) );
			add_filter( 'wp_mce_translation', array( $this, 'pchap_wp_mce_translation' ), 10, 2 );
			add_filter( 'tiny_mce_before_init', array( $this, 'pchap_classic_block_mce_css' ), 10, 2 );
		}

		// Add the HTML (Text) editor button, printed along with the quicktags script.
		add_action( 'admin_enqueue_scripts', array( $this, 'editor_add_quicktags' ) );
	}

	/**
	 * Register site-admin nav menu elements.
	 *
	 * @since 1.4
	 */
	public function admin_menus() {
		$hooks = array();

		// Add the option pages.
		$hooks[] = add_submenu_page(
			$this->settings_page,
			__( 'Post Chapters Options', 'post-chapters' ),
			__( 'Post Chapters', 'post-chapters' ),
			'manage_options',
			'post-chapters',
			'pchap_admin_settings'
		);

		$hooks[] = add_submenu_page(
			$this->settings_page,
			__( 'Post Chapters Advanced Settings', 'post-chapters' ),
			__( 'Post Chapters Advanced Settings', 'post-chapters' ),
			'manage_options',
			'post-chapters-advanced',
			'pchap_admin_advanced'
		);

		foreach ( $hooks as $hook ) {
			if ( $hook ) {
				add_action( "admin_head-$hook", 'pchap_modify_admin_menu_highlight' );
			}
		}
	}

	/**
	 * Register the settings.
	 *
	 * @since 1.5
	 */
	public function register_admin_settings() {
		/* Settings registration **********************************************/

		/*
		 * Every option is registered in the group of its settings screen and
		 * saved by the Settings API (options.php). Options missing from the
		 * submitted form (unchecked checkboxes) are saved as null and run
		 * through the sanitize callback, which stores 0.
		 */
		foreach ( pchap_get_option_definitions() as $option_name => $definition ) {
			if ( empty( $definition['group'] ) || empty( $definition['type'] ) ) {
				continue;
			}

			if ( 'boolean' === $definition['type'] ) {
				$type    = 'boolean';
				$default = $definition['default'] ? 1 : 0;
			} elseif ( 'priority' === $definition['type'] ) {
				$type    = 'integer';
				$default = (int) $definition['default'];
			} else {
				$type    = 'string';
				$default = (string) $definition['default'];
			}

			register_setting(
				$definition['group'],
				$option_name,
				array(
					'type'              => $type,
					'sanitize_callback' => static function ( $value ) use ( $option_name ) {
						return pchap_sanitize_option( $value, $option_name );
					},
					'default'           => $default,
					'show_in_rest'      => false,
				)
			);
		}

		/* Main Settings ******************************************************/

		// Add the main section.
		add_settings_section( 'pchap_main', __( 'Main Settings', 'post-chapters' ), 'pchap_admin_settings_callback_main_section', 'post-chapters' );

		// Hide default intro title.
		add_settings_field( 'mpp-hide-intro-title', __( 'Intro', 'post-chapters' ), 'pchap_admin_settings_callback_hide_intro_title', 'post-chapters', 'pchap_main', array( 'label_for' => 'hide-intro-title' ) );

		// Display comments on pages (radio group labelled by its fieldset legend).
		add_settings_field( 'mpp-comments-on-page', __( 'Display comments on', 'post-chapters' ), 'pchap_admin_settings_callback_comments_on_page', 'post-chapters', 'pchap_main' );

		// Add the pagination section.
		add_settings_section( 'pchap_pagination', __( 'Pagination', 'post-chapters' ), 'pchap_admin_settings_callback_pagination_section', 'post-chapters' );

		// Navigation type.
		add_settings_field( 'mpp-continue-or-prev-next', __( 'Navigation type', 'post-chapters' ), 'pchap_admin_settings_callback_continue_or_prev_next', 'post-chapters', 'pchap_pagination', array( 'label_for' => 'continue-or-prev-next' ) );

		// Disable the standard WordPress pagination.
		add_settings_field( 'mpp-disable-standard-pagination', __( 'Disable the standard pagination', 'post-chapters' ), 'pchap_admin_settings_callback_disable_standard_pagination', 'post-chapters', 'pchap_pagination', array( 'label_for' => 'disable-standard-pagination' ) );

		// Add the table of contents section.
		add_settings_section( 'pchap_toc', __( 'Table of contents', 'post-chapters' ), 'pchap_admin_settings_callback_toc_section', 'post-chapters' );

		// Display the table of contents only on the first page.
		add_settings_field( 'mpp-toc-only-on-the-first-page', __( 'Only on the first page', 'post-chapters' ), 'pchap_admin_settings_callback_toc_only_on_the_first_page', 'post-chapters', 'pchap_toc', array( 'label_for' => 'toc-only-on-the-first-page' ) );

		// Set the table of contents position.
		add_settings_field( 'mpp-toc-position', __( 'Position', 'post-chapters' ), 'pchap_admin_settings_callback_toc_position', 'post-chapters', 'pchap_toc', array( 'label_for' => 'toc-position' ) );

		// Define row labels (radio group labelled by its fieldset legend).
		add_settings_field( 'mpp-toc-row-labels', __( 'Row labels', 'post-chapters' ), 'pchap_admin_settings_callback_toc_row_labels', 'post-chapters', 'pchap_toc' );

		// Hide the table of contents header.
		add_settings_field( 'mpp-hide-toc-header', __( 'Hide header', 'post-chapters' ), 'pchap_admin_settings_callback_hide_toc_header', 'post-chapters', 'pchap_toc', array( 'label_for' => 'hide-toc-header' ) );

		// Add a link to comments inside the table of contents.
		add_settings_field( 'mpp-comments-toc-link', __( 'Comments link', 'post-chapters' ), 'pchap_admin_settings_callback_comments_toc_link', 'post-chapters', 'pchap_toc', array( 'label_for' => 'comments-toc-link' ) );

		/* Advanced Settings **************************************************/

		// Add the main section.
		add_settings_section( 'pchap_advanced', '', 'pchap_admin_advanced_settings_callback_main_section', 'post-chapters-advanced' );

		// Set the title rewrite priority.
		add_settings_field( '_mpp-rewrite-title-priority', __( 'Rewrite Title Priority', 'post-chapters' ), 'pchap_admin_advanced_callback_rewrite_title_priority', 'post-chapters-advanced', 'pchap_advanced', array( 'label_for' => 'rewrite-title-priority' ) );

		// Set the content rewrite priority.
		add_settings_field( '_mpp-rewrite-content-priority', __( 'Rewrite Content Priority', 'post-chapters' ), 'pchap_admin_advanced_callback_rewrite_content_priority', 'post-chapters-advanced', 'pchap_advanced', array( 'label_for' => 'rewrite-content-priority' ) );

		// Disable the TinyMCE buttons of the classic editor.
		add_settings_field( 'mpp-disable-tinymce-buttons', __( 'Disable TinyMCE Buttons', 'post-chapters' ), 'pchap_admin_advanced_callback_disable_tinymce_buttons', 'post-chapters-advanced', 'pchap_advanced', array( 'label_for' => 'disable-tinymce-buttons' ) );

		// The subpage index rebuild is an action (admin-post.php), see pchap_admin_rebuild_postmeta_section().
	}

	/**
	 * Enqueue scripts and styles.
	 *
	 * @since 0.93
	 *
	 * @uses wp_enqueue_style()
	 * @return void
	 */
	public static function enqueue_scripts() {
		$handle = 'post-chapters-admin';

		// LTR or RTL.
		$file = is_rtl() ? 'admin/css/' . $handle . '-rtl' : 'admin/css/' . $handle;

		// Use minified libraries if SCRIPT_DEBUG is turned off.
		$suffix = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';
		$file   = $file . $suffix . '.css';

		// Enqueue the Post Chapters Plugin styling.
		wp_enqueue_style( $handle, trailingslashit( PCHAP_PLUGIN_URL ) . $file, array(), PCHAP_VERSION, 'screen' );
	}

	/**
	 * Add Settings link to plugins area.
	 *
	 * @since 1.4
	 *
	 * @param array  $links Links array in which we would prepend our link.
	 * @param string $file  Current plugin basename.
	 * @return array Processed links.
	 */
	public function modify_plugin_action_links( $links, $file ) {
		// Return normal links if not Post Chapters.
		if ( plugin_basename( PCHAP__PLUGIN_FILE ) !== $file ) {
			return $links;
		}

		// Add a few links to the existing links array.
		return array_merge(
			array(
				'settings' => '<a href="' . esc_url( add_query_arg( array( 'page' => 'post-chapters' ), pchap_get_admin_url( 'options-general.php' ) ) ) . '">' . esc_html__( 'Settings', 'post-chapters' ) . '</a>',
			),
			$links
		);
	}

	/**
	 * Add some general styling to the admin area.
	 *
	 * @since 1.4
	 */
	public function admin_head() {
		// Hide the advanced settings from the submenu (accessed via tab).
		remove_submenu_page( $this->settings_page, 'post-chapters-advanced' );
	}

	/**
	 * Add the "subpage" button to the HTML (Text) editor toolbar.
	 *
	 * Since 1.7.0 the button is attached as an inline script to the core
	 * 'quicktags' script (hooked on 'admin_enqueue_scripts'), so it is only
	 * printed when quicktags is, right after it and before the editors are
	 * initialized. No global function is defined anymore.
	 *
	 * @since 1.3
	 * @since 1.7.0 Uses wp_add_inline_script() and escapes the title.
	 *
	 * @return void
	 */
	public static function editor_add_quicktags() {
		if ( self::$quicktags_added || ! wp_script_is( 'quicktags', 'registered' ) ) {
			return;
		}

		self::$quicktags_added = true;

		$l10n = array(
			'button' => __( 'chapter', 'post-chapters' ),
			'title'  => __( 'Start a new chapter', 'post-chapters' ),
			'prompt' => __( 'Enter the chapter title', 'post-chapters' ),
		);

		$script = <<<'JS'
( function( window ) {
	'use strict';

	var QTags = window.QTags;
	var l10n = __MPP_QTAGS_L10N__;

	if ( ! QTags || ! QTags.addButton || ! QTags.TagButton ) {
		return;
	}

	// Same encoding as the TinyMCE plugin: & < > are stored as HTML, " [ ] would break the shortcode.
	function escapeShortcodeAttribute( text ) {
		return String( text )
			.replace( /&/g, '&amp;' )
			.replace( /"/g, '&quot;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /\[/g, '&#91;' )
			.replace( /\]/g, '&#93;' );
	}

	QTags.addButton( 'eg_subpage', l10n.button, function( element, canvas, ed ) {
		var title = window.prompt( l10n.prompt );

		if ( 'string' !== typeof title ) {
			return;
		}

		title = title.trim();
		if ( ! title.length ) {
			return;
		}

		this.tagStart = '[nextpage title="' + escapeShortcodeAttribute( title ) + '"]\n\n';
		this.tagEnd = false;

		QTags.TagButton.prototype.callback.call( this, element, canvas, ed );
	}, '', '', l10n.title, 121 );
}( window ) );
JS;

		$script = str_replace( '__MPP_QTAGS_L10N__', wp_json_encode( $l10n ), $script );

		wp_add_inline_script( 'quicktags', $script );
	}

	/**
	 * Add a new TinyMCE css.
	 *
	 * @since 1.3
	 *
	 * @param string $mce_css Comma-separated list of editor stylesheets.
	 * @return string
	 */
	public static function pchap_mce_css( $mce_css ) {
		if ( ! empty( $mce_css ) ) {
			$mce_css .= ',';
		}

		$mce_css .= self::get_mce_css_url();

		return $mce_css;
	}

	/**
	 * Return the URL of the stylesheet loaded inside the TinyMCE content iframe.
	 *
	 * It shows the [nextpage] placeholder image in the Visual editor.
	 *
	 * @since 1.7.0
	 *
	 * @return string
	 */
	private static function get_mce_css_url() {
		// Use minified libraries if SCRIPT_DEBUG is turned off.
		$suffix = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';

		return add_query_arg( 'ver', rawurlencode( PCHAP_VERSION ), PCHAP_PLUGIN_URL . 'inc/admin/tinymce/css/post-chapters' . $suffix . '.css' );
	}

	/**
	 * Load the placeholder stylesheet in the Classic block of the block editor.
	 *
	 * The 'mce_css' filter only runs for the classic editor (wp_editor()). The
	 * Classic block builds its TinyMCE settings in wp_tinymce_inline_scripts()
	 * (editor id 'classic-block') and, since WordPress 6.3, edits its content
	 * in a modal whose iframe only loads the default editor stylesheets: without
	 * this, the [nextpage] placeholder is an invisible image.
	 *
	 * The default stylesheets (_WP_Editors::default_settings()) are kept, as the
	 * Classic block settings override them when they set 'content_css'.
	 *
	 * @since 1.7.0
	 *
	 * @param array  $mce_init  TinyMCE settings.
	 * @param string $editor_id Editor id, 'classic-block' for the Classic block.
	 * @return array
	 */
	public static function pchap_classic_block_mce_css( $mce_init, $editor_id = '' ) {
		if ( 'classic-block' !== $editor_id || ! is_array( $mce_init ) ) {
			return $mce_init;
		}

		if ( isset( $mce_init['content_css'] ) && is_string( $mce_init['content_css'] ) && '' !== trim( $mce_init['content_css'], ' ,' ) ) {
			$content_css = trim( $mce_init['content_css'], ' ,' );
		} else {
			// Same defaults as _WP_Editors::default_settings().
			$suffix      = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';
			$version     = 'ver=' . get_bloginfo( 'version' );
			$content_css = includes_url( "css/dashicons$suffix.css?$version" ) . ',' . includes_url( "js/tinymce/skins/wordpress/wp-content.css?$version" );
		}

		$mce_init['content_css'] = $content_css . ',' . self::get_mce_css_url();

		return $mce_init;
	}

	/**
	 * Add the new subpage TinyMCE button.
	 *
	 * @since 1.3
	 *
	 * @param array $buttons First-row list of buttons.
	 * @return array $buttons
	 */
	public static function pchap_mce_button( $buttons ) {
		if ( ! is_array( $buttons ) ) {
			return $buttons;
		}

		// Insert 'Subpage' button after the 'WP More' button.
		$wp_more_key = array_search( 'wp_more', $buttons, true );
		if ( false !== $wp_more_key ) {
			++$wp_more_key;
		} else {
			$wp_more_key = count( $buttons );
		}
		$buttons_after = array_splice( $buttons, $wp_more_key );

		array_unshift( $buttons_after, 'subpage' );

		return array_merge( $buttons, $buttons_after );
	}

	/**
	 * Add the new TinyMCE plugin.
	 *
	 * @since 1.3
	 *
	 * @param array $plugin_array An array of external TinyMCE plugins.
	 * @return array $plugin_array
	 */
	public static function pchap_mce_external_plugin( $plugin_array ) {
		// Use minified libraries if SCRIPT_DEBUG is turned off.
		$suffix = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';

		$plugin_array['postchapters'] = PCHAP_PLUGIN_URL . 'inc/admin/tinymce/js/plugin' . $suffix . '.js';

		return $plugin_array;
	}

	/**
	 * Add the new TinyMCE plugin locale.
	 *
	 * Used by the classic editor and by the Classic block.
	 *
	 * @since 1.5
	 *
	 * @param array  $mce_translation Key/value pairs of strings.
	 * @param string $mce_locale      Locale.
	 * @return array $mce_translation
	 */
	public static function pchap_wp_mce_translation( $mce_translation, $mce_locale ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Filter signature.
		$pchap_wp_mce_strings = array(
			'Start a new chapter'     => __( 'Start a new chapter', 'post-chapters' ),
			'Enter the chapter title' => __( 'Enter the chapter title', 'post-chapters' ),
		);

		return array_merge( (array) $mce_translation, $pchap_wp_mce_strings );
	}

	/**
	 * Former registration of the Subpage block assets.
	 *
	 * @since 1.3.9
	 * @deprecated 1.7.0 The block is registered from blocks/subpage/block.json
	 *                   by pchap_register_blocks() on 'init'.
	 *
	 * @return void
	 */
	public static function pchap_enqueue_block_editor_assets() {
		_deprecated_function( __METHOD__, '1.7.0', 'pchap_register_blocks()' );
	}
}
