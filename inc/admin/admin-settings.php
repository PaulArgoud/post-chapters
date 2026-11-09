<?php
/**
 * Post Chapters Admin Settings.
 *
 * Fields of the main settings screen (Settings > Post Chapters). The settings are
 * saved by the WordPress Settings API (options.php), see
 * PChap_Admin::register_admin_settings().
 *
 * @package Post Chapters
 * @since 1.4
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/** Field helpers *************************************************************/

/**
 * Output a checkbox setting field.
 *
 * @since 1.7.0
 *
 * @param string $option_name The option name (input name).
 * @param string $input_id    The input id.
 * @param bool   $checked     Whether the box is checked.
 * @param string $label       The label displayed next to the checkbox.
 * @param string $description Optional. Help text displayed below the field.
 */
function pchap_admin_render_checkbox_field( $option_name, $input_id, $checked, $label, $description = '' ) {
	$description_id = $input_id . '-description';
	?>
	<input id="<?php echo esc_attr( $input_id ); ?>" name="<?php echo esc_attr( $option_name ); ?>" type="checkbox" value="1" <?php checked( (bool) $checked ); ?><?php echo '' !== $description ? ' aria-describedby="' . esc_attr( $description_id ) . '"' : ''; ?> />
	<label for="<?php echo esc_attr( $input_id ); ?>"><?php echo esc_html( $label ); ?></label>
	<?php if ( '' !== $description ) : ?>
		<p id="<?php echo esc_attr( $description_id ); ?>" class="description"><?php echo esc_html( $description ); ?></p>
	<?php endif; ?>
	<?php
}

/**
 * Output a radio group setting field, with choices from the option definitions.
 *
 * @since 1.7.0
 *
 * @param string $option_name The option name (input name).
 * @param string $fieldset_id The fieldset id.
 * @param string $current     The current value.
 * @param string $legend      The (screen reader) legend of the group.
 */
function pchap_admin_render_radio_field( $option_name, $fieldset_id, $current, $legend ) {
	$choices = pchap_get_option_choice_labels( $option_name );
	?>
	<fieldset id="<?php echo esc_attr( $fieldset_id ); ?>">
		<legend class="screen-reader-text"><span><?php echo esc_html( $legend ); ?></span></legend>
		<?php foreach ( $choices as $value => $label ) : ?>
			<label>
				<input type="radio" name="<?php echo esc_attr( $option_name ); ?>" value="<?php echo esc_attr( $value ); ?>" <?php checked( (string) $value, (string) $current ); ?> />
				<?php echo esc_html( $label ); ?>
			</label>
			<br />
		<?php endforeach; ?>
	</fieldset>
	<?php
}

/**
 * Output a select setting field, with choices from the option definitions.
 *
 * A current value that is valid but not part of the offered choices (e.g. a
 * custom priority) is kept as an extra option so saving does not change it.
 *
 * @since 1.7.0
 *
 * @param string $option_name    The option name (select name).
 * @param string $select_id      The select id.
 * @param mixed  $current        The current value.
 * @param string $description_id Optional. Id of the element describing the field.
 */
function pchap_admin_render_select_field( $option_name, $select_id, $current, $description_id = '' ) {
	$choices = pchap_get_option_choice_labels( $option_name );

	$values = array_map( 'strval', array_keys( $choices ) );

	if ( ! in_array( (string) $current, $values, true ) ) {
		$choices[ (string) $current ] = sprintf(
			/* translators: %s: custom value. */
			__( 'Custom (%s)', 'post-chapters' ),
			(string) $current
		);
	}
	?>
	<select id="<?php echo esc_attr( $select_id ); ?>" name="<?php echo esc_attr( $option_name ); ?>" class="<?php echo esc_attr( $select_id ); ?>"<?php echo '' !== $description_id ? ' aria-describedby="' . esc_attr( $description_id ) . '"' : ''; ?>>
		<?php foreach ( $choices as $value => $label ) : ?>
			<option value="<?php echo esc_attr( $value ); ?>" <?php selected( (string) $value, (string) $current ); ?>><?php echo esc_html( $label ); ?></option>
		<?php endforeach; ?>
	</select>
	<?php
}

/** Settings screens ***********************************************************/

/**
 * Sections of the settings screens.
 *
 * @since 2.1.0
 *
 * @return array Section id => array( 'page', 'title', 'callback' ).
 */
function pchap_get_settings_sections() {
	return array(
		'pchap_main'       => array(
			'page'     => 'post-chapters',
			'title'    => __( 'Main Settings', 'post-chapters' ),
			'callback' => 'pchap_admin_settings_callback_main_section',
		),
		'pchap_pagination' => array(
			'page'     => 'post-chapters',
			'title'    => __( 'Pagination', 'post-chapters' ),
			'callback' => 'pchap_admin_settings_callback_pagination_section',
		),
		'pchap_toc'        => array(
			'page'     => 'post-chapters',
			'title'    => __( 'Table of contents', 'post-chapters' ),
			'callback' => 'pchap_admin_settings_callback_toc_section',
		),
		'pchap_advanced'   => array(
			'page'     => 'post-chapters-advanced',
			'title'    => '',
			'callback' => 'pchap_admin_advanced_settings_callback_main_section',
		),
	);
}

/**
 * Fields of the settings screens, in display order: one per option of
 * pchap_get_option_definitions(), whose choices and labels come from there.
 *
 * Each field: 'section' (see pchap_get_settings_sections()), 'title' (row
 * header, also used in the invalid value notices), 'control' (checkbox, radio
 * or select), 'id' (input, fieldset or select id), 'label' (checkbox label or
 * radio group legend), 'description' (optional help text; may hold <code> and
 * links) and 'value' (getter called with the option default).
 *
 * @since 2.1.0
 *
 * @return array Option name => field.
 */
function pchap_get_settings_fields() {
	return array(
		'mpp-hide-intro-title'            => array(
			'section' => 'pchap_main',
			'title'   => __( 'Intro', 'post-chapters' ),
			'control' => 'checkbox',
			'id'      => 'hide-intro-title',
			'label'   => __( 'Hide the default intro title', 'post-chapters' ),
			'value'   => 'pchap_hide_intro_title',
		),
		'mpp-comments-on-page'            => array(
			'section' => 'pchap_main',
			'title'   => __( 'Display comments on', 'post-chapters' ),
			'control' => 'radio',
			'id'      => 'comments-on-page',
			'label'   => __( 'Display comments on', 'post-chapters' ),
			'value'   => 'pchap_get_comments_on_page',
		),
		'mpp-continue-or-prev-next'       => array(
			'section' => 'pchap_pagination',
			'title'   => __( 'Navigation type', 'post-chapters' ),
			'control' => 'select',
			'id'      => 'continue-or-prev-next',
			'value'   => 'pchap_get_continue_or_prev_next',
		),
		'mpp-disable-standard-pagination' => array(
			'section'     => 'pchap_pagination',
			'title'       => __( 'Disable the standard pagination', 'post-chapters' ),
			'control'     => 'checkbox',
			'id'          => 'disable-standard-pagination',
			'label'       => __( 'Disable the WordPress standard pagination', 'post-chapters' ),
			'description' => __( 'By default, WordPress displays a pagination with only numbers on posts split into pages. Uncheck this if you still want this standard pagination to be displayed.', 'post-chapters' ),
			'value'       => 'pchap_disable_standard_pagination',
		),
		'mpp-toc-only-on-the-first-page'  => array(
			'section' => 'pchap_toc',
			'title'   => __( 'Only on the first page', 'post-chapters' ),
			'control' => 'checkbox',
			'id'      => 'toc-only-on-the-first-page',
			'label'   => __( 'Display the table of contents only on the first page of the post', 'post-chapters' ),
			'value'   => 'pchap_toc_only_on_the_first_page',
		),
		'mpp-toc-position'                => array(
			'section' => 'pchap_toc',
			'title'   => __( 'Position', 'post-chapters' ),
			'control' => 'select',
			'id'      => 'toc-position',
			'value'   => 'pchap_get_toc_position',
		),
		'mpp-toc-row-labels'              => array(
			'section' => 'pchap_toc',
			'title'   => __( 'Row labels', 'post-chapters' ),
			'control' => 'radio',
			'id'      => 'row-labels',
			'label'   => __( 'Row labels', 'post-chapters' ),
			'value'   => 'pchap_get_toc_row_labels',
		),
		'mpp-hide-toc-header'             => array(
			'section' => 'pchap_toc',
			'title'   => __( 'Hide header', 'post-chapters' ),
			'control' => 'checkbox',
			'id'      => 'hide-toc-header',
			'label'   => __( 'Hide the table of contents header', 'post-chapters' ),
			'value'   => 'pchap_hide_toc_header',
		),
		'mpp-comments-toc-link'           => array(
			'section'     => 'pchap_toc',
			'title'       => __( 'Comments link', 'post-chapters' ),
			'control'     => 'checkbox',
			'id'          => 'comments-toc-link',
			'label'       => __( 'Add a link for the comments inside the table of contents', 'post-chapters' ),
			'description' => __( 'If comments are enabled, this will display, inside the table of contents, a link for the comments list.', 'post-chapters' ),
			'value'       => 'pchap_comments_toc_link',
		),
		'_mpp-rewrite-title-priority'     => array(
			'section'     => 'pchap_advanced',
			'title'       => __( 'Rewrite Title Priority', 'post-chapters' ),
			'control'     => 'select',
			'id'          => 'rewrite-title-priority',
			'description' => __( 'Some plugins need this higher in order to correctly show the chapter title instead of "Page # of #". If the title works good please leave this to normal.', 'post-chapters' ),
			'value'       => 'pchap_get_rewrite_title_priority',
		),
		'_mpp-rewrite-content-priority'   => array(
			'section'     => 'pchap_advanced',
			'title'       => __( 'Rewrite Content Priority', 'post-chapters' ),
			'control'     => 'select',
			'id'          => 'rewrite-content-priority',
			'description' => sprintf(
				/* translators: %1$s: URL of the_content filter documentation. */
				__( 'This value affects the position where the table of contents is displayed, referring to other plugins that use <code><a href="%1$s" target="_blank">the_content</a></code> filter. e.g. above or below social buttons or related posts. If the table of contents position looks good please leave this to normal.', 'post-chapters' ),
				esc_url( 'https://developer.wordpress.org/reference/hooks/the_content/' )
			),
			'value'       => 'pchap_get_rewrite_content_priority',
		),
		'mpp-disable-tinymce-buttons'     => array(
			'section'     => 'pchap_advanced',
			'title'       => __( 'Disable TinyMCE Buttons', 'post-chapters' ),
			'control'     => 'checkbox',
			'id'          => 'disable-tinymce-buttons',
			'label'       => __( 'Disable TinyMCE Buttons', 'post-chapters' ),
			'description' => __( 'Disable the TinyMCE Chapter button in the classic editor. Leave unchecked unless you experience editor conflicts.', 'post-chapters' ),
			'value'       => 'pchap_disable_tinymce_buttons',
		),
	);
}

/**
 * Output the field of an option, as described by pchap_get_settings_fields().
 *
 * Callback of add_settings_field().
 *
 * @since 2.1.0
 *
 * @param array $args {
 *     Field arguments.
 *
 *     @type string $option_name The option name.
 * }
 */
function pchap_admin_render_option_field( $args ) {
	$option_name = isset( $args['option_name'] ) ? (string) $args['option_name'] : '';
	$fields      = pchap_get_settings_fields();
	$definition  = pchap_get_option_definition( $option_name );

	if ( ! isset( $fields[ $option_name ] ) || empty( $definition ) ) {
		return;
	}

	$field       = $fields[ $option_name ];
	$value       = is_callable( $field['value'] ) ? call_user_func( $field['value'], $definition['default'] ) : pchap_get_option( $option_name, $definition['default'] );
	$label       = isset( $field['label'] ) ? (string) $field['label'] : '';
	$description = isset( $field['description'] ) ? (string) $field['description'] : '';

	if ( 'checkbox' === $field['control'] ) {
		pchap_admin_render_checkbox_field( $option_name, $field['id'], $value, $label, $description );
		return;
	}

	$description_id = '' !== $description ? $field['id'] . '-description' : '';

	if ( 'radio' === $field['control'] ) {
		pchap_admin_render_radio_field( $option_name, $field['id'], $value, $label );
	} else {
		pchap_admin_render_select_field( $option_name, $field['id'], $value, $description_id );
	}

	if ( '' !== $description_id ) {
		printf(
			'<p id="%1$s" class="description">%2$s</p>',
			esc_attr( $description_id ),
			wp_kses(
				$description,
				array(
					'code' => array(),
					'a'    => array(
						'href'   => true,
						'target' => true,
						'rel'    => true,
					),
				)
			)
		);
	}
}

/** Sections ******************************************************************/

/**
 * Main settings section description for the settings page.
 *
 * @since 1.4
 */
function pchap_admin_settings_callback_main_section() { }

/**
 * Pagination settings section description for the settings page.
 *
 * @since 1.4
 */
function pchap_admin_settings_callback_pagination_section() { }

/**
 * Table of contents settings section description for the settings page.
 *
 * @since 1.4
 */
function pchap_admin_settings_callback_toc_section() { }

/** Fields ********************************************************************/

/**
 * Hide the intro title.
 *
 * @since 1.4
 * @deprecated 2.1.0 Use pchap_admin_render_option_field().
 */
function pchap_admin_settings_callback_hide_intro_title() {
	_deprecated_function( __FUNCTION__, '2.1.0', 'pchap_admin_render_option_field()' );

	pchap_admin_render_option_field( array( 'option_name' => 'mpp-hide-intro-title' ) );
}

/**
 * Show the post comments on specific pages.
 *
 * @since 1.4
 * @deprecated 2.1.0 Use pchap_admin_render_option_field().
 */
function pchap_admin_settings_callback_comments_on_page() {
	_deprecated_function( __FUNCTION__, '2.1.0', 'pchap_admin_render_option_field()' );

	pchap_admin_render_option_field( array( 'option_name' => 'mpp-comments-on-page' ) );
}

/**
 * Display the navigation type.
 *
 * @since 1.4
 * @deprecated 2.1.0 Use pchap_admin_render_option_field().
 */
function pchap_admin_settings_callback_continue_or_prev_next() {
	_deprecated_function( __FUNCTION__, '2.1.0', 'pchap_admin_render_option_field()' );

	pchap_admin_render_option_field( array( 'option_name' => 'mpp-continue-or-prev-next' ) );
}

/**
 * Disable the standard WordPress pagination on multipage posts.
 *
 * @since 1.4
 * @deprecated 2.1.0 Use pchap_admin_render_option_field().
 */
function pchap_admin_settings_callback_disable_standard_pagination() {
	_deprecated_function( __FUNCTION__, '2.1.0', 'pchap_admin_render_option_field()' );

	pchap_admin_render_option_field( array( 'option_name' => 'mpp-disable-standard-pagination' ) );
}

/**
 * Decide if the table of contents must be visible only on the first page of a multipage post.
 *
 * @since 1.4
 * @deprecated 2.1.0 Use pchap_admin_render_option_field().
 */
function pchap_admin_settings_callback_toc_only_on_the_first_page() {
	_deprecated_function( __FUNCTION__, '2.1.0', 'pchap_admin_render_option_field()' );

	pchap_admin_render_option_field( array( 'option_name' => 'mpp-toc-only-on-the-first-page' ) );
}

/**
 * Set the table of contents position.
 *
 * @since 1.4
 * @deprecated 2.1.0 Use pchap_admin_render_option_field().
 */
function pchap_admin_settings_callback_toc_position() {
	_deprecated_function( __FUNCTION__, '2.1.0', 'pchap_admin_render_option_field()' );

	pchap_admin_render_option_field( array( 'option_name' => 'mpp-toc-position' ) );
}

/**
 * Set the table of contents row labels.
 *
 * @since 1.4
 * @deprecated 2.1.0 Use pchap_admin_render_option_field().
 */
function pchap_admin_settings_callback_toc_row_labels() {
	_deprecated_function( __FUNCTION__, '2.1.0', 'pchap_admin_render_option_field()' );

	pchap_admin_render_option_field( array( 'option_name' => 'mpp-toc-row-labels' ) );
}

/**
 * Decide whether to hide the table of contents header.
 *
 * @since 1.4
 * @deprecated 2.1.0 Use pchap_admin_render_option_field().
 */
function pchap_admin_settings_callback_hide_toc_header() {
	_deprecated_function( __FUNCTION__, '2.1.0', 'pchap_admin_render_option_field()' );

	pchap_admin_render_option_field( array( 'option_name' => 'mpp-hide-toc-header' ) );
}

/**
 * Decide if the table of contents must have a link for comments.
 *
 * @since 1.4
 * @deprecated 2.1.0 Use pchap_admin_render_option_field().
 */
function pchap_admin_settings_callback_comments_toc_link() {
	_deprecated_function( __FUNCTION__, '2.1.0', 'pchap_admin_render_option_field()' );

	pchap_admin_render_option_field( array( 'option_name' => 'mpp-comments-toc-link' ) );
}

/** Settings Page *************************************************************/

/**
 * The main settings page.
 *
 * Notices ("Settings saved.", validation errors) are displayed automatically
 * by wp-admin/options-head.php on Settings sub-pages.
 *
 * @since 1.4
 */
function pchap_admin_settings() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	?>

	<div class="wrap">

		<h1><?php esc_html_e( 'Post Chapters Settings', 'post-chapters' ); ?></h1>
		<hr class="wp-header-end" />

		<nav class="nav-tab-wrapper wp-clearfix" aria-label="<?php esc_attr_e( 'Post Chapters settings sections', 'post-chapters' ); ?>"><?php pchap_admin_tabs( __( 'Options', 'post-chapters' ) ); ?></nav>

		<form action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>" method="post">

			<?php settings_fields( 'post-chapters' ); ?>

			<?php do_settings_sections( 'post-chapters' ); ?>

			<?php submit_button( __( 'Save Settings', 'post-chapters' ) ); ?>
		</form>

	</div><!-- .wrap -->

	<?php
}

/**
 * Save the main settings.
 *
 * Settings are now saved by the WordPress Settings API (options.php).
 *
 * @since 1.4
 * @deprecated 1.7.0 No longer used, settings are saved through options.php.
 */
function pchap_admin_settings_save() {
	_deprecated_function( __FUNCTION__, '1.7.0' );
}
