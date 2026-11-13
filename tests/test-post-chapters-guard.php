<?php
/**
 * Coexistence with Post Chapters 2.x, the former name of Paginova.
 *
 * @package Paginova
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Test classes use the required Test_PChap_ prefix.

/**
 * Paginova stays idle while Post Chapters 2.x is active.
 *
 * @group compat
 */
class Test_PChap_Post_Chapters_Guard extends PChap_UnitTestCase {

	/**
	 * Fake plugin folders created by the tests.
	 *
	 * @var string[]
	 */
	private $fake_folders = array();

	/**
	 * Remove the fake plugins and the active plugins list.
	 */
	public function tear_down() {
		delete_option( 'active_plugins' );
		foreach ( $this->fake_folders as $folder ) {
			wp_delete_file( WP_PLUGIN_DIR . '/' . $folder . '/post-chapters.php' );
			rmdir( WP_PLUGIN_DIR . '/' . $folder ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Test fixture cleanup.
		}
		parent::tear_down();
	}

	/**
	 * Create a fake Post Chapters main file in a plugin folder.
	 *
	 * @param string $folder Plugin folder name.
	 * @return string The plugin basename (folder/post-chapters.php).
	 */
	private function fake_post_chapters( $folder ) {
		wp_mkdir_p( WP_PLUGIN_DIR . '/' . $folder );
		file_put_contents( WP_PLUGIN_DIR . '/' . $folder . '/post-chapters.php', "<?php\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test fixture.
		$this->fake_folders[] = $folder;

		return $folder . '/post-chapters.php';
	}

	/**
	 * The guard is off when Post Chapters is not active.
	 */
	public function test_guard_off_without_post_chapters() {
		update_option( 'active_plugins', array( 'paginova/paginova.php', 'akismet/akismet.php' ) );

		$this->assertFalse( pchap_is_post_chapters_active() );
	}

	/**
	 * The guard detects Post Chapters, whatever the name of its folder.
	 */
	public function test_guard_detects_post_chapters() {
		foreach ( array( 'pchap-test-post-chapters', 'pchap-test-post-chapters-main' ) as $folder ) {
			$basename = $this->fake_post_chapters( $folder );
			update_option( 'active_plugins', array( 'paginova/paginova.php', $basename ) );
			$this->assertTrue( pchap_is_post_chapters_active(), $basename );
		}
	}

	/**
	 * An active entry whose file was deleted does not keep Paginova idle.
	 */
	public function test_guard_ignores_deleted_post_chapters() {
		update_option( 'active_plugins', array( 'paginova/paginova.php', 'pchap-test-deleted/post-chapters.php' ) );

		$this->assertFalse( pchap_is_post_chapters_active() );
	}

	/**
	 * The notice is shown to the users who can deactivate plugins only.
	 */
	public function test_guard_notice() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		ob_start();
		pchap_post_chapters_active_notice();
		$this->assertSame( '', ob_get_clean() );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		ob_start();
		pchap_post_chapters_active_notice();
		$html = ob_get_clean();

		$this->assertStringContainsString( 'notice-warning', $html );
		$this->assertStringContainsString( 'Deactivate Post Chapters', $html );
		$this->assertStringContainsString( 'delete its folder from the server', $html );
	}

	/**
	 * The main file declares no function of Post Chapters 2.x.
	 *
	 * PHP declares the top-level functions of a file when it compiles it, even
	 * below a return: a function of Post Chapters 2.x declared there would make
	 * Post Chapters fail with "Cannot redeclare" while Paginova waits for it to
	 * be deactivated. Only the functions of the guard may live in paginova.php.
	 */
	public function test_main_file_declares_only_the_guard() {
		$tokens    = token_get_all( (string) file_get_contents( PCHAP_PLUGIN_DIR . 'paginova.php' ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file.
		$functions = array();
		$count     = count( $tokens );

		for ( $i = 0; $i < $count; $i++ ) {
			if ( is_array( $tokens[ $i ] ) && T_FUNCTION === $tokens[ $i ][0] ) {
				for ( $j = $i + 1; $j < $count; $j++ ) {
					if ( is_array( $tokens[ $j ] ) && T_STRING === $tokens[ $j ][0] ) {
						$functions[] = $tokens[ $j ][1];
						break;
					}
				}
			}
		}

		$this->assertSame(
			array( 'pchap_is_post_chapters_active', 'pchap_load_idle_textdomain', 'pchap_post_chapters_active_notice' ),
			$functions
		);
		$this->assertDoesNotMatchRegularExpression( '/^\s*(?:final\s+|abstract\s+)?(?:class|interface|trait)\s/m', (string) file_get_contents( PCHAP_PLUGIN_DIR . 'paginova.php' ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file.
	}
}
