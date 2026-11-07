<?php
/**
 * Multipage Admin Settings.
 *
 * Fields of the main settings screen (Settings > Multipage). The settings are
 * saved by the WordPress Settings API (options.php), see
 * MPP_Admin::register_admin_settings().
 *
 * @package Multipage
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
function mpp_admin_render_checkbox_field( $option_name, $input_id, $checked, $label, $description = '' ) {
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
function mpp_admin_render_radio_field( $option_name, $fieldset_id, $current, $legend ) {
	$choices = mpp_get_option_choice_labels( $option_name );
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
function mpp_admin_render_select_field( $option_name, $select_id, $current, $description_id = '' ) {
	$choices = mpp_get_option_choice_labels( $option_name );

	$values = array_map( 'strval', array_keys( $choices ) );

	if ( ! in_array( (string) $current, $values, true ) ) {
		$choices[ (string) $current ] = sprintf(
			/* translators: %s: custom value. */
			__( 'Custom (%s)', 'sgr-nextpage-titles' ),
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

/** Sections ******************************************************************/

/**
 * Main settings section description for the settings page.
 *
 * @since 1.4
 */
function mpp_admin_settings_callback_main_section() { }

/**
 * Pagination settings section description for the settings page.
 *
 * @since 1.4
 */
function mpp_admin_settings_callback_pagination_section() { }

/**
 * Table of contents settings section description for the settings page.
 *
 * @since 1.4
 */
function mpp_admin_settings_callback_toc_section() { }

/** Fields ********************************************************************/

/**
 * Hide the intro title.
 *
 * @since 1.4
 */
function mpp_admin_settings_callback_hide_intro_title() {
	mpp_admin_render_checkbox_field(
		'mpp-hide-intro-title',
		'hide-intro-title',
		mpp_hide_intro_title( false ),
		__( 'Hide the default intro title', 'sgr-nextpage-titles' )
	);
}

/**
 * Show the post comments on specific pages.
 *
 * @since 1.4
 */
function mpp_admin_settings_callback_comments_on_page() {
	mpp_admin_render_radio_field(
		'mpp-comments-on-page',
		'comments-on-page',
		mpp_get_comments_on_page(),
		__( 'Display comments on', 'sgr-nextpage-titles' )
	);
}

/**
 * Display the navigation type.
 *
 * @since 1.4
 */
function mpp_admin_settings_callback_continue_or_prev_next() {
	mpp_admin_render_select_field(
		'mpp-continue-or-prev-next',
		'continue-or-prev-next',
		mpp_get_continue_or_prev_next()
	);
}

/**
 * Disable the standard WordPress pagination on multipage posts.
 *
 * @since 1.4
 */
function mpp_admin_settings_callback_disable_standard_pagination() {
	mpp_admin_render_checkbox_field(
		'mpp-disable-standard-pagination',
		'disable-standard-pagination',
		mpp_disable_standard_pagination( true ),
		__( 'Disable the WordPress standard pagination', 'sgr-nextpage-titles' ),
		__( 'WordPress, by default, displays a pagination with only numbers on standard multipage posts. Please uncheck this if you still want the standard pagination to display on multipage posts.', 'sgr-nextpage-titles' )
	);
}

/**
 * Decide if the table of contents must be visible only on the first page of a multipage post.
 *
 * @since 1.4
 */
function mpp_admin_settings_callback_toc_only_on_the_first_page() {
	mpp_admin_render_checkbox_field(
		'mpp-toc-only-on-the-first-page',
		'toc-only-on-the-first-page',
		mpp_toc_only_on_the_first_page( false ),
		__( 'Display the table of contents only on the first page of the post', 'sgr-nextpage-titles' )
	);
}

/**
 * Set the table of contents position.
 *
 * @since 1.4
 */
function mpp_admin_settings_callback_toc_position() {
	mpp_admin_render_select_field(
		'mpp-toc-position',
		'toc-position',
		mpp_get_toc_position()
	);
}

/**
 * Set the table of contents row labels.
 *
 * @since 1.4
 */
function mpp_admin_settings_callback_toc_row_labels() {
	mpp_admin_render_radio_field(
		'mpp-toc-row-labels',
		'row-labels',
		mpp_get_toc_row_labels(),
		__( 'Row labels', 'sgr-nextpage-titles' )
	);
}

/**
 * Decide whether to hide the table of contents header.
 *
 * @since 1.4
 */
function mpp_admin_settings_callback_hide_toc_header() {
	mpp_admin_render_checkbox_field(
		'mpp-hide-toc-header',
		'hide-toc-header',
		mpp_hide_toc_header( false ),
		__( 'Hide the table of contents header', 'sgr-nextpage-titles' )
	);
}

/**
 * Decide if the table of contents must have a link for comments.
 *
 * @since 1.4
 */
function mpp_admin_settings_callback_comments_toc_link() {
	mpp_admin_render_checkbox_field(
		'mpp-comments-toc-link',
		'comments-toc-link',
		mpp_comments_toc_link( false ),
		__( 'Add a link for the comments inside the table of contents', 'sgr-nextpage-titles' ),
		__( 'If comments are enabled, this will display, inside the table of contents, a link for the comments list.', 'sgr-nextpage-titles' )
	);
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
function mpp_admin_settings() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	?>

	<div class="wrap">

		<h1><?php esc_html_e( 'Multipage Settings', 'sgr-nextpage-titles' ); ?></h1>
		<hr class="wp-header-end" />

		<nav class="nav-tab-wrapper wp-clearfix" aria-label="<?php esc_attr_e( 'Multipage settings sections', 'sgr-nextpage-titles' ); ?>"><?php mpp_admin_tabs( __( 'Options', 'sgr-nextpage-titles' ) ); ?></nav>

		<form action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>" method="post">

			<?php settings_fields( 'mpp-settings' ); ?>

			<?php do_settings_sections( 'multipage' ); ?>

			<?php submit_button( __( 'Save Settings', 'sgr-nextpage-titles' ) ); ?>
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
function mpp_admin_settings_save() {
	_deprecated_function( __FUNCTION__, '1.7.0' );
}
