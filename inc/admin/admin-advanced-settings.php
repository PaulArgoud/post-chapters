<?php
/**
 * Multipage Admin Advanced Settings.
 *
 * Fields of the advanced settings screen and the "Rebuild subpage index"
 * action. The settings are saved by the WordPress Settings API (options.php),
 * see MPP_Admin::register_admin_settings().
 *
 * @package Multipage
 * @since 1.4
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Main settings section description for the advanced settings page.
 *
 * @since 1.4
 */
function mpp_admin_advanced_settings_callback_main_section() { }

/**
 * Set the rewrite title priority.
 *
 * @since 1.4
 */
function mpp_admin_advanced_callback_rewrite_title_priority() {
	mpp_admin_render_select_field(
		'_mpp-rewrite-title-priority',
		'rewrite-title-priority',
		mpp_get_rewrite_title_priority(),
		'rewrite-title-description'
	);
	?>
	<p id="rewrite-title-description" class="description"><?php esc_html_e( 'Some plugins need this higher in order to correctly show the subpage title instead of "Page # of #". If the title works good please leave this to normal.', 'sgr-nextpage-titles' ); ?></p>
	<?php
}

/**
 * Set the rewrite content priority.
 *
 * @since 1.4
 */
function mpp_admin_advanced_callback_rewrite_content_priority() {
	mpp_admin_render_select_field(
		'_mpp-rewrite-content-priority',
		'rewrite-content-priority',
		mpp_get_rewrite_content_priority(),
		'rewrite-content-description'
	);

	$description = sprintf(
		/* translators: %1$s: URL of the_content filter documentation. */
		__( 'This value affects the position where the table of contents is displayed, referring to other plugins that use <code><a href="%1$s" target="_blank">the_content</a></code> filter. e.g. above or below social buttons or related posts. If the table of contents position looks good please leave this to normal.', 'sgr-nextpage-titles' ),
		esc_url( 'https://developer.wordpress.org/reference/hooks/the_content/' )
	);
	?>
	<p id="rewrite-content-description" class="description">
		<?php
		echo wp_kses(
			$description,
			array(
				'code' => array(),
				'a'    => array(
					'href'   => true,
					'target' => true,
					'rel'    => true,
				),
			)
		);
		?>
	</p>
	<?php
}

/**
 * Disable the TinyMCE buttons (classic editor).
 *
 * @since 1.4
 */
function mpp_admin_advanced_callback_disable_tinymce_buttons() {
	mpp_admin_render_checkbox_field(
		'mpp-disable-tinymce-buttons',
		'disable-tinymce-buttons',
		mpp_disable_tinymce_buttons(),
		__( 'Disable TinyMCE Buttons', 'sgr-nextpage-titles' ),
		__( 'Disable the TinyMCE Subpage button in the classic editor. Leave unchecked unless you experience editor conflicts.', 'sgr-nextpage-titles' )
	);
}

/**
 * Former "Build Multipage postmetas" settings field.
 *
 * @since 1.4
 * @deprecated 1.7.0 Replaced by the "Rebuild subpage index" action, see mpp_admin_rebuild_postmeta_section().
 */
function mpp_admin_advanced_callback_build_mpp_postmeta_data() {
	_deprecated_function( __FUNCTION__, '1.7.0', 'mpp_admin_rebuild_postmeta_section' );
}

/** Subpage index rebuild *****************************************************/

/**
 * Output the "Subpage index" section of the advanced settings page.
 *
 * Must be output outside of the settings form (it has its own form).
 *
 * @since 1.7.0
 */
function mpp_admin_rebuild_postmeta_section() {
	$built = (int) get_option( '_mpp-postmeta-built', 0 );
	$state = mpp_get_postmeta_rebuild_state();

	// Self-healing: a pending rebuild must always have a scheduled event.
	if ( false !== $state ) {
		mpp_ensure_postmeta_rebuild_event();
	}

	if ( $built > DAY_IN_SECONDS ) {
		$built_text = sprintf(
			/* translators: %s: date and time of the last rebuild. */
			__( 'Last rebuilt: %s.', 'sgr-nextpage-titles' ),
			wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $built )
		);
	} elseif ( $built > 0 ) {
		$built_text = __( 'Last rebuilt: unknown date.', 'sgr-nextpage-titles' );
	} else {
		$built_text = __( 'The subpage index has never been rebuilt on this site.', 'sgr-nextpage-titles' );
	}
	?>
	<h2 id="mpp-subpage-index"><?php esc_html_e( 'Subpage index', 'sgr-nextpage-titles' ); ?></h2>

	<p><?php esc_html_e( 'Multipage stores the list of subpages of each post when the post is saved. Rebuild this index after an update, an import, or if the table of contents of a post looks out of date. Posts are processed in batches; on large sites the remaining posts are processed in the background.', 'sgr-nextpage-titles' ); ?></p>

	<p class="mpp-rebuild-status"><?php echo esc_html( $built_text ); ?></p>

	<?php if ( false !== $state ) : ?>
		<p class="mpp-rebuild-status mpp-rebuild-pending">
			<?php
			echo esc_html(
				sprintf(
					/* translators: %s: number of posts already checked. */
					_n(
						'A rebuild is in progress in the background: %s post checked so far.',
						'A rebuild is in progress in the background: %s posts checked so far.',
						(int) $state['processed'],
						'sgr-nextpage-titles'
					),
					number_format_i18n( (int) $state['processed'] )
				)
			);
			?>
		</p>
	<?php endif; ?>

	<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" class="mpp-rebuild-form">
		<input type="hidden" name="action" value="mpp_rebuild_postmeta" />
		<?php wp_nonce_field( 'mpp_rebuild_postmeta' ); ?>
		<?php submit_button( __( 'Rebuild subpage index', 'sgr-nextpage-titles' ), 'secondary', 'mpp-rebuild-submit', false ); ?>
	</form>
	<?php
}

/**
 * Handle the "Rebuild subpage index" action (admin-post.php).
 *
 * Processes batches for a limited time, hands the remaining posts over to
 * WP-Cron, then redirects back to the advanced settings page with the counts.
 *
 * @since 1.7.0
 */
function mpp_admin_rebuild_postmeta_handler() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die(
			esc_html__( 'Sorry, you are not allowed to manage options for this site.', 'sgr-nextpage-titles' ),
			'',
			array( 'response' => 403 )
		);
	}

	check_admin_referer( 'mpp_rebuild_postmeta' );

	$args = array( 'page' => 'mpp-advanced-settings' );

	if ( ! mpp_rebuild_acquire_lock() ) {
		$args['mpp-rebuild'] = 'locked';
	} else {

		/**
		 * Filters the time budget of the synchronous rebuild run from the admin, in seconds.
		 *
		 * The posts left when the budget is exceeded are processed in the background.
		 *
		 * @since 1.7.0
		 *
		 * @param int $time_limit Seconds. Default 15.
		 */
		$time_limit = (int) apply_filters( 'mpp_rebuild_admin_time_limit', 15 );

		try {
			$stats = mpp_run_postmeta_rebuild( array( 'time_limit' => max( 1, $time_limit ) ) );

			if ( $stats['lock_lost'] ) {
				// Another process took the rebuild over: it owns the state now.
				$args['mpp-rebuild'] = 'locked';
			} elseif ( $stats['done'] ) {
				mpp_complete_postmeta_rebuild( $stats );
				$args['mpp-rebuild'] = 'done';
			} else {
				mpp_save_postmeta_rebuild_state( $stats );
				wp_clear_scheduled_hook( mpp_get_rebuild_cron_hook() );
				mpp_ensure_postmeta_rebuild_event();
				$args['mpp-rebuild'] = 'partial';
			}
		} finally {
			mpp_rebuild_release_lock();
		}

		$args['mpp-processed'] = (int) $stats['processed'];
		$args['mpp-updated']   = (int) $stats['updated'];
		$args['mpp-deleted']   = (int) $stats['deleted'];
	}

	wp_safe_redirect( add_query_arg( $args, admin_url( 'options-general.php' ) ) );
	exit;
}

/**
 * Register the notice reporting the result of the "Rebuild subpage index" action.
 *
 * Added as a settings error: Settings sub-pages display them automatically
 * (wp-admin/options-head.php).
 *
 * @since 1.7.0
 */
function mpp_admin_rebuild_postmeta_notice() {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Display only.
	$page   = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
	$status = isset( $_GET['mpp-rebuild'] ) ? sanitize_key( wp_unslash( $_GET['mpp-rebuild'] ) ) : '';

	if ( 'mpp-advanced-settings' !== $page || '' === $status ) {
		return;
	}

	$processed = isset( $_GET['mpp-processed'] ) ? absint( $_GET['mpp-processed'] ) : 0;
	$updated   = isset( $_GET['mpp-updated'] ) ? absint( $_GET['mpp-updated'] ) : 0;
	$deleted   = isset( $_GET['mpp-deleted'] ) ? absint( $_GET['mpp-deleted'] ) : 0;
	// phpcs:enable

	if ( 'done' === $status || 'partial' === $status ) {
		// Each count is its own plural form; the sentences below assemble them.
		$counts = array(
			sprintf(
				/* translators: %s: number of posts checked. */
				_n( '%s post checked', '%s posts checked', $processed, 'sgr-nextpage-titles' ),
				number_format_i18n( $processed )
			),
			sprintf(
				/* translators: %s: number of posts whose subpage index was updated. */
				_n( '%s post updated', '%s posts updated', $updated, 'sgr-nextpage-titles' ),
				number_format_i18n( $updated )
			),
			sprintf(
				/* translators: %s: number of stale subpage indexes removed. */
				_n( '%s stale entry removed', '%s stale entries removed', $deleted, 'sgr-nextpage-titles' ),
				number_format_i18n( $deleted )
			),
		);
	}

	switch ( $status ) {
		case 'done':
			$type    = 'success';
			$message = vsprintf(
				/* translators: 1: count of posts checked, such as 12 posts checked. 2: count of posts updated, such as 3 posts updated. 3: count of stale entries removed, such as 1 stale entry removed. */
				__( 'Subpage index rebuilt: %1$s, %2$s, %3$s.', 'sgr-nextpage-titles' ),
				$counts
			);
			break;

		case 'partial':
			$type    = 'info';
			$message = vsprintf(
				/* translators: 1: count of posts checked, such as 12 posts checked. 2: count of posts updated, such as 3 posts updated. 3: count of stale entries removed, such as 1 stale entry removed. */
				__( 'Subpage index rebuild started: %1$s, %2$s, %3$s so far. The remaining posts are processed in the background.', 'sgr-nextpage-titles' ),
				$counts
			);
			break;

		case 'locked':
			$type    = 'warning';
			$message = __( 'A subpage index rebuild is already running. Please try again in a few minutes.', 'sgr-nextpage-titles' );
			break;

		default:
			return;
	}

	add_settings_error( 'mpp-rebuild', 'mpp-rebuild-' . $status, $message, $type );
}

/**
 * Remove the rebuild result arguments from the address bar after display.
 *
 * @since 1.7.0
 *
 * @param string[] $args Removable query arguments.
 * @return string[]
 */
function mpp_admin_removable_query_args( $args ) {
	return array_merge( (array) $args, array( 'mpp-rebuild', 'mpp-processed', 'mpp-updated', 'mpp-deleted' ) );
}

/** Advanced settings Page ****************************************************/

/**
 * The advanced settings page.
 *
 * Notices ("Settings saved.", validation errors, rebuild results) are
 * displayed automatically by wp-admin/options-head.php on Settings sub-pages.
 *
 * @since 1.4
 */
function mpp_admin_advanced() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	?>

	<div class="wrap">

		<h1><?php esc_html_e( 'Multipage Settings', 'sgr-nextpage-titles' ); ?></h1>
		<hr class="wp-header-end" />

		<nav class="nav-tab-wrapper wp-clearfix" aria-label="<?php esc_attr_e( 'Multipage settings sections', 'sgr-nextpage-titles' ); ?>"><?php mpp_admin_tabs( __( 'Advanced', 'sgr-nextpage-titles' ) ); ?></nav>

		<h2><?php esc_html_e( 'Advanced Settings', 'sgr-nextpage-titles' ); ?></h2>

		<p><?php esc_html_e( 'Please leave this settings to their default values, change only if you really know what to do.', 'sgr-nextpage-titles' ); ?></p>

		<form action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>" method="post">

			<?php settings_fields( 'mpp-advanced-settings' ); ?>

			<?php do_settings_sections( 'mpp-advanced-settings' ); ?>

			<?php submit_button( __( 'Save Settings', 'sgr-nextpage-titles' ) ); ?>
		</form>

		<?php mpp_admin_rebuild_postmeta_section(); ?>

	</div><!-- .wrap -->

	<?php
}

/**
 * Save the advanced settings.
 *
 * Settings are now saved by the WordPress Settings API (options.php) and the
 * subpage index is rebuilt by mpp_admin_rebuild_postmeta_handler().
 *
 * @since 1.4
 * @deprecated 1.7.0 No longer used, settings are saved through options.php.
 */
function mpp_admin_advanced_settings_save() {
	_deprecated_function( __FUNCTION__, '1.7.0' );
}
