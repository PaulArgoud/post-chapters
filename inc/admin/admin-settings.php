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
 */
function pchap_admin_settings_callback_hide_intro_title() {
	pchap_admin_render_checkbox_field(
		'mpp-hide-intro-title',
		'hide-intro-title',
		pchap_hide_intro_title( false ),
		__( 'Hide the default intro title', 'post-chapters' )
	);
}

/**
 * Show the post comments on specific pages.
 *
 * @since 1.4
 */
function pchap_admin_settings_callback_comments_on_page() {
	pchap_admin_render_radio_field(
		'mpp-comments-on-page',
		'comments-on-page',
		pchap_get_comments_on_page(),
		__( 'Display comments on', 'post-chapters' )
	);
}

/**
 * Display the navigation type.
 *
 * @since 1.4
 */
function pchap_admin_settings_callback_continue_or_prev_next() {
	pchap_admin_render_select_field(
		'mpp-continue-or-prev-next',
		'continue-or-prev-next',
		pchap_get_continue_or_prev_next()
	);
}

/**
 * Disable the standard WordPress pagination on multipage posts.
 *
 * @since 1.4
 */
function pchap_admin_settings_callback_disable_standard_pagination() {
	pchap_admin_render_checkbox_field(
		'mpp-disable-standard-pagination',
		'disable-standard-pagination',
		pchap_disable_standard_pagination( true ),
		__( 'Disable the WordPress standard pagination', 'post-chapters' ),
		__( 'By default, WordPress displays a pagination with only numbers on posts split into pages. Uncheck this if you still want this standard pagination to be displayed.', 'post-chapters' )
	);
}

/**
 * Decide if the table of contents must be visible only on the first page of a multipage post.
 *
 * @since 1.4
 */
function pchap_admin_settings_callback_toc_only_on_the_first_page() {
	pchap_admin_render_checkbox_field(
		'mpp-toc-only-on-the-first-page',
		'toc-only-on-the-first-page',
		pchap_toc_only_on_the_first_page( false ),
		__( 'Display the table of contents only on the first page of the post', 'post-chapters' )
	);
}

/**
 * Set the table of contents position.
 *
 * @since 1.4
 */
function pchap_admin_settings_callback_toc_position() {
	pchap_admin_render_select_field(
		'mpp-toc-position',
		'toc-position',
		pchap_get_toc_position()
	);
}

/**
 * Set the table of contents row labels.
 *
 * @since 1.4
 */
function pchap_admin_settings_callback_toc_row_labels() {
	pchap_admin_render_radio_field(
		'mpp-toc-row-labels',
		'row-labels',
		pchap_get_toc_row_labels(),
		__( 'Row labels', 'post-chapters' )
	);
}

/**
 * Decide whether to hide the table of contents header.
 *
 * @since 1.4
 */
function pchap_admin_settings_callback_hide_toc_header() {
	pchap_admin_render_checkbox_field(
		'mpp-hide-toc-header',
		'hide-toc-header',
		pchap_hide_toc_header( false ),
		__( 'Hide the table of contents header', 'post-chapters' )
	);
}

/**
 * Decide if the table of contents must have a link for comments.
 *
 * @since 1.4
 */
function pchap_admin_settings_callback_comments_toc_link() {
	pchap_admin_render_checkbox_field(
		'mpp-comments-toc-link',
		'comments-toc-link',
		pchap_comments_toc_link( false ),
		__( 'Add a link for the comments inside the table of contents', 'post-chapters' ),
		__( 'If comments are enabled, this will display, inside the table of contents, a link for the comments list.', 'post-chapters' )
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
