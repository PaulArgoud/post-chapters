<?php
/**
 * Settings screens built from pchap_get_settings_sections() and pchap_get_settings_fields().
 *
 * @package Paginova
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Test classes use the required Test_PChap_ prefix.
// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited -- The tests reset the settings sections and fields globals.

/**
 * PChap_Admin::register_admin_settings() and pchap_admin_render_option_field().
 *
 * @group admin
 */
class Test_PChap_Admin_Settings extends PChap_UnitTestCase {

	/**
	 * Load the admin files and register the screens.
	 */
	public function set_up() {
		parent::set_up();

		require_once ABSPATH . 'wp-admin/includes/template.php';
		require_once PCHAP_PLUGIN_DIR . 'inc/admin/admin-settings.php';
		require_once PCHAP_PLUGIN_DIR . 'inc/admin/admin-advanced-settings.php';

		$GLOBALS['wp_settings_sections'] = array();
		$GLOBALS['wp_settings_fields']   = array();

		$admin = new PChap_Admin();
		$admin->register_admin_settings();
	}

	/**
	 * Output of a field callback.
	 *
	 * @param callable $callback Callback.
	 * @param array    $args     Arguments.
	 * @return string
	 */
	private function render( $callback, $args = array() ) {
		ob_start();
		call_user_func_array( $callback, $args );
		return (string) ob_get_clean();
	}

	/**
	 * Every option has exactly one field, in a registered section of its own screen.
	 */
	public function test_every_option_has_a_field() {
		global $wp_settings_fields;

		$sections = pchap_get_settings_sections();
		$fields   = pchap_get_settings_fields();

		$this->assertSameSets( array_keys( pchap_get_option_definitions() ), array_keys( $fields ) );

		foreach ( $fields as $option_name => $field ) {
			$this->assertArrayHasKey( $field['section'], $sections, $option_name );

			$definition = pchap_get_option_definition( $option_name );
			$page       = $sections[ $field['section'] ]['page'];
			$this->assertSame( $definition['group'], $page, "$option_name is on the screen of its settings group." );
			$this->assertArrayHasKey( $option_name, $wp_settings_fields[ $page ][ $field['section'] ] );
			$this->assertSame( $field['title'], $wp_settings_fields[ $page ][ $field['section'] ][ $option_name ]['title'] );
		}
	}

	/**
	 * Radio groups are labelled by their legend, the other fields by label_for.
	 */
	public function test_label_for() {
		global $wp_settings_fields;

		$this->assertArrayNotHasKey( 'label_for', $wp_settings_fields['paginova']['pchap_main']['pchap-comments-on-page']['args'] );
		$this->assertSame( 'hide-intro-title', $wp_settings_fields['paginova']['pchap_main']['pchap-hide-intro-title']['args']['label_for'] );
		$this->assertSame( 'rewrite-title-priority', $wp_settings_fields['paginova-advanced']['pchap_advanced']['pchap-rewrite-title-priority']['args']['label_for'] );
	}

	/**
	 * The fields show the saved values.
	 */
	public function test_fields_show_the_saved_values() {
		$this->set_options(
			array(
				'pchap-hide-intro-title'       => true,
				'pchap-comments-on-page'       => 'last-page',
				'pchap-toc-position'           => 'bottom',
				'pchap-rewrite-title-priority' => 50,
			)
		);

		$checkbox = $this->render( 'pchap_admin_render_option_field', array( array( 'option_name' => 'pchap-hide-intro-title' ) ) );
		$this->assertStringContainsString( 'name="pchap-hide-intro-title"', $checkbox );
		$this->assertStringContainsString( "checked='checked'", $checkbox );

		$radio = $this->render( 'pchap_admin_render_option_field', array( array( 'option_name' => 'pchap-comments-on-page' ) ) );
		$this->assertMatchesRegularExpression( '/value="last-page"\s+checked=\'checked\'/', $radio );
		$this->assertStringContainsString( '<legend class="screen-reader-text"><span>Display comments on</span></legend>', $radio );

		$select = $this->render( 'pchap_admin_render_option_field', array( array( 'option_name' => 'pchap-toc-position' ) ) );
		$this->assertMatchesRegularExpression( '/<option value="bottom"\s+selected=\'selected\'>/', $select );

		$priority = $this->render( 'pchap_admin_render_option_field', array( array( 'option_name' => 'pchap-rewrite-title-priority' ) ) );
		$this->assertMatchesRegularExpression( '/<option value="50"\s+selected=\'selected\'>/', $priority );
		$this->assertStringContainsString( 'aria-describedby="rewrite-title-priority-description"', $priority );
		$this->assertStringContainsString( '<p id="rewrite-title-priority-description" class="description">', $priority );
	}

	/**
	 * The description of the content priority keeps its code and link markup.
	 */
	public function test_description_markup() {
		$output = $this->render( 'pchap_admin_render_option_field', array( array( 'option_name' => 'pchap-rewrite-content-priority' ) ) );

		$this->assertStringContainsString( '<code><a href="https://developer.wordpress.org/reference/hooks/the_content/" target="_blank">the_content</a></code>', $output );
	}

	/**
	 * An unknown option renders nothing.
	 */
	public function test_unknown_option() {
		$this->assertSame( '', $this->render( 'pchap_admin_render_option_field', array( array( 'option_name' => 'nope' ) ) ) );
		$this->assertSame( '', $this->render( 'pchap_admin_render_option_field', array( array() ) ) );
	}

	/**
	 * Former field callbacks and their option.
	 *
	 * @return array
	 */
	public function data_deprecated_callbacks() {
		return array(
			array( 'pchap_admin_settings_callback_hide_intro_title', 'pchap-hide-intro-title' ),
			array( 'pchap_admin_settings_callback_comments_on_page', 'pchap-comments-on-page' ),
			array( 'pchap_admin_settings_callback_continue_or_prev_next', 'pchap-continue-or-prev-next' ),
			array( 'pchap_admin_settings_callback_disable_standard_pagination', 'pchap-disable-standard-pagination' ),
			array( 'pchap_admin_settings_callback_toc_only_on_the_first_page', 'pchap-toc-only-on-the-first-page' ),
			array( 'pchap_admin_settings_callback_toc_position', 'pchap-toc-position' ),
			array( 'pchap_admin_settings_callback_toc_row_labels', 'pchap-toc-row-labels' ),
			array( 'pchap_admin_settings_callback_hide_toc_header', 'pchap-hide-toc-header' ),
			array( 'pchap_admin_settings_callback_comments_toc_link', 'pchap-comments-toc-link' ),
			array( 'pchap_admin_advanced_callback_rewrite_title_priority', 'pchap-rewrite-title-priority' ),
			array( 'pchap_admin_advanced_callback_rewrite_content_priority', 'pchap-rewrite-content-priority' ),
			array( 'pchap_admin_advanced_callback_disable_tinymce_buttons', 'pchap-disable-tinymce-buttons' ),
		);
	}

	/**
	 * A former field callback is deprecated and outputs the generic field.
	 *
	 * @dataProvider data_deprecated_callbacks
	 *
	 * @param string $callback    Former callback.
	 * @param string $option_name Its option.
	 */
	public function test_deprecated_callback( $callback, $option_name ) {
		$this->setExpectedDeprecated( $callback );

		$this->assertSame(
			$this->render( 'pchap_admin_render_option_field', array( array( 'option_name' => $option_name ) ) ),
			$this->render( $callback )
		);
	}

	/**
	 * The "Rebuild chapter index" form posts to an admin-post action the plugin handles.
	 */
	public function test_rebuild_form_action_is_handled() {
		ob_start();
		pchap_admin_rebuild_postmeta_section();
		$html = ob_get_clean();

		$this->assertSame( 1, preg_match( '/name="action" value="([a-z_]+)"/', $html, $matches ) );
		$this->assertSame( 10, has_action( 'admin_post_' . $matches[1], 'pchap_admin_rebuild_postmeta_handler' ) );
	}
}
