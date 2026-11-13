<?php
/**
 * Translations: the bundled files win over the language packs of the original plugin.
 *
 * The paginova slug is shared with the original plugin, whose language packs
 * from translate.wordpress.org (installed in WP_LANG_DIR/plugins) lack the strings of
 * this version.
 *
 * @package Paginova
 */

/**
 * Bundled translations vs installed language packs.
 *
 * @group i18n
 */
class PChap_Test_Translations extends WP_UnitTestCase {

	/**
	 * Locale forced by force_locale().
	 *
	 * @var string
	 */
	private $locale = 'fr_FR';

	/**
	 * Files written by the test, removed in tear_down().
	 *
	 * @var string[]
	 */
	private $created = array();

	/**
	 * Folders created by the test, removed in tear_down().
	 *
	 * @var string[]
	 */
	private $created_dirs = array();

	/**
	 * Text domain registry in use before the test.
	 *
	 * @var WP_Textdomain_Registry|null
	 */
	private $registry;

	/**
	 * Force the locale and start from a fresh text domain registry.
	 */
	public function set_up() {
		parent::set_up();

		$this->registry = isset( $GLOBALS['wp_textdomain_registry'] ) ? $GLOBALS['wp_textdomain_registry'] : null;

		add_filter( 'determine_locale', array( $this, 'force_locale' ) );
		$this->reset_registry();
	}

	/**
	 * Remove the fake language packs and restore the registry.
	 */
	public function tear_down() {
		remove_filter( 'determine_locale', array( $this, 'force_locale' ) );
		remove_filter( 'pchap_prefer_bundled_translations', '__return_false' );

		foreach ( $this->created as $file ) {
			if ( file_exists( $file ) ) {
				unlink( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Test fixture.
			}
		}
		foreach ( array_reverse( $this->created_dirs ) as $dir ) {
			if ( is_dir( $dir ) ) {
				rmdir( $dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Test fixture.
			}
		}

		wp_cache_delete( md5( WP_LANG_DIR . '/plugins/' ), 'translation_files' );
		self::unload_all_locales();

		if ( null !== $this->registry ) {
			$GLOBALS['wp_textdomain_registry'] = $this->registry; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restores the registry replaced in set_up().
		}

		parent::tear_down();
	}

	/**
	 * A stale fr_FR pack (.mo and .l10n.php) is ignored: the bundled fr_FR file is used.
	 */
	public function test_bundled_file_wins_over_language_pack() {
		$this->install_pack( 'fr_FR', 'Commentaires (ancien pack)' );

		$this->assert_registry_resolves_pack();
		$this->assertSame( 'Commentaires', __( 'Comments', 'paginova' ) );
		$this->assertSame( 'Navigation des chapitres', __( 'Chapter navigation', 'paginova' ) );
	}

	/**
	 * A pack still listed in the registry file cache (1 h, group translation_files) but
	 * deleted since does not leave the plugin untranslated.
	 */
	public function test_deleted_pack_still_cached_by_the_registry() {
		$files = $this->install_pack( 'fr_FR', 'Commentaires (ancien pack)' );

		$this->assert_registry_resolves_pack();

		foreach ( $files as $file ) {
			unlink( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Test fixture.
		}
		self::unload_all_locales();

		$this->assertSame( 'Commentaires', __( 'Comments', 'paginova' ) );
	}

	/**
	 * A locale without bundled translation keeps its language pack.
	 */
	public function test_pack_kept_for_a_locale_without_bundled_file() {
		$this->locale = 'nl_NL';
		$this->install_pack( 'nl_NL', 'Reacties' );

		$this->assertFileDoesNotExist( PCHAP_PLUGIN_DIR . 'languages/paginova-nl_NL.mo' );
		$this->assertSame( 'Reacties', __( 'Comments', 'paginova' ) );
	}

	/**
	 * The pchap_prefer_bundled_translations filter gives the language packs precedence back.
	 */
	public function test_filter_restores_language_pack_precedence() {
		add_filter( 'pchap_prefer_bundled_translations', '__return_false' );
		$this->install_pack( 'fr_FR', 'Commentaires (ancien pack)' );
		$this->assertSame( 'Commentaires (ancien pack)', __( 'Comments', 'paginova' ) );
	}

	/**
	 * Other text domains and files outside WP_LANG_DIR/plugins are never redirected.
	 */
	public function test_mofile_filter_scope() {
		$pack = WP_LANG_DIR . '/plugins/paginova-fr_FR.mo';

		$this->assertSame( PCHAP_PLUGIN_DIR . 'languages/paginova-fr_FR.mo', wp_normalize_path( pchap_load_bundled_textdomain_mofile( $pack, 'paginova' ) ) );
		$this->assertSame( $pack, pchap_load_bundled_textdomain_mofile( $pack, 'other-domain' ) );

		$custom = WP_LANG_DIR . '/loco/plugins/paginova-fr_FR.mo';
		$this->assertSame( $custom, pchap_load_bundled_textdomain_mofile( $custom, 'paginova' ) );

		$elsewhere = WP_CONTENT_DIR . '/custom/paginova-fr_FR.mo';
		$this->assertSame( $elsewhere, pchap_load_bundled_textdomain_mofile( $elsewhere, 'paginova' ) );
	}

	/**
	 * The block editor script uses the bundled JSON even when a pack provides one.
	 */
	public function test_bundled_script_translations_win_over_language_pack() {
		$block_type = WP_Block_Type_Registry::get_instance()->get_registered( 'multipage/subpage' );
		$this->assertInstanceOf( 'WP_Block_Type', $block_type );

		$handle   = $block_type->editor_script_handles[0];
		$basename = 'paginova-fr_FR-' . md5( 'blocks/subpage/index.js' ) . '.json';
		$bundled  = PCHAP_PLUGIN_DIR . 'languages/' . $basename;
		$this->assertFileExists( $bundled );

		$this->ensure_packs_dir();
		foreach ( array( $basename, 'paginova-fr_FR-' . $handle . '.json' ) as $name ) {
			$this->write_file( WP_LANG_DIR . '/plugins/' . $name, '{"locale_data":{"messages":{"":{"domain":"messages"},"Subpage title":["Ancien pack"]}}}' );
		}
		wp_cache_delete( md5( WP_LANG_DIR . '/plugins/' ), 'translation_files' );

		$this->assertSame( file_get_contents( $bundled ), load_script_textdomain( $handle, 'paginova' ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file.
	}

	/**
	 * The registry resolves the language pack folder, not the bundled one.
	 *
	 * Only on WordPress 6.5+, where the registry stops at the first folder holding the
	 * file (WP_LANG_DIR/plugins): that is the order that made the packs win. WordPress
	 * 6.3 and 6.4 keep the last folder found, so the bundled folder already wins there.
	 */
	private function assert_registry_resolves_pack() {
		if ( version_compare( $GLOBALS['wp_version'], '6.5', '<' ) ) {
			return;
		}

		$this->assertSame( WP_LANG_DIR . '/plugins/', $GLOBALS['wp_textdomain_registry']->get( 'paginova', 'fr_FR' ), 'The registry should resolve the language pack first.' );
	}

	/**
	 * Install a fake language pack translating "Comments" (and nothing else), in both formats.
	 *
	 * @param string $locale      Locale.
	 * @param string $translation Translation of "Comments".
	 * @return string[] Files written.
	 */
	private function install_pack( $locale, $translation ) {
		$this->ensure_packs_dir();

		$base = WP_LANG_DIR . '/plugins/paginova-' . $locale;

		$mo = new MO();
		$mo->set_header( 'Language', $locale );
		$mo->add_entry(
			new Translation_Entry(
				array(
					'singular'     => 'Comments',
					'translations' => array( $translation ),
				)
			)
		);
		$mo->export_to_file( $base . '.mo' );
		$this->created[] = $base . '.mo';

		$this->write_file(
			$base . '.l10n.php',
			'<?php return ' . var_export( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export -- Fixture generation.
				array(
					'language' => $locale,
					'messages' => array( 'Comments' => $translation ),
				),
				true
			) . ';'
		);

		// The registry lists the folder once per hour: start from a fresh listing.
		wp_cache_delete( md5( WP_LANG_DIR . '/plugins/' ), 'translation_files' );
		$this->reset_registry();

		return array( $base . '.mo', $base . '.l10n.php' );
	}

	/**
	 * Fresh text domain registry, with the plugin path registered again.
	 */
	private function reset_registry() {
		$registry = new WP_Textdomain_Registry();
		if ( method_exists( $registry, 'init' ) ) {
			// WordPress 6.5+ (hooks the cache invalidation on upgrades).
			$registry->init();
		}
		$GLOBALS['wp_textdomain_registry'] = $registry; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restored in tear_down().

		self::unload_all_locales();
		pchap_load_textdomain();
	}

	/**
	 * Unload the plugin text domain in every locale used here.
	 *
	 * The core unload_textdomain() only unloads the current locale: a file loaded for
	 * another locale would stay loaded for the next tests.
	 */
	private static function unload_all_locales() {
		unload_textdomain( 'paginova', true );

		if ( class_exists( 'WP_Translation_Controller' ) ) {
			foreach ( array( 'fr_FR', 'nl_NL' ) as $locale ) {
				WP_Translation_Controller::get_instance()->unload_textdomain( 'paginova', $locale );
			}
		}
	}

	/**
	 * Create WP_LANG_DIR/plugins if needed.
	 */
	private function ensure_packs_dir() {
		foreach ( array( WP_LANG_DIR, WP_LANG_DIR . '/plugins' ) as $dir ) {
			if ( ! is_dir( $dir ) ) {
				mkdir( $dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- Test fixture.
				$this->created_dirs[] = $dir;
			}
		}
	}

	/**
	 * Write a fixture file.
	 *
	 * @param string $file    Path.
	 * @param string $content Content.
	 */
	private function write_file( $file, $content ) {
		file_put_contents( $file, $content ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test fixture.
		$this->created[] = $file;
	}

	/**
	 * Filter callback forcing the locale.
	 *
	 * @return string
	 */
	public function force_locale() {
		return $this->locale;
	}
}
