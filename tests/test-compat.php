<?php
/**
 * Multipage compatibility tests: deprecated mpp_* hooks and the coexistence guard.
 *
 * @package Post Chapters
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Test classes use the required Test_PChap_ prefix and tests hook the deprecated mpp_* names on purpose.

/**
 * Compatibility with Multipage 1.x.
 *
 * @group compat
 */
class Test_PChap_Compat extends PChap_UnitTestCase {

	/**
	 * Restore the active plugins after each test.
	 */
	public function tear_down() {
		delete_option( 'active_plugins' );
		parent::tear_down();
	}

	/**
	 * Every pchap_* hook maps to its mpp_* name, other hooks to nothing.
	 */
	public function test_legacy_hook_name() {
		$this->assertSame( 'mpp_toc', _pchap_get_legacy_hook_name( 'pchap_toc' ) );
		$this->assertSame( 'mpp_get_toc_position', _pchap_get_legacy_hook_name( 'pchap_get_toc_position' ) );
		$this->assertSame( '', _pchap_get_legacy_hook_name( 'the_content' ) );
		$this->assertSame( '', _pchap_get_legacy_hook_name( 'my_pchap_toc' ) );
	}

	/**
	 * A filter without legacy callbacks only runs the pchap_* callbacks (no deprecation notice).
	 */
	public function test_filter_without_legacy_callbacks() {
		add_filter(
			'pchap_toc',
			static function ( $html ) {
				return $html . '-new';
			}
		);

		$this->assertSame( 'toc-new', pchap_apply_filters( 'pchap_toc', 'toc', array() ) );
	}

	/**
	 * A callback hooked to the mpp_* name still runs, after the pchap_* ones, with the same arguments.
	 */
	public function test_legacy_filter_still_runs() {
		$this->setExpectedDeprecated( 'mpp_toc' );

		$received = null;
		add_filter(
			'pchap_toc',
			static function ( $html ) {
				return $html . '-new';
			}
		);
		add_filter(
			'mpp_toc',
			static function ( $html, $args ) use ( &$received ) {
				$received = $args;
				return $html . '-legacy';
			},
			10,
			2
		);

		$this->assertSame( 'toc-new-legacy', pchap_apply_filters( 'pchap_toc', 'toc', array( 'echo' => 0 ) ) );
		$this->assertSame( array( 'echo' => 0 ), $received );
	}

	/**
	 * A legacy option getter filter still changes the option value.
	 */
	public function test_legacy_option_filter_still_runs() {
		$this->setExpectedDeprecated( 'mpp_get_toc_position' );

		add_filter(
			'mpp_get_toc_position',
			static function () {
				return 'bottom';
			}
		);

		$this->assertSame( 'bottom', pchap_get_toc_position() );
	}

	/**
	 * A callback hooked to an mpp_* action still runs, once, with the same arguments.
	 */
	public function test_legacy_action_still_runs() {
		$this->setExpectedDeprecated( 'mpp_on_update' );

		$calls = array();
		add_action(
			'mpp_on_update',
			static function ( ...$args ) use ( &$calls ) {
				$calls[] = $args;
			},
			10,
			2
		);

		pchap_do_action( 'pchap_on_update', 'a', 'b' );

		$this->assertSame( array( array( 'a', 'b' ) ), $calls );
	}

	/**
	 * A legacy filter of the rendering still applies to the rendered subpage.
	 */
	public function test_legacy_rendering_filter_still_applies() {
		$this->setExpectedDeprecated( 'mpp_page_title_template' );

		add_filter(
			'mpp_page_title_template',
			static function () {
				return '<h3 class="legacy">%s</h3>';
			}
		);

		$post_id = $this->create_post( "Intro\n\n[nextpage title=\"Deux\"]\n\nTwo" );
		$html    = $this->render_subpage( $post_id, 2 );

		$this->assertStringContainsString( '<h3 class="legacy">Deux</h3>', $html );
	}

	/**
	 * The guard is off when Multipage is not active.
	 */
	public function test_guard_off_without_multipage() {
		update_option( 'active_plugins', array( 'post-chapters/post-chapters.php', 'akismet/akismet.php' ) );

		$this->assertFalse( pchap_is_multipage_active() );
	}

	/**
	 * The guard detects Multipage, whatever the name of its folder.
	 */
	public function test_guard_detects_multipage() {
		foreach ( array( 'sgr-nextpage-titles/sgr-nextpage-titles.php', 'sgr-nextpage-titles-main/sgr-nextpage-titles.php' ) as $basename ) {
			update_option( 'active_plugins', array( 'post-chapters/post-chapters.php', $basename ) );
			$this->assertTrue( pchap_is_multipage_active(), $basename );
		}
	}

	/**
	 * The guard notice is shown to the users who can deactivate plugins only.
	 */
	public function test_guard_notice() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		ob_start();
		pchap_multipage_active_notice();
		$this->assertSame( '', ob_get_clean() );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		ob_start();
		pchap_multipage_active_notice();
		$html = ob_get_clean();

		$this->assertStringContainsString( 'notice-warning', $html );
		$this->assertStringContainsString( 'Deactivate Multipage', $html );
		$this->assertStringContainsString( 'delete its folder from the server', $html );
	}
}
