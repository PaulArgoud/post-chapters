<?php
/**
 * Speculative loading: the links of the chapters are prefetched before the click.
 *
 * @package Paginova
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Test classes use the required Test_PChap_ prefix and tests apply core and third-party hooks.

/**
 * PChap_Speculation.
 *
 * @group rendering
 */
class Test_PChap_Speculation extends PChap_UnitTestCase {

	/**
	 * Intro, then the chapters "Un" and "Deux".
	 */
	const CONTENT = 'Intro[nextpage title="Un"]Premier[nextpage title="Deux"]Second';

	/**
	 * Skip before WordPress 6.8, which has no speculative loading.
	 */
	public function set_up() {
		parent::set_up();

		if ( ! class_exists( 'WP_Speculation_Rules' ) ) {
			$this->markTestSkipped( 'Speculative loading needs WordPress 6.8.' );
		}

		$this->set_permalink_structure( '/%postname%/' );
	}

	/**
	 * Decoded rules of a mode.
	 *
	 * @param WP_Speculation_Rules $rules Rules.
	 * @param string               $mode  Mode.
	 * @return array Rules by id... as a list.
	 */
	private function rules_of( $rules, $mode ) {
		$data = json_decode( wp_json_encode( $rules ), true );

		return isset( $data[ $mode ] ) ? $data[ $mode ] : array();
	}

	/**
	 * On a chapter, WordPress prints the rule of the chapter links next to its own.
	 */
	public function test_rule_on_a_chapter() {
		$post_id = $this->create_post( self::CONTENT, array( 'post_name' => 'mon-article' ) );
		$this->go_to_subpage( $post_id, 2 );

		$rules = wp_get_speculation_rules();
		$this->assertInstanceOf( 'WP_Speculation_Rules', $rules );
		$this->assertTrue( $rules->has_rule( 'prefetch', 'paginova' ) );

		$list = $this->rules_of( $rules, 'prefetch' );
		$mine = array_values(
			array_filter(
				$list,
				static function ( $rule ) {
					return isset( $rule['where']['selector_matches'] ) && false !== strpos( $rule['where']['selector_matches'], 'mpp-post-navigation' );
				}
			)
		);

		$this->assertCount( 1, $mine );
		$this->assertSame( 'document', $mine[0]['source'] );
		$this->assertSame( 'moderate', $mine[0]['eagerness'] );
		$this->assertSame( '.mpp-post-navigation a, .mpp-toc-container a', $mine[0]['where']['selector_matches'] );
	}

	/**
	 * Other pages of the site get no rule of the plugin.
	 */
	public function test_no_rule_elsewhere() {
		$post_id = $this->create_post( 'Sans chapitre', array( 'post_name' => 'simple' ) );
		$this->go_to_subpage( $post_id, 1 );

		$rules = wp_get_speculation_rules();
		$this->assertInstanceOf( 'WP_Speculation_Rules', $rules );
		$this->assertFalse( $rules->has_rule( 'prefetch', 'paginova' ) );
	}

	/**
	 * The rule can be changed or removed.
	 */
	public function test_rule_filters() {
		$post_id = $this->create_post( self::CONTENT, array( 'post_name' => 'mon-article' ) );

		add_filter(
			'pchap_speculation_rule',
			static function ( $rule ) {
				$rule['eagerness'] = 'conservative';
				return $rule;
			}
		);
		add_filter(
			'pchap_speculation_mode',
			static function () {
				return 'prerender';
			}
		);
		$this->go_to_subpage( $post_id, 2 );

		$rules = wp_get_speculation_rules();
		$this->assertTrue( $rules->has_rule( 'prerender', 'paginova' ) );
		$this->assertFalse( $rules->has_rule( 'prefetch', 'paginova' ) );

		$this->reset_multipage();
		remove_all_filters( 'pchap_speculation_rule' );
		add_filter( 'pchap_speculation_rule', '__return_false' );
		$this->go_to_subpage( $post_id, 2 );

		$this->assertFalse( wp_get_speculation_rules()->has_rule( 'prefetch', 'paginova' ) );
	}

	/**
	 * An invalid mode falls back to prefetch.
	 */
	public function test_invalid_mode() {
		add_filter(
			'pchap_speculation_mode',
			static function () {
				return 'nonsense';
			}
		);

		$post_id = $this->create_post( self::CONTENT, array( 'post_name' => 'mon-article' ) );
		$this->go_to_subpage( $post_id, 2 );

		$this->assertTrue( wp_get_speculation_rules()->has_rule( 'prefetch', 'paginova' ) );
	}

	/**
	 * WordPress turns speculative loading off for logged in users: the rule is never offered then.
	 */
	public function test_logged_in_visitors_have_no_rules() {
		$post_id = $this->create_post( self::CONTENT, array( 'post_name' => 'mon-article' ) );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$this->go_to_subpage( $post_id, 2 );

		$this->assertNull( wp_get_speculation_rules() );
	}

	/**
	 * Anything but speculation rules given to the callback is ignored.
	 */
	public function test_add_rules_ignores_other_values() {
		$post_id = $this->create_post( self::CONTENT, array( 'post_name' => 'mon-article' ) );
		$this->go_to_subpage( $post_id, 2 );

		pchap()->speculation->add_rules( null );
		pchap()->speculation->add_rules( 'x' );

		$this->assertTrue( true, 'No error.' );
	}
}
