<?php
/**
 * Paginova Admin Advanced Settings.
 *
 * Fields of the advanced settings screen and the "Rebuild subpage index"
 * action. The settings are saved by the WordPress Settings API (options.php),
 * see PChap_Admin::register_admin_settings().
 *
 * @package Paginova
 * @since 1.4
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Main settings section description for the advanced settings page.
 *
 * @since 1.4
 */
function pchap_admin_advanced_settings_callback_main_section() { }

/**
 * Set the rewrite title priority.
 *
 * @since 1.4
 * @deprecated 2.1.0 Use pchap_admin_render_option_field().
 */
function pchap_admin_advanced_callback_rewrite_title_priority() {
	_deprecated_function( __FUNCTION__, '2.1.0', 'pchap_admin_render_option_field()' );

	pchap_admin_render_option_field( array( 'option_name' => '_mpp-rewrite-title-priority' ) );
}

/**
 * Set the rewrite content priority.
 *
 * @since 1.4
 * @deprecated 2.1.0 Use pchap_admin_render_option_field().
 */
function pchap_admin_advanced_callback_rewrite_content_priority() {
	_deprecated_function( __FUNCTION__, '2.1.0', 'pchap_admin_render_option_field()' );

	pchap_admin_render_option_field( array( 'option_name' => '_mpp-rewrite-content-priority' ) );
}

/**
 * Disable the TinyMCE buttons (classic editor).
 *
 * @since 1.4
 * @deprecated 2.1.0 Use pchap_admin_render_option_field().
 */
function pchap_admin_advanced_callback_disable_tinymce_buttons() {
	_deprecated_function( __FUNCTION__, '2.1.0', 'pchap_admin_render_option_field()' );

	pchap_admin_render_option_field( array( 'option_name' => 'mpp-disable-tinymce-buttons' ) );
}

/**
 * Former "Build Paginova postmetas" settings field.
 *
 * @since 1.4
 * @deprecated 1.7.0 Replaced by the "Rebuild subpage index" action, see pchap_admin_rebuild_postmeta_section().
 */
function pchap_admin_advanced_callback_build_mpp_postmeta_data() {
	_deprecated_function( __FUNCTION__, '1.7.0', 'pchap_admin_rebuild_postmeta_section' );
}

/** Subpage index rebuild *****************************************************/

/**
 * Output the "Subpage index" section of the advanced settings page.
 *
 * Must be output outside of the settings form (it has its own form).
 *
 * @since 1.7.0
 */
function pchap_admin_rebuild_postmeta_section() {
	$built = (int) get_option( '_mpp-postmeta-built', 0 );
	$state = pchap_get_postmeta_rebuild_state();

	// Self-healing: a pending rebuild must always have a scheduled event.
	if ( false !== $state ) {
		pchap_ensure_postmeta_rebuild_event();
	}

	if ( $built > DAY_IN_SECONDS ) {
		$built_text = sprintf(
			/* translators: %s: date and time of the last rebuild. */
			__( 'Last rebuilt: %s.', 'paginova' ),
			wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $built )
		);
	} elseif ( $built > 0 ) {
		$built_text = __( 'Last rebuilt: unknown date.', 'paginova' );
	} else {
		$built_text = __( 'The chapter index has never been rebuilt on this site.', 'paginova' );
	}
	?>
	<h2 id="mpp-subpage-index"><?php esc_html_e( 'Chapter index', 'paginova' ); ?></h2>

	<p><?php esc_html_e( 'Paginova stores the list of chapters of each post when the post is saved. Rebuild this index after an update, an import, or if the table of contents of a post looks out of date. Posts are processed in batches; on large sites the remaining posts are processed in the background.', 'paginova' ); ?></p>

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
						'paginova'
					),
					number_format_i18n( (int) $state['processed'] )
				)
			);
			?>
		</p>
	<?php endif; ?>

	<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" class="mpp-rebuild-form">
		<input type="hidden" name="action" value="pchap_rebuild_postmeta" />
		<?php wp_nonce_field( 'pchap_rebuild_postmeta' ); ?>
		<?php submit_button( __( 'Rebuild chapter index', 'paginova' ), 'secondary', 'mpp-rebuild-submit', false ); ?>
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
function pchap_admin_rebuild_postmeta_handler() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die(
			esc_html__( 'Sorry, you are not allowed to manage options for this site.', 'paginova' ),
			'',
			array( 'response' => 403 )
		);
	}

	check_admin_referer( 'pchap_rebuild_postmeta' );

	$args = array( 'page' => 'paginova-advanced' );

	if ( ! pchap_rebuild_acquire_lock() ) {
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
		$time_limit = (int) pchap_apply_filters( 'pchap_rebuild_admin_time_limit', 15 );

		try {
			$stats = pchap_run_postmeta_rebuild( array( 'time_limit' => max( 1, $time_limit ) ) );

			if ( $stats['lock_lost'] ) {
				// Another process took the rebuild over: it owns the state now.
				$args['mpp-rebuild'] = 'locked';
			} elseif ( $stats['done'] ) {
				pchap_complete_postmeta_rebuild( $stats );
				$args['mpp-rebuild'] = 'done';
			} else {
				pchap_save_postmeta_rebuild_state( $stats );
				wp_clear_scheduled_hook( pchap_get_rebuild_cron_hook() );
				pchap_ensure_postmeta_rebuild_event();
				$args['mpp-rebuild'] = 'partial';
			}
		} finally {
			pchap_rebuild_release_lock();
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
function pchap_admin_rebuild_postmeta_notice() {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Display only.
	$page   = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
	$status = isset( $_GET['mpp-rebuild'] ) ? sanitize_key( wp_unslash( $_GET['mpp-rebuild'] ) ) : '';

	if ( 'paginova-advanced' !== $page || '' === $status ) {
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
				_n( '%s post checked', '%s posts checked', $processed, 'paginova' ),
				number_format_i18n( $processed )
			),
			sprintf(
				/* translators: %s: number of posts whose chapter index was updated. */
				_n( '%s post updated', '%s posts updated', $updated, 'paginova' ),
				number_format_i18n( $updated )
			),
			sprintf(
				/* translators: %s: number of stale chapter indexes removed. */
				_n( '%s stale entry removed', '%s stale entries removed', $deleted, 'paginova' ),
				number_format_i18n( $deleted )
			),
		);
	}

	switch ( $status ) {
		case 'done':
			$type    = 'success';
			$message = vsprintf(
				/* translators: 1: count of posts checked, such as 12 posts checked. 2: count of posts updated, such as 3 posts updated. 3: count of stale entries removed, such as 1 stale entry removed. */
				__( 'Chapter index rebuilt: %1$s, %2$s, %3$s.', 'paginova' ),
				$counts
			);
			break;

		case 'partial':
			$type    = 'info';
			$message = vsprintf(
				/* translators: 1: count of posts checked, such as 12 posts checked. 2: count of posts updated, such as 3 posts updated. 3: count of stale entries removed, such as 1 stale entry removed. */
				__( 'Chapter index rebuild started: %1$s, %2$s, %3$s so far. The remaining posts are processed in the background.', 'paginova' ),
				$counts
			);
			break;

		case 'locked':
			$type    = 'warning';
			$message = __( 'A chapter index rebuild is already running. Please try again in a few minutes.', 'paginova' );
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
function pchap_admin_removable_query_args( $args ) {
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
function pchap_admin_advanced() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	?>

	<div class="wrap">

		<h1><?php esc_html_e( 'Paginova Settings', 'paginova' ); ?></h1>
		<hr class="wp-header-end" />

		<nav class="nav-tab-wrapper wp-clearfix" aria-label="<?php esc_attr_e( 'Paginova settings sections', 'paginova' ); ?>"><?php pchap_admin_tabs( __( 'Advanced', 'paginova' ) ); ?></nav>

		<h2><?php esc_html_e( 'Advanced Settings', 'paginova' ); ?></h2>

		<p><?php esc_html_e( 'Please leave this settings to their default values, change only if you really know what to do.', 'paginova' ); ?></p>

		<form action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>" method="post">

			<?php settings_fields( 'paginova-advanced' ); ?>

			<?php do_settings_sections( 'paginova-advanced' ); ?>

			<?php submit_button( __( 'Save Settings', 'paginova' ) ); ?>
		</form>

		<?php pchap_admin_rebuild_postmeta_section(); ?>

	</div><!-- .wrap -->

	<?php
}

/**
 * Save the advanced settings.
 *
 * Settings are now saved by the WordPress Settings API (options.php) and the
 * subpage index is rebuilt by pchap_admin_rebuild_postmeta_handler().
 *
 * @since 1.4
 * @deprecated 1.7.0 No longer used, settings are saved through options.php.
 */
function pchap_admin_advanced_settings_save() {
	_deprecated_function( __FUNCTION__, '1.7.0' );
}
