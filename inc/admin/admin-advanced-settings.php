<?php
/**
 * Post Chapters Admin Advanced Settings.
 *
 * Fields of the advanced settings screen and the "Rebuild subpage index"
 * action. The settings are saved by the WordPress Settings API (options.php),
 * see PChap_Admin::register_admin_settings().
 *
 * @package Post Chapters
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
 */
function pchap_admin_advanced_callback_rewrite_title_priority() {
	pchap_admin_render_select_field(
		'_mpp-rewrite-title-priority',
		'rewrite-title-priority',
		pchap_get_rewrite_title_priority(),
		'rewrite-title-description'
	);
	?>
	<p id="rewrite-title-description" class="description"><?php esc_html_e( 'Some plugins need this higher in order to correctly show the chapter title instead of "Page # of #". If the title works good please leave this to normal.', 'post-chapters' ); ?></p>
	<?php
}

/**
 * Set the rewrite content priority.
 *
 * @since 1.4
 */
function pchap_admin_advanced_callback_rewrite_content_priority() {
	pchap_admin_render_select_field(
		'_mpp-rewrite-content-priority',
		'rewrite-content-priority',
		pchap_get_rewrite_content_priority(),
		'rewrite-content-description'
	);

	$description = sprintf(
		/* translators: %1$s: URL of the_content filter documentation. */
		__( 'This value affects the position where the table of contents is displayed, referring to other plugins that use <code><a href="%1$s" target="_blank">the_content</a></code> filter. e.g. above or below social buttons or related posts. If the table of contents position looks good please leave this to normal.', 'post-chapters' ),
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
function pchap_admin_advanced_callback_disable_tinymce_buttons() {
	pchap_admin_render_checkbox_field(
		'mpp-disable-tinymce-buttons',
		'disable-tinymce-buttons',
		pchap_disable_tinymce_buttons(),
		__( 'Disable TinyMCE Buttons', 'post-chapters' ),
		__( 'Disable the TinyMCE Chapter button in the classic editor. Leave unchecked unless you experience editor conflicts.', 'post-chapters' )
	);
}

/**
 * Former "Build Post Chapters postmetas" settings field.
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
			__( 'Last rebuilt: %s.', 'post-chapters' ),
			wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $built )
		);
	} elseif ( $built > 0 ) {
		$built_text = __( 'Last rebuilt: unknown date.', 'post-chapters' );
	} else {
		$built_text = __( 'The chapter index has never been rebuilt on this site.', 'post-chapters' );
	}
	?>
	<h2 id="mpp-subpage-index"><?php esc_html_e( 'Chapter index', 'post-chapters' ); ?></h2>

	<p><?php esc_html_e( 'Post Chapters stores the list of chapters of each post when the post is saved. Rebuild this index after an update, an import, or if the table of contents of a post looks out of date. Posts are processed in batches; on large sites the remaining posts are processed in the background.', 'post-chapters' ); ?></p>

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
						'post-chapters'
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
		<?php submit_button( __( 'Rebuild chapter index', 'post-chapters' ), 'secondary', 'mpp-rebuild-submit', false ); ?>
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
			esc_html__( 'Sorry, you are not allowed to manage options for this site.', 'post-chapters' ),
			'',
			array( 'response' => 403 )
		);
	}

	check_admin_referer( 'pchap_rebuild_postmeta' );

	$args = array( 'page' => 'post-chapters-advanced' );

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

	if ( 'post-chapters-advanced' !== $page || '' === $status ) {
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
				_n( '%s post checked', '%s posts checked', $processed, 'post-chapters' ),
				number_format_i18n( $processed )
			),
			sprintf(
				/* translators: %s: number of posts whose chapter index was updated. */
				_n( '%s post updated', '%s posts updated', $updated, 'post-chapters' ),
				number_format_i18n( $updated )
			),
			sprintf(
				/* translators: %s: number of stale chapter indexes removed. */
				_n( '%s stale entry removed', '%s stale entries removed', $deleted, 'post-chapters' ),
				number_format_i18n( $deleted )
			),
		);
	}

	switch ( $status ) {
		case 'done':
			$type    = 'success';
			$message = vsprintf(
				/* translators: 1: count of posts checked, such as 12 posts checked. 2: count of posts updated, such as 3 posts updated. 3: count of stale entries removed, such as 1 stale entry removed. */
				__( 'Chapter index rebuilt: %1$s, %2$s, %3$s.', 'post-chapters' ),
				$counts
			);
			break;

		case 'partial':
			$type    = 'info';
			$message = vsprintf(
				/* translators: 1: count of posts checked, such as 12 posts checked. 2: count of posts updated, such as 3 posts updated. 3: count of stale entries removed, such as 1 stale entry removed. */
				__( 'Chapter index rebuild started: %1$s, %2$s, %3$s so far. The remaining posts are processed in the background.', 'post-chapters' ),
				$counts
			);
			break;

		case 'locked':
			$type    = 'warning';
			$message = __( 'A chapter index rebuild is already running. Please try again in a few minutes.', 'post-chapters' );
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

		<h1><?php esc_html_e( 'Post Chapters Settings', 'post-chapters' ); ?></h1>
		<hr class="wp-header-end" />

		<nav class="nav-tab-wrapper wp-clearfix" aria-label="<?php esc_attr_e( 'Post Chapters settings sections', 'post-chapters' ); ?>"><?php pchap_admin_tabs( __( 'Advanced', 'post-chapters' ) ); ?></nav>

		<h2><?php esc_html_e( 'Advanced Settings', 'post-chapters' ); ?></h2>

		<p><?php esc_html_e( 'Please leave this settings to their default values, change only if you really know what to do.', 'post-chapters' ); ?></p>

		<form action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>" method="post">

			<?php settings_fields( 'post-chapters-advanced' ); ?>

			<?php do_settings_sections( 'post-chapters-advanced' ); ?>

			<?php submit_button( __( 'Save Settings', 'post-chapters' ) ); ?>
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
