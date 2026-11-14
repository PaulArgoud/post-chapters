<?php
/**
 * Options tests.
 *
 * @package Paginova
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Test classes use the required Test_PChap_ prefix and tests apply core and third-party hooks.

/**
 * Option definitions, getters, validation, sanitization and the in-memory cache.
 *
 * @group multipage
 * @group options
 */
class Test_PChap_Options extends PChap_UnitTestCase {

	/**
	 * Names of the settings registered by this test.
	 *
	 * @var string[]
	 */
	private $registered = array();

	/**
	 * Unregister the settings registered by the test.
	 */
	public function tear_down() {
		foreach ( $this->registered as $option_name ) {
			$definition = pchap_get_option_definition( $option_name );
			if ( $definition ) {
				unregister_setting( $definition['group'], $option_name );
			}
		}
		$this->registered = array();
		parent::tear_down();
	}

	/**
	 * Register the plugin settings as the admin does (PChap_Admin::register_admin_settings()).
	 */
	private function register_settings() {
		require_once ABSPATH . 'wp-admin/includes/template.php';

		$admin = new PChap_Admin();
		$admin->register_admin_settings();

		$this->registered = array_keys( pchap_get_option_definitions() );
	}

	/**
	 * Getter => array( option name, default value, a valid non-default value ).
	 *
	 * @return array
	 */
	public function data_getters() {
		return array(
			'pchap_hide_intro_title'             => array( 'pchap_hide_intro_title', 'pchap-hide-intro-title', false, true ),
			'pchap_get_comments_on_page'         => array( 'pchap_get_comments_on_page', 'pchap-comments-on-page', 'all', 'last-page' ),
			'pchap_get_continue_or_prev_next'    => array( 'pchap_get_continue_or_prev_next', 'pchap-continue-or-prev-next', 'continue', 'next-previous' ),
			'pchap_disable_standard_pagination'  => array( 'pchap_disable_standard_pagination', 'pchap-disable-standard-pagination', true, false ),
			'pchap_toc_only_on_the_first_page'   => array( 'pchap_toc_only_on_the_first_page', 'pchap-toc-only-on-the-first-page', false, true ),
			'pchap_get_toc_position'             => array( 'pchap_get_toc_position', 'pchap-toc-position', 'top-right', 'bottom' ),
			'pchap_get_toc_row_labels'           => array( 'pchap_get_toc_row_labels', 'pchap-toc-row-labels', 'number', 'page' ),
			'pchap_hide_toc_header'              => array( 'pchap_hide_toc_header', 'pchap-hide-toc-header', false, true ),
			'pchap_comments_toc_link'            => array( 'pchap_comments_toc_link', 'pchap-comments-toc-link', false, true ),
			'pchap_get_rewrite_title_priority'   => array( 'pchap_get_rewrite_title_priority', 'pchap-rewrite-title-priority', 20, 100 ),
			'pchap_get_rewrite_content_priority' => array( 'pchap_get_rewrite_content_priority', 'pchap-rewrite-content-priority', 20, 5 ),
			'pchap_disable_tinymce_buttons'      => array( 'pchap_disable_tinymce_buttons', 'pchap-disable-tinymce-buttons', false, true ),
		);
	}

	/**
	 * Every getter returns its default when the option is missing.
	 *
	 * @dataProvider data_getters
	 *
	 * @param string $getter        Getter function.
	 * @param string $option        Option name.
	 * @param mixed  $default_value Default value.
	 */
	public function test_getter_default( $getter, $option, $default_value ) {
		delete_option( $option );

		$this->assertSame( $default_value, call_user_func( $getter ) );
		$this->assertSame( $default_value, pchap_get_option_definition( $option )['default'] );
	}

	/**
	 * Every getter returns a valid stored value.
	 *
	 * @dataProvider data_getters
	 *
	 * @param string $getter        Getter function.
	 * @param string $option        Option name.
	 * @param mixed  $default_value Default value.
	 * @param mixed  $valid         Valid non-default value.
	 */
	public function test_getter_valid_value( $getter, $option, $default_value, $valid ) {
		update_option( $option, is_bool( $valid ) ? (int) $valid : $valid );

		$this->assertSame( $valid, call_user_func( $getter ) );
	}

	/**
	 * Every getter falls back to its default for invalid stored values.
	 *
	 * @dataProvider data_getters
	 *
	 * @param string $getter        Getter function.
	 * @param string $option        Option name.
	 * @param mixed  $default_value Default value.
	 */
	public function test_getter_invalid_values_fall_back_to_default( $getter, $option, $default_value ) {
		$definition = pchap_get_option_definition( $option );

		$invalid = array( array( 'x' ), (object) array( 'a' => 1 ) );
		if ( 'boolean' !== $definition['type'] ) {
			$invalid[] = 'not-a-valid-value';
			$invalid[] = '';
		}
		if ( 'priority' === $definition['type'] ) {
			$invalid[] = '12abc';
			$invalid[] = '1.5';
		}

		foreach ( $invalid as $value ) {
			update_option( $option, $value );
			$this->assertSame( $default_value, call_user_func( $getter ), 'Stored: ' . wp_json_encode( $value ) );
		}
	}

	/**
	 * Booleans stored by every plugin version.
	 */
	public function test_boolean_values_of_all_versions() {
		foreach ( array( 1, '1', true, 'on', 'yes', 'true' ) as $value ) {
			update_option( 'pchap-hide-toc-header', $value );
			$this->assertTrue( pchap_hide_toc_header(), wp_json_encode( $value ) );
		}

		foreach ( array( 0, '0', '', 'off', 'false' ) as $value ) {
			update_option( 'pchap-hide-toc-header', $value );
			$this->assertFalse( pchap_hide_toc_header(), wp_json_encode( $value ) );
		}
	}

	/**
	 * Priorities stored as strings by 1.4-1.6.
	 */
	public function test_priority_strings() {
		update_option( 'pchap-rewrite-title-priority', '50' );
		$this->assertSame( 50, pchap_get_rewrite_title_priority() );

		update_option( 'pchap-rewrite-content-priority', ' 10 ' );
		$this->assertSame( 10, pchap_get_rewrite_content_priority() );

		update_option( 'pchap-rewrite-content-priority', 7 );
		$this->assertSame( 7, pchap_get_rewrite_content_priority(), 'Any integer priority is allowed.' );
	}

	/**
	 * The getter filters still apply.
	 */
	public function test_getter_filters() {
		add_filter(
			'pchap_get_toc_position',
			static function () {
				return 'top-left';
			}
		);
		add_filter( 'pchap_hideintro_title', '__return_true' );

		$this->assertSame( 'top-left', pchap_get_toc_position() );
		$this->assertTrue( pchap_hide_intro_title() );
	}

	/**
	 * The definitions filter can add allowed values, but never drop core definitions.
	 */
	public function test_definitions_filter() {
		add_filter(
			'pchap_get_option_definitions',
			static function ( $definitions ) {
				$definitions['pchap-toc-position']['choices'][] = 'sidebar';
				unset( $definitions['pchap-hide-toc-header'] );
				$definitions['pchap-toc-row-labels']['choices'] = 'broken';
				return $definitions;
			}
		);

		$definitions = pchap_get_option_definitions();
		$this->assertContains( 'sidebar', $definitions['pchap-toc-position']['choices'] );
		$this->assertArrayHasKey( 'pchap-hide-toc-header', $definitions );
		$this->assertSame( array( 'number', 'page', 'hidden' ), $definitions['pchap-toc-row-labels']['choices'] );

		update_option( 'pchap-toc-position', 'sidebar' );
		$this->assertSame( 'sidebar', pchap_get_toc_position() );

		$labels = pchap_get_option_choice_labels( 'pchap-toc-position' );
		$this->assertSame( 'sidebar', $labels['sidebar'] );
	}

	/**
	 * Function pchap_validate_option_value().
	 */
	public function test_validate_option_value() {
		$this->assertSame( 'bottom', pchap_validate_option_value( 'pchap-toc-position', 'bottom' ) );
		$this->assertSame( 'top-right', pchap_validate_option_value( 'pchap-toc-position', 'nope' ) );
		$this->assertSame( 'top', pchap_validate_option_value( 'pchap-toc-position', 'nope', 'top' ), 'Valid fallback.' );
		$this->assertSame( 'top-right', pchap_validate_option_value( 'pchap-toc-position', 'nope', 'also-invalid' ) );
		$this->assertSame( 'untouched', pchap_validate_option_value( 'unknown-option', 'untouched' ) );
		$this->assertTrue( pchap_validate_option_value( 'pchap-hide-toc-header', '1' ) );
		$this->assertSame( 100, pchap_validate_option_value( 'pchap-rewrite-title-priority', '100' ) );
	}

	/**
	 * Sanitize callbacks, through sanitize_option() (as options.php does).
	 */
	public function test_sanitize_option_callbacks() {
		$this->register_settings();

		// Booleans: stored as 1/0, an unchecked checkbox is posted as null.
		$this->assertSame( 1, sanitize_option( 'pchap-hide-toc-header', '1' ) );
		$this->assertSame( 1, sanitize_option( 'pchap-hide-toc-header', 'on' ) );
		$this->assertSame( 0, sanitize_option( 'pchap-hide-toc-header', null ) );
		$this->assertSame( 0, sanitize_option( 'pchap-hide-toc-header', '' ) );
		$this->assertSame( 0, sanitize_option( 'pchap-hide-toc-header', '0' ) );
		$this->assertSame( 0, sanitize_option( 'pchap-hide-toc-header', array( 1 ) ) );

		// Choices.
		$this->assertSame( 'bottom', sanitize_option( 'pchap-toc-position', 'bottom' ) );
		$this->assertSame( 'next-previous', sanitize_option( 'pchap-continue-or-prev-next', ' next-previous ' ) );

		// Invalid choice: keeps the current valid value and reports an error.
		update_option( 'pchap-toc-position', 'top-left' );
		$this->assertSame( 'top-left', sanitize_option( 'pchap-toc-position', '<script>x</script>' ) );
		$errors = get_settings_errors( 'pchap-toc-position' );
		$this->assertNotEmpty( $errors );
		$this->assertSame( 'error', $errors[0]['type'] );

		// Invalid with an invalid stored value: the default.
		update_option( 'pchap-toc-row-labels', 'corrupted' );
		$this->assertSame( 'number', sanitize_option( 'pchap-toc-row-labels', 'nope' ) );

		// Priorities.
		$this->assertSame( 50, sanitize_option( 'pchap-rewrite-title-priority', '50' ) );
		$this->assertSame( 20, sanitize_option( 'pchap-rewrite-title-priority', 'abc' ) );
	}

	/**
	 * Function update_option() goes through the sanitize callbacks once the settings are registered.
	 */
	public function test_update_option_is_sanitized_when_registered() {
		$this->register_settings();

		update_option( 'pchap-comments-toc-link', 'yes' );
		$this->assertSame( 1, get_option( 'pchap-comments-toc-link' ) );

		update_option( 'pchap-continue-or-prev-next', 'invalid' );
		$this->assertSame( 'continue', get_option( 'pchap-continue-or-prev-next' ) );
	}

	/**
	 * The defaults registered with register_setting() are returned by get_option().
	 */
	public function test_registered_defaults() {
		$this->register_settings();

		delete_option( 'pchap-disable-standard-pagination' );
		delete_option( 'pchap-toc-position' );
		delete_option( 'pchap-rewrite-content-priority' );

		$this->assertSame( 1, get_option( 'pchap-disable-standard-pagination' ) );
		$this->assertSame( 'top-right', get_option( 'pchap-toc-position' ) );
		$this->assertSame( 20, get_option( 'pchap-rewrite-content-priority' ) );
		$this->assertTrue( pchap_disable_standard_pagination() );
	}

	/**
	 * Function pchap_sanitize_option() for unknown options.
	 */
	public function test_sanitize_unknown_option() {
		$this->assertSame( 'abc', pchap_sanitize_option( '<b>abc</b>', 'unknown' ) );
		$this->assertSame( '', pchap_sanitize_option( array( 'x' ), 'unknown' ) );
	}

	/**
	 * The in-memory cache: cleared on add/update/delete, and by pchap_clear_options_cache().
	 */
	public function test_options_cache() {
		global $wpdb;

		update_option( 'pchap-toc-position', 'bottom' );
		$this->assertSame( 'bottom', pchap_get_option( 'pchap-toc-position' ) );

		// A direct database change is not seen until the cache is cleared.
		$wpdb->update( $wpdb->options, array( 'option_value' => 'top' ), array( 'option_name' => 'pchap-toc-position' ) );
		wp_cache_delete( 'alloptions', 'options' );
		wp_cache_delete( 'pchap-toc-position', 'options' );
		$this->assertSame( 'bottom', pchap_get_option( 'pchap-toc-position' ) );

		pchap_clear_options_cache();
		$this->assertSame( 'top', pchap_get_option( 'pchap-toc-position' ) );

		// The option hooks clear it.
		update_option( 'pchap-toc-position', 'hidden' );
		$this->assertSame( 'hidden', pchap_get_option( 'pchap-toc-position' ) );

		delete_option( 'pchap-toc-position' );
		$this->assertSame( 'fallback', pchap_get_option( 'pchap-toc-position', 'fallback' ) );

		add_option( 'pchap-toc-position', 'top-left' );
		$this->assertSame( 'top-left', pchap_get_option( 'pchap-toc-position' ) );
	}

	/**
	 * Missing options are cached as missing: the default is applied per call.
	 */
	public function test_missing_option_default_per_call() {
		delete_option( 'pchap-hide-intro-title' );

		$this->assertSame( 'a', pchap_get_option( 'pchap-hide-intro-title', 'a' ) );
		$this->assertSame( 'b', pchap_get_option( 'pchap-hide-intro-title', 'b' ) );
	}

	/**
	 * Every choice has a translated label.
	 */
	public function test_choice_labels() {
		$labels = pchap_get_option_choice_labels();

		foreach ( pchap_get_option_definitions() as $name => $definition ) {
			foreach ( $definition['choices'] as $choice ) {
				$this->assertArrayHasKey( $choice, $labels[ $name ], "{$name}: {$choice}" );
				$this->assertNotSame( '', (string) $labels[ $name ][ $choice ] );
			}
		}

		$this->assertSame( array(), pchap_get_option_choice_labels( 'unknown' ) );
	}
}
