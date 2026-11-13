<?php
/**
 * Paginova comments: show the comments on all, the first or the last subpage.
 *
 * @package Paginova
 * @since 2.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Don't access directly.
}

/**
 * Hides the comments (classic comments template and comment blocks of block
 * themes) on the subpages where the "Display comments on" setting excludes them.
 *
 * Reads the request state of the PChap instance; its hooks are registered by
 * PChap::pchap_post() on valid multipage views only. Available as pchap()->comments.
 *
 * @since 2.1.0
 */
class PChap_Comments {

	/**
	 * The main plugin instance.
	 *
	 * @var PChap
	 */
	private $pchap;

	/**
	 * Constructor.
	 *
	 * @since 2.1.0
	 *
	 * @param PChap $pchap The main plugin instance.
	 */
	public function __construct( PChap $pchap ) {
		$this->pchap = $pchap;
	}

	/**
	 * Register the hooks of the current multipage view.
	 *
	 * @since 2.1.0
	 */
	public function register_hooks() {
		if ( $this->allowed_on_current_page() ) {
			return;
		}

		add_filter( 'comments_template', array( $this, 'filter_comments_template' ) );
		add_filter( 'pre_render_block', array( $this, 'filter_comment_blocks' ), 10, 2 );
	}

	/**
	 * Whether comments are displayed on the current subpage.
	 *
	 * @since 1.7.0 As PChap::comments_allowed_on_current_page().
	 * @since 2.1.0
	 *
	 * @return bool
	 */
	public function allowed_on_current_page() {
		$current_page = $this->pchap->get_current_page();

		switch ( pchap_get_comments_on_page() ) {
			case 'first-page':
				return 1 === $current_page;
			case 'last-page':
				return $current_page === (int) $this->pchap->max_num_pages;
			default:
				return true;
		}
	}

	/**
	 * Replace the comments template of classic themes with an empty file.
	 *
	 * @since 1.0 As PChap::hide_comments().
	 * @since 2.1.0
	 *
	 * @return string
	 */
	public function filter_comments_template() {
		return PCHAP_PLUGIN_DIR . 'index.php';
	}

	/**
	 * Hide the comment blocks of block themes on subpages without comments.
	 *
	 * @since 1.7.0
	 *
	 * @param string|null $pre_render   The pre-rendered content.
	 * @param array       $parsed_block The block being rendered.
	 * @return string|null
	 */
	public function filter_comment_blocks( $pre_render, $parsed_block ) {
		if ( null !== $pre_render || ! is_array( $parsed_block ) || empty( $parsed_block['blockName'] ) ) {
			return $pre_render;
		}

		/**
		 * Filters the blocks hidden on subpages where comments are not displayed.
		 *
		 * @since 1.7.0
		 *
		 * @param string[] $block_names Block names.
		 */
		$block_names = (array) pchap_apply_filters(
			'pchap_comment_block_names',
			array(
				'core/comments',
				'core/post-comments',
				'core/post-comments-form',
				'core/comments-title',
				'core/comments-pagination',
				'core/comment-template',
				'core/comments-query-loop',
			)
		);

		if ( ! in_array( $parsed_block['blockName'], $block_names, true ) ) {
			return $pre_render;
		}

		if ( ! $this->pchap->is_multipage_view() || (int) get_the_ID() !== $this->pchap->post_id ) {
			return $pre_render;
		}

		return '';
	}
}
