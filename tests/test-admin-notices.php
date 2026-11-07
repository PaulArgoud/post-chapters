<?php
/**
 * Admin notices tests: invalid setting values and subpage index rebuild results.
 *
 * @package Multipage
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Test classes use the required Test_Mpp_ prefix and tests apply core and third-party hooks.
// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited -- The tests set up and restore the settings fields and errors globals.

/**
 * The settings errors added by mpp_sanitize_option() and mpp_admin_rebuild_postmeta_notice().
 *
 * @group multipage
 * @group admin
 */
class Test_Mpp_Admin_Notices extends Mpp_UnitTestCase {

	/**
	 * Saved $wp_settings_fields.
	 *
	 * @var mixed
	 */
	private $saved_fields;

	/**
	 * Load the admin files and start with no settings error.
	 */
	public function set_up() {
		global $wp_settings_fields;

		parent::set_up();

		require_once ABSPATH . 'wp-admin/includes/template.php';
		require_once MPP_PLUGIN_DIR . 'inc/admin/admin-advanced-settings.php';

		$this->saved_fields            = $wp_settings_fields;
		$GLOBALS['wp_settings_errors'] = array();
	}

	/**
	 * Restore the settings fields, the settings errors and the request.
	 */
	public function tear_down() {
		$GLOBALS['wp_settings_fields'] = $this->saved_fields;
		$GLOBALS['wp_settings_errors'] = array();
		$_GET                          = array();
		parent::tear_down();
	}

	/**
	 * Register the settings fields as the admin does (MPP_Admin::register_admin_settings()),
	 * without keeping the settings themselves (their sanitize callbacks are not needed).
	 */
	private function register_fields() {
		$admin = new MPP_Admin();
		$admin->register_admin_settings();

		foreach ( array_keys( mpp_get_option_definitions() ) as $option_name ) {
			$definition = mpp_get_option_definition( $option_name );
			unregister_setting( $definition['group'], $option_name );
		}
	}

	/**
	 * Messages of the settings errors of a setting.
	 *
	 * @param string $setting Setting slug.
	 * @return array Code => array( message, type ).
	 */
	private function errors( $setting ) {
		$errors = array();
		foreach ( get_settings_errors( $setting ) as $error ) {
			$errors[ $error['code'] ] = array( $error['message'], $error['type'] );
		}
		return $errors;
	}

	/**
	 * The invalid value notice names the setting by its label on the settings screen.
	 *
	 * Each test uses its own option: mpp_sanitize_option() reports an option once per request.
	 */
	public function test_invalid_value_notice_uses_the_field_label() {
		$this->register_fields();

		update_option( 'mpp-comments-on-page', 'last-page' );
		$this->assertSame( 'last-page', mpp_sanitize_option( 'nope', 'mpp-comments-on-page' ) );

		$errors = $this->errors( 'mpp-comments-on-page' );
		$this->assertArrayHasKey( 'mpp-invalid-mpp-comments-on-page', $errors );
		$this->assertSame(
			array( 'An invalid value was submitted for the Display comments on setting. The previous value was kept.', 'error' ),
			$errors['mpp-invalid-mpp-comments-on-page']
		);
	}

	/**
	 * Without registered settings fields (update_option() outside the settings screens),
	 * the notice falls back to the option name.
	 */
	public function test_invalid_value_notice_falls_back_to_the_option_name() {
		$GLOBALS['wp_settings_fields'] = array();

		$this->assertSame( 20, mpp_sanitize_option( 'abc', '_mpp-rewrite-content-priority' ) );

		$errors = $this->errors( '_mpp-rewrite-content-priority' );
		$this->assertSame(
			'An invalid value was submitted for the _mpp-rewrite-content-priority setting. The previous value was kept.',
			$errors['mpp-invalid-_mpp-rewrite-content-priority'][0]
		);
	}

	/**
	 * The field label is plain text: HTML in a (filtered) field title is not printed as markup.
	 */
	public function test_field_label_is_escaped() {
		$GLOBALS['wp_settings_fields'] = array(
			'multipage' => array(
				'mpp_toc' => array(
					'mpp-hide-intro-title' => array( 'title' => '<b>Intro</b> &amp; "more"' ),
				),
			),
		);

		$this->assertSame( 'Intro & "more"', _mpp_get_option_field_label( 'mpp-hide-intro-title' ) );

		$GLOBALS['wp_settings_fields']['multipage']['mpp_toc']['mpp-hide-intro-title']['title'] = '<script>alert(1)</script>';
		$this->assertSame( 'mpp-hide-intro-title', _mpp_get_option_field_label( 'mpp-hide-intro-title' ), 'A label without text falls back to the option name.' );
	}

	/**
	 * Rebuild result notices: singular and plural forms of each count.
	 *
	 * @dataProvider data_rebuild_notices
	 *
	 * @param array  $query    Query arguments of the redirect.
	 * @param string $type     Expected notice type.
	 * @param string $expected Expected message.
	 */
	public function test_rebuild_notice( $query, $type, $expected ) {
		$_GET = array_merge( array( 'page' => 'mpp-advanced-settings' ), $query );

		mpp_admin_rebuild_postmeta_notice();

		$errors = array_values( $this->errors( 'mpp-rebuild' ) );
		$this->assertCount( 1, $errors );
		$this->assertSame( array( $expected, $type ), $errors[0] );
	}

	/**
	 * Rebuild redirects => expected notice.
	 *
	 * @return array
	 */
	public function data_rebuild_notices() {
		return array(
			'done, singular'       => array(
				array(
					'mpp-rebuild'   => 'done',
					'mpp-processed' => '1',
					'mpp-updated'   => '1',
					'mpp-deleted'   => '1',
				),
				'success',
				'Subpage index rebuilt: 1 post checked, 1 post updated, 1 stale entry removed.',
			),
			'done, plural'         => array(
				array(
					'mpp-rebuild'   => 'done',
					'mpp-processed' => '1234',
					'mpp-updated'   => '2',
					'mpp-deleted'   => '0',
				),
				'success',
				'Subpage index rebuilt: 1,234 posts checked, 2 posts updated, 0 stale entries removed.',
			),
			'done, missing counts' => array(
				array( 'mpp-rebuild' => 'done' ),
				'success',
				'Subpage index rebuilt: 0 posts checked, 0 posts updated, 0 stale entries removed.',
			),
			'partial'              => array(
				array(
					'mpp-rebuild'   => 'partial',
					'mpp-processed' => '1',
					'mpp-updated'   => '0',
					'mpp-deleted'   => '1',
				),
				'info',
				'Subpage index rebuild started: 1 post checked, 0 posts updated, 1 stale entry removed so far. The remaining posts are processed in the background.',
			),
			'locked'               => array(
				array( 'mpp-rebuild' => 'locked' ),
				'warning',
				'A subpage index rebuild is already running. Please try again in a few minutes.',
			),
			'negative counts'      => array(
				array(
					'mpp-rebuild'   => 'done',
					'mpp-processed' => '-3',
					'mpp-updated'   => 'x',
					'mpp-deleted'   => '1',
				),
				'success',
				'Subpage index rebuilt: 3 posts checked, 0 posts updated, 1 stale entry removed.',
			),
		);
	}

	/**
	 * No rebuild notice on other screens or for unknown statuses.
	 */
	public function test_no_rebuild_notice() {
		foreach (
			array(
				array(),
				array( 'mpp-rebuild' => 'done' ),
				array(
					'page'        => 'multipage',
					'mpp-rebuild' => 'done',
				),
				array(
					'page'        => 'mpp-advanced-settings',
					'mpp-rebuild' => 'unknown',
				),
				array(
					'page'        => 'mpp-advanced-settings',
					'mpp-rebuild' => '',
				),
			) as $query
		) {
			$_GET = $query;
			mpp_admin_rebuild_postmeta_notice();
			$this->assertSame( array(), get_settings_errors( 'mpp-rebuild' ), wp_json_encode( $query ) );
		}
	}
}
