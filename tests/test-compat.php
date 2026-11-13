<?php
/**
 * Multipage compatibility tests: deprecated mpp_* hooks and the coexistence guard.
 *
 * @package Paginova
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Test classes use the required Test_PChap_ prefix and tests hook the deprecated mpp_* names on purpose.

/**
 * Compatibility with Multipage 1.x.
 *
 * @group compat
 */
class Test_PChap_Compat extends PChap_UnitTestCase {

	/**
	 * Folders of the fake Multipage installs created by the tests.
	 *
	 * @var string[]
	 */
	private $fake_folders = array();

	/**
	 * Restore the active plugins and remove the fake Multipage installs after each test.
	 */
	public function tear_down() {
		delete_option( 'active_plugins' );
		foreach ( $this->fake_folders as $folder ) {
			wp_delete_file( WP_PLUGIN_DIR . '/' . $folder . '/sgr-nextpage-titles.php' );
			rmdir( WP_PLUGIN_DIR . '/' . $folder ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Test fixture cleanup.
		}
		parent::tear_down();
	}

	/**
	 * Create a fake Multipage main file in a plugin folder.
	 *
	 * @param string $folder Plugin folder name.
	 * @return string The plugin basename (folder/sgr-nextpage-titles.php).
	 */
	private function fake_multipage( $folder ) {
		wp_mkdir_p( WP_PLUGIN_DIR . '/' . $folder );
		file_put_contents( WP_PLUGIN_DIR . '/' . $folder . '/sgr-nextpage-titles.php', "<?php\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test fixture.
		$this->fake_folders[] = $folder;

		return $folder . '/sgr-nextpage-titles.php';
	}

	/**
	 * Every pchap_* hook maps to its mpp_* name, other hooks to nothing.
	 */
	public function test_legacy_hook_name() {
		$this->assertSame( 'mpp_toc', pchap_get_legacy_hook_name( 'pchap_toc' ) );
		$this->assertSame( 'mpp_get_toc_position', pchap_get_legacy_hook_name( 'pchap_get_toc_position' ) );
		$this->assertSame( '', pchap_get_legacy_hook_name( 'the_content' ) );
		$this->assertSame( '', pchap_get_legacy_hook_name( 'my_pchap_toc' ) );
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
		update_option( 'active_plugins', array( 'paginova/paginova.php', 'akismet/akismet.php' ) );

		$this->assertFalse( pchap_is_multipage_active() );
	}

	/**
	 * The guard detects Multipage, whatever the name of its folder.
	 */
	public function test_guard_detects_multipage() {
		foreach ( array( 'pchap-test-multipage', 'pchap-test-multipage-main' ) as $folder ) {
			$basename = $this->fake_multipage( $folder );
			update_option( 'active_plugins', array( 'paginova/paginova.php', $basename ) );
			$this->assertTrue( pchap_is_multipage_active(), $basename );
		}
	}

	/**
	 * An active entry whose Multipage file was deleted (folder removed over FTP
	 * while active) does not keep Paginova idle.
	 */
	public function test_guard_ignores_deleted_multipage() {
		update_option( 'active_plugins', array( 'paginova/paginova.php', 'pchap-test-deleted/sgr-nextpage-titles.php' ) );

		$this->assertFalse( pchap_is_multipage_active() );
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
