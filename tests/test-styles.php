<?php
/**
 * The stylesheet of the chapters: which file is loaded, and that WordPress prints it inline.
 *
 * @package Post Chapters
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Test classes use the required Test_PChap_ prefix.
// phpcs:disable WordPress.WP.AlternativeFunctions -- The tests write a file in a theme folder.

/**
 * PChap::enqueue_styles().
 *
 * @group rendering
 */
class Test_PChap_Styles extends PChap_UnitTestCase {

	/**
	 * Files written in the folder of the theme.
	 *
	 * @var string[]
	 */
	private $theme_files = array();

	/**
	 * Start from a clean list of styles: another test may have printed this one already.
	 */
	public function set_up() {
		parent::set_up();

		$GLOBALS['wp_styles'] = new WP_Styles(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Reset for the test.
	}

	/**
	 * Remove the styles and the files of the theme.
	 */
	public function tear_down() {
		foreach ( $this->theme_files as $file ) {
			if ( file_exists( $file ) ) {
				unlink( $file );
			}
		}
		$this->theme_files = array();

		wp_dequeue_style( 'post-chapters' );
		wp_deregister_style( 'post-chapters' );
		wp_dequeue_style( get_stylesheet() . '-post-chapters' );
		wp_deregister_style( get_stylesheet() . '-post-chapters' );
		$GLOBALS['wp_styles'] = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- WordPress creates it again.

		parent::tear_down();
	}

	/**
	 * Request a chapter and enqueue its styles.
	 */
	private function enqueue() {
		$post_id = $this->create_post( 'Intro[nextpage title="Un"]Texte' );
		$this->go_to_subpage( $post_id, 2 );

		do_action( 'wp_enqueue_scripts' );
	}

	/**
	 * The stylesheet of the plugin is declared with its path, so that WordPress can print it inline.
	 */
	public function test_plugin_stylesheet_has_its_path() {
		$this->enqueue();

		$path = wp_styles()->get_data( 'post-chapters', 'path' );

		$this->assertSame( PCHAP_PLUGIN_DIR . 'css/post-chapters' . $this->min() . '.css', $path );
		$this->assertFileExists( $path );
	}

	/**
	 * WordPress prints the small stylesheet inline.
	 */
	public function test_stylesheet_is_printed_inline() {
		if ( ! function_exists( 'wp_maybe_inline_styles' ) ) {
			$this->markTestSkipped( 'Needs WordPress 5.8.' );
		}

		$this->enqueue();
		wp_maybe_inline_styles();

		$style = wp_styles()->registered['post-chapters'];
		$this->assertFalse( $style->src, 'The stylesheet has no file to request.' );

		$output = get_echo( array( wp_styles(), 'do_items' ), array( 'post-chapters' ) );
		// WordPress 6.3 prints single quotes, later versions double quotes.
		$this->assertMatchesRegularExpression( '/<style id=["\']post-chapters-inline-css["\']/', $output );
		$this->assertStringContainsString( '.mpp-toc-container', $output );
	}

	/**
	 * A stylesheet of the theme replaces the one of the plugin, and has its path as well.
	 */
	public function test_theme_stylesheet_has_its_path() {
		$directory = trailingslashit( get_stylesheet_directory() ) . 'css';
		if ( ! is_dir( $directory ) ) {
			mkdir( $directory, 0755, true );
		}

		$file = $directory . '/post-chapters' . $this->min() . '.css';
		file_put_contents( $file, '.mpp-toc-container{color:red}' );
		$this->theme_files[] = $file;

		$this->enqueue();

		$this->assertSame( $file, wp_styles()->get_data( get_stylesheet() . '-post-chapters', 'path' ) );
		$this->assertFalse( wp_style_is( 'post-chapters', 'enqueued' ), 'The plugin stylesheet is not loaded.' );
	}

	/**
	 * The suffix of the stylesheets.
	 *
	 * @return string
	 */
	private function min() {
		return ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';
	}
}
