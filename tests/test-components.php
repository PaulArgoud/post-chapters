<?php
/**
 * Components of the main instance (pchap()->seo, pchap()->comments) and the
 * deprecated PChap methods that delegate to them.
 *
 * @package Paginova
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Test classes use the required Test_PChap_ prefix.

/**
 * PChap_SEO and PChap_Comments.
 *
 * @group compat
 */
class Test_PChap_Components extends PChap_UnitTestCase {

	/**
	 * Classic post with three pages.
	 */
	const CONTENT = 'Intro[nextpage title="Un"]Premier[nextpage title="Deux"]Second';

	/**
	 * The components are created with the instance.
	 */
	public function test_components_exist() {
		$this->assertInstanceOf( 'PChap_SEO', pchap()->seo );
		$this->assertInstanceOf( 'PChap_Comments', pchap()->comments );
	}

	/**
	 * The hooks of a multipage view belong to the components.
	 */
	public function test_components_register_the_view_hooks() {
		$post_id = $this->create_post( self::CONTENT );
		$this->set_options( array( 'pchap-comments-on-page' => 'first-page' ) );
		$this->go_to_subpage( $post_id, 2 );

		$this->assertSame( 10, has_action( 'wp_head', array( pchap()->seo, 'print_rel_links' ) ) );
		$this->assertNotFalse( has_filter( 'document_title_parts', array( pchap()->seo, 'filter_document_title_parts' ) ) );
		$this->assertSame( 10, has_filter( 'comments_template', array( pchap()->comments, 'filter_comments_template' ) ) );
		$this->assertSame( 10, has_filter( 'pre_render_block', array( pchap()->comments, 'filter_comment_blocks' ) ) );

		// Not on the instance any more.
		$this->assertFalse( has_action( 'wp_head', array( pchap(), 'pchap_rel_links' ) ) );
		$this->assertFalse( has_filter( 'comments_template', array( pchap(), 'hide_comments' ) ) );
	}

	/**
	 * Deprecated PChap methods and the component method they call, with sample arguments.
	 *
	 * @return array
	 */
	public function data_deprecated_methods() {
		$parsed_block = array( 'blockName' => 'core/comments' );

		return array(
			'pchap_rel_links'                       => array( 'pchap_rel_links', 'seo', 'print_rel_links', array() ),
			'pchap_document_title_parts'            => array(
				'pchap_document_title_parts',
				'seo',
				'filter_document_title_parts',
				array(
					array(
						'title' => 'T',
						'page'  => 'Page 2',
					),
				),
			),
			'filter_wpseo_replacements'             => array( 'filter_wpseo_replacements', 'seo:yoast', 'filter_replacements', array( array( '%%page%%' => 'x' ) ) ),
			'filter_rank_math_replacements'         => array( 'filter_rank_math_replacements', 'seo:rank_math', 'filter_replacements', array( array( '%page%' => 'x' ) ) ),
			'filter_seopress_context_paged'         => array( 'filter_seopress_context_paged', 'seo:seopress', 'filter_context_paged', array( '' ) ),
			'filter_seopress_tag_page_value'        => array( 'filter_seopress_tag_page_value', 'seo:seopress', 'filter_tag_page_value', array( '' ) ),
			'filter_seo_adjacent_rel_url'           => array( 'filter_seo_adjacent_rel_url', 'seo:yoast', 'filter_adjacent_rel_url', array( 'http://example.org/x' ) ),
			'filter_wpseo_canonical'                => array( 'filter_wpseo_canonical', 'seo:yoast', 'filter_canonical', array( 'http://example.org/x' ) ),
			'filter_disable_seo_adjacent_rel_links' => array( 'filter_disable_seo_adjacent_rel_links', 'seo:rank_math', 'filter_disable_adjacent_rel_links', array( false ) ),
			'comments_allowed_on_current_page'      => array( 'comments_allowed_on_current_page', 'comments', 'allowed_on_current_page', array() ),
			'hide_comments'                         => array( 'hide_comments', 'comments', 'filter_comments_template', array() ),
			'filter_comment_blocks'                 => array( 'filter_comment_blocks', 'comments', 'filter_comment_blocks', array( null, $parsed_block ) ),
		);
	}

	/**
	 * The object of a component: a PChap property, or 'seo:key' for an SEO integration.
	 *
	 * @param string $component Component.
	 * @return object
	 */
	private function resolve_component( $component ) {
		if ( 0 === strpos( $component, 'seo:' ) ) {
			return pchap()->seo->get_integration( substr( $component, 4 ) );
		}

		return pchap()->$component;
	}

	/**
	 * A deprecated method warns and returns what the component method returns.
	 *
	 * @dataProvider data_deprecated_methods
	 *
	 * @param string $method    Deprecated PChap method.
	 * @param string $component Component property.
	 * @param string $target    Component method.
	 * @param array  $args      Arguments.
	 */
	public function test_deprecated_method_delegates( $method, $component, $target, $args ) {
		$this->setExpectedDeprecated( 'PChap::' . $method );

		$post_id = $this->create_post( self::CONTENT );
		$this->set_options( array( 'pchap-comments-on-page' => 'first-page' ) );
		$this->go_to_subpage( $post_id, 2 );

		$pchap = pchap();

		ob_start();
		$expected        = call_user_func_array( array( $this->resolve_component( $component ), $target ), $args );
		$expected_output = ob_get_clean();

		ob_start();
		$actual        = call_user_func_array( array( $pchap, $method ), $args );
		$actual_output = ob_get_clean();

		$this->assertSame( $expected, $actual );
		$this->assertSame( $expected_output, $actual_output );
	}
}
