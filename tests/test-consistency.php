<?php
/**
 * Consistency of the release: the version in every file, the files the plugin loads, and
 * the block metadata kept in two places.
 *
 * @package Post Chapters
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Test classes use the required Test_PChap_ prefix.

/**
 * Guards against the mistakes of a release: a version left behind, a renamed asset, a block
 * definition that drifted from its copy in index.js.
 *
 * @group consistency
 */
class Test_PChap_Consistency extends WP_UnitTestCase {

	/**
	 * Contents of a file of the plugin.
	 *
	 * @param string $file Path relative to the plugin folder.
	 * @return string
	 */
	private function read( $file ) {
		$path = PCHAP_PLUGIN_DIR . $file;
		$this->assertFileExists( $path );

		return (string) file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file.
	}

	/**
	 * Value captured by a pattern in a file.
	 *
	 * @param string $file    Path relative to the plugin folder.
	 * @param string $pattern Pattern with one capture group.
	 * @return string
	 */
	private function capture( $file, $pattern ) {
		$this->assertSame( 1, preg_match( $pattern, $this->read( $file ), $match ), "No match for $pattern in $file." );

		return $match[1];
	}

	/**
	 * Every place that holds the version agrees with the plugin header.
	 */
	public function test_version_is_the_same_everywhere() {
		$version = $this->capture( 'post-chapters.php', '/^\s*\*\s*Version:\s*(\S+)/m' );

		$this->assertMatchesRegularExpression( '/^\d+\.\d+\.\d+$/', $version );
		$this->assertSame( $version, PCHAP_VERSION, 'PCHAP_VERSION' );
		$this->assertSame( $version, pchap()->version, 'The instance version' );

		$block = wp_json_file_decode( PCHAP_PLUGIN_DIR . 'blocks/subpage/block.json', array( 'associative' => true ) );
		$this->assertSame( $version, $block['version'], 'blocks/subpage/block.json' );

		$this->assertSame( $version, $this->capture( 'blocks/subpage/index.asset.php', "/: '(\d+\.\d+\.\d+)'/" ), 'The fallback version of index.asset.php' );
		$this->assertSame( $version, $this->capture( 'readme.txt', '/^Stable tag:\s*(\S+)/m' ), 'readme.txt Stable tag' );
		$this->assertSame( $version, $this->capture( 'README.md', '/badge\/version-(\d+\.\d+\.\d+)-blue/' ), 'The README badge' );
	}

	/**
	 * The changelogs have an entry for the version.
	 */
	public function test_changelogs_have_the_version() {
		$version = PCHAP_VERSION;

		$this->assertStringContainsString( "\n## [$version]", $this->read( 'CHANGELOG.md' ), 'CHANGELOG.md' );
		$this->assertStringContainsString( "\n= $version =\n", $this->read( 'readme.txt' ), 'The changelog of readme.txt' );
	}

	/**
	 * Files the plugin loads: each stylesheet and script exists, minified and not, left to right and right to left.
	 */
	public function test_loaded_files_exist() {
		$files = array();

		foreach ( array( '', '-rtl' ) as $direction ) {
			foreach ( array( '.css', '.min.css' ) as $extension ) {
				$files[] = 'css/post-chapters' . $direction . $extension;
				$files[] = 'admin/css/post-chapters-admin' . $direction . $extension;
			}
		}

		foreach ( array( '.css', '.min.css' ) as $extension ) {
			$files[] = 'inc/admin/tinymce/css/post-chapters' . $extension;
		}
		foreach ( array( '.js', '.min.js' ) as $extension ) {
			$files[] = 'inc/admin/tinymce/js/plugin' . $extension;
		}

		foreach ( array( 'block.json', 'index.js', 'index.asset.php', 'editor.css' ) as $file ) {
			$files[] = 'blocks/subpage/' . $file;
		}

		foreach ( $files as $file ) {
			$this->assertFileExists( PCHAP_PLUGIN_DIR . $file );

			if ( preg_match( '/\.min\.(css|js)$/', $file ) ) {
				$this->assertGreaterThan( 0, filesize( PCHAP_PLUGIN_DIR . $file ), "$file is empty." );
			}
		}
	}

	/**
	 * The editor script depends on scripts WordPress registers, and on those its code uses.
	 */
	public function test_editor_script_dependencies() {
		$asset = include PCHAP_PLUGIN_DIR . 'blocks/subpage/index.asset.php';

		foreach ( array( 'wp-blocks', 'wp-block-editor', 'wp-element', 'wp-i18n', 'wp-primitives', 'wp-plugins', 'wp-data', 'wp-components', 'wp-editor', 'wp-dom-ready' ) as $handle ) {
			$this->assertContains( $handle, $asset['dependencies'], "$handle is not a dependency of the editor script." );
		}

		foreach ( $asset['dependencies'] as $handle ) {
			$this->assertTrue( wp_script_is( $handle, 'registered' ), "$handle is not a script of WordPress." );
		}
	}

	/**
	 * The copy of the block metadata in index.js matches block.json (the copy registers the block
	 * where WordPress did not bootstrap the server definition).
	 */
	public function test_block_metadata_copy_matches_block_json() {
		$json   = wp_json_file_decode( PCHAP_PLUGIN_DIR . 'blocks/subpage/block.json', array( 'associative' => true ) );
		$script = $this->read( 'blocks/subpage/index.js' );

		$this->assertSame( 1, preg_match( '/var metadata = \{(.*?)\n\t\};/s', $script, $match ), 'The metadata object of index.js was not found.' );
		$copy = $match[1];

		foreach ( array( 'name', 'title', 'category', 'description', 'textdomain' ) as $field ) {
			$this->assertSame( 1, preg_match( '/\b' . $field . ": '((?:[^'\\\\]|\\\\.)*)'/", $copy, $value ), "$field is missing from the copy." );
			$this->assertSame( $json[ $field ], stripslashes( $value[1] ), "$field differs from block.json." );
		}

		$this->assertSame( 1, preg_match( '/keywords: \[(.*?)\]/s', $copy, $keywords ) );
		preg_match_all( "/'([^']+)'/", $keywords[1], $words );
		$this->assertSame( $json['keywords'], $words[1], 'keywords differ from block.json.' );

		$this->assertSame( $json['apiVersion'], (int) $this->capture( 'blocks/subpage/index.js', '/var metadata = \{\s*apiVersion: (\d+)/' ), 'apiVersion' );

		// save() depends on the default of the title attribute: it must stay `false`.
		$this->assertFalse( $json['attributes']['title']['default'], 'The default of the title attribute in block.json.' );
		$this->assertMatchesRegularExpression( "/title: \{\s*type: 'string',\s*'default': false\s*\}/", $copy, 'The default of the title attribute in index.js.' );

		foreach ( $json['supports'] as $support => $enabled ) {
			$this->assertMatchesRegularExpression( '/\b' . $support . ': ' . ( $enabled ? 'true' : 'false' ) . '\b/', $copy, "The $support support differs from block.json." );
		}
	}
}
