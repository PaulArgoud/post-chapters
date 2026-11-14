<?php
/**
 * Paginova Options.
 *
 * Single source of truth for the plugin settings: definitions (type, default,
 * allowed values, settings group), validation, sanitization and getters.
 *
 * @package Paginova
 * @subpackage Options
 * @since 1.4
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/** Definitions ***************************************************************/

/**
 * Return the definitions of every Paginova setting.
 *
 * Each definition is an array with the following keys:
 * - type:    'boolean', 'string' (value restricted to `choices`) or 'priority'
 *            (any integer; `choices` lists the values offered in the UI).
 * - default: Default value.
 * - choices: Allowed (string) or suggested (priority) values. Empty for booleans.
 * - group:   Settings API group the option is registered in.
 *
 * Definitions contain no translated strings, so they can be used at any time.
 * Use pchap_get_option_choice_labels() for the UI labels.
 *
 * @since 1.7.0
 *
 * @return array Option name => definition.
 */
function pchap_get_option_definitions() {
	$definitions = array(
		'pchap-hide-intro-title'            => array(
			'type'    => 'boolean',
			'default' => false,
			'choices' => array(),
			'group'   => 'paginova',
		),
		'pchap-comments-on-page'            => array(
			'type'    => 'string',
			'default' => 'all',
			'choices' => array( 'all', 'first-page', 'last-page' ),
			'group'   => 'paginova',
		),
		'pchap-continue-or-prev-next'       => array(
			'type'    => 'string',
			'default' => 'continue',
			'choices' => array( 'continue', 'next-previous', 'hidden' ),
			'group'   => 'paginova',
		),
		'pchap-disable-standard-pagination' => array(
			'type'    => 'boolean',
			'default' => true,
			'choices' => array(),
			'group'   => 'paginova',
		),
		'pchap-toc-only-on-the-first-page'  => array(
			'type'    => 'boolean',
			'default' => false,
			'choices' => array(),
			'group'   => 'paginova',
		),
		'pchap-toc-position'                => array(
			'type'    => 'string',
			'default' => 'top-right',
			'choices' => array( 'top-left', 'top-right', 'top', 'bottom', 'hidden' ),
			'group'   => 'paginova',
		),
		'pchap-toc-row-labels'              => array(
			'type'    => 'string',
			'default' => 'number',
			'choices' => array( 'number', 'page', 'hidden' ),
			'group'   => 'paginova',
		),
		'pchap-hide-toc-header'             => array(
			'type'    => 'boolean',
			'default' => false,
			'choices' => array(),
			'group'   => 'paginova',
		),
		'pchap-comments-toc-link'           => array(
			'type'    => 'boolean',
			'default' => false,
			'choices' => array(),
			'group'   => 'paginova',
		),
		'pchap-rewrite-title-priority'      => array(
			'type'    => 'priority',
			'default' => 20,
			'choices' => array( 100, 50, 20, 10, 5 ),
			'group'   => 'paginova-advanced',
		),
		'pchap-rewrite-content-priority'    => array(
			'type'    => 'priority',
			'default' => 20,
			'choices' => array( 100, 50, 20, 10, 5 ),
			'group'   => 'paginova-advanced',
		),
		'pchap-disable-tinymce-buttons'     => array(
			'type'    => 'boolean',
			'default' => false,
			'choices' => array(),
			'group'   => 'paginova-advanced',
		),
	);

	/**
	 * Filters the Paginova option definitions.
	 *
	 * Can be used to add allowed values (e.g. a custom table of contents
	 * position handled by a theme). Removing core options is not supported.
	 *
	 * @since 1.7.0
	 *
	 * @param array $definitions Option name => definition.
	 */
	$filtered = pchap_apply_filters( 'pchap_get_option_definitions', $definitions );

	if ( ! is_array( $filtered ) ) {
		return $definitions;
	}

	// Never let a filter drop or break a core definition.
	foreach ( $definitions as $name => $definition ) {
		if ( ! isset( $filtered[ $name ] ) || ! is_array( $filtered[ $name ] ) ) {
			$filtered[ $name ] = $definition;
			continue;
		}
		$filtered[ $name ] = array_merge( $definition, $filtered[ $name ] );
		if ( ! is_array( $filtered[ $name ]['choices'] ) ) {
			$filtered[ $name ]['choices'] = $definition['choices'];
		}
	}

	return $filtered;
}

/**
 * Return the definition of a single Paginova option.
 *
 * @since 1.7.0
 *
 * @param string $option_name The option name.
 * @return array|null The definition, or null if the option is unknown.
 */
function pchap_get_option_definition( $option_name ) {
	$definitions = pchap_get_option_definitions();

	return isset( $definitions[ $option_name ] ) ? $definitions[ $option_name ] : null;
}

/**
 * Return the translated labels of the choices offered for each option.
 *
 * Labels are built at call time so they are translated with the current
 * locale: call this function after the `init` action.
 *
 * @since 1.7.0
 *
 * @param string $option_name Optional. Return only the labels of this option.
 * @return array Option name => array( value => label ), or value => label when
 *               `$option_name` is given (empty array if unknown).
 */
function pchap_get_option_choice_labels( $option_name = '' ) {
	$priority_labels = array(
		100 => __( 'Highest', 'paginova' ),
		50  => __( 'High', 'paginova' ),
		20  => __( 'Normal', 'paginova' ),
		10  => __( 'Low', 'paginova' ),
		5   => __( 'Lowest', 'paginova' ),
	);

	$labels = array(
		'pchap-comments-on-page'         => array(
			'all'        => __( 'All pages — Display comments on all post pages', 'paginova' ),
			'first-page' => __( 'First page — Display comments only on the first page', 'paginova' ),
			'last-page'  => __( 'Last page — Display comments only on the last page', 'paginova' ),
		),
		'pchap-continue-or-prev-next'    => array(
			'continue'      => __( 'Continue or back to intro', 'paginova' ),
			'next-previous' => __( 'Next and previous', 'paginova' ),
			'hidden'        => __( 'Hidden', 'paginova' ),
		),
		'pchap-toc-position'             => array(
			'top-left'  => __( 'above the post content, align left', 'paginova' ),
			'top-right' => __( 'above the post content, align right', 'paginova' ),
			'top'       => __( 'above the post content', 'paginova' ),
			'bottom'    => __( 'below the post content', 'paginova' ),
			'hidden'    => __( 'hidden', 'paginova' ),
		),
		'pchap-toc-row-labels'           => array(
			'number' => __( 'number — Display numbers before the chapter title', 'paginova' ),
			'page'   => __( 'chapter # — Display "Chapter #" before the chapter title', 'paginova' ),
			'hidden' => __( 'hidden — Hide the labels, display only the chapter title', 'paginova' ),
		),
		'pchap-rewrite-title-priority'   => $priority_labels,
		'pchap-rewrite-content-priority' => $priority_labels,
	);

	// Make sure every allowed value has a label (e.g. values added by a filter).
	foreach ( pchap_get_option_definitions() as $name => $definition ) {
		if ( empty( $definition['choices'] ) ) {
			continue;
		}
		$option_labels = isset( $labels[ $name ] ) ? $labels[ $name ] : array();
		$ordered       = array();
		foreach ( $definition['choices'] as $value ) {
			$ordered[ $value ] = isset( $option_labels[ $value ] ) ? $option_labels[ $value ] : (string) $value;
		}
		$labels[ $name ] = $ordered;
	}

	/**
	 * Filters the labels of the choices displayed on the settings screens.
	 *
	 * @since 1.7.0
	 *
	 * @param array $labels Option name => array( value => label ).
	 */
	$labels = (array) pchap_apply_filters( 'pchap_get_option_choice_labels', $labels );

	if ( '' !== $option_name ) {
		return isset( $labels[ $option_name ] ) ? (array) $labels[ $option_name ] : array();
	}

	return $labels;
}

/** Validation & sanitization ************************************************/

/**
 * Convert a stored or submitted boolean setting to a real boolean.
 *
 * Like wp_validate_boolean(), with the usual "off"/"no" strings (any case,
 * surrounding spaces ignored) also read as false.
 *
 * @since 1.7.0
 * @access private
 *
 * @param mixed $value Scalar value.
 * @return bool
 */
function pchap_option_to_boolean( $value ) {
	if ( is_string( $value ) && in_array( strtolower( trim( $value ) ), array( '', '0', 'false', 'off', 'no' ), true ) ) {
		return false;
	}

	return wp_validate_boolean( $value );
}

/**
 * Check a value against an option definition.
 *
 * @since 1.7.0
 * @access private
 *
 * @param array $definition The option definition.
 * @param mixed $value      The value to check.
 * @param mixed $normalized Output. The normalized value when valid.
 * @return bool Whether the value is valid.
 */
function pchap_check_option_value( $definition, $value, &$normalized = null ) {
	switch ( $definition['type'] ) {
		case 'boolean':
			// Values stored by all the plugin versions: 1/0, '1'/'0', '' (unchecked), true/false.
			if ( is_bool( $value ) || is_int( $value ) || is_float( $value ) || is_string( $value ) ) {
				$normalized = pchap_option_to_boolean( $value );
				return true;
			}
			return false;

		case 'priority':
			if ( is_int( $value ) ) {
				$normalized = $value;
				return true;
			}
			if ( is_string( $value ) && 1 === preg_match( '/^-?\d+$/', trim( $value ) ) ) {
				$normalized = (int) $value;
				return true;
			}
			return false;

		case 'string':
		default:
			if ( ! is_string( $value ) && ! is_int( $value ) ) {
				return false;
			}
			$choices = array_map( 'strval', (array) $definition['choices'] );
			if ( in_array( (string) $value, $choices, true ) ) {
				$normalized = (string) $value;
				return true;
			}
			return false;
	}
}

/**
 * Validate an option value, falling back to a default when it is invalid.
 *
 * The fallback order is: `$fallback` (when valid), then the definition default.
 *
 * @since 1.7.0
 *
 * @param string $option_name The option name.
 * @param mixed  $value       The value to validate (usually the stored value).
 * @param mixed  $fallback    Optional. Preferred fallback value. Default null.
 * @return mixed The normalized valid value. Unknown options are returned untouched.
 */
function pchap_validate_option_value( $option_name, $value, $fallback = null ) {
	$definition = pchap_get_option_definition( $option_name );

	if ( null === $definition ) {
		return $value;
	}

	$normalized = null;
	if ( pchap_check_option_value( $definition, $value, $normalized ) ) {
		return $normalized;
	}

	if ( null !== $fallback && pchap_check_option_value( $definition, $fallback, $normalized ) ) {
		return $normalized;
	}

	pchap_check_option_value( $definition, $definition['default'], $normalized );
	return $normalized;
}

/**
 * Sanitize a Paginova option before it is saved (Settings API callback).
 *
 * Booleans are stored as 1/0 (an unchecked checkbox is posted as null by
 * options.php and stored as 0). Invalid values keep the current valid value
 * (or the default) and register a settings error.
 *
 * @since 1.7.0
 *
 * @param mixed  $value       The submitted value.
 * @param string $option_name The option name.
 * @return mixed The sanitized value.
 */
function pchap_sanitize_option( $value, $option_name ) {
	static $reported = array();

	$definition = pchap_get_option_definition( $option_name );

	if ( null === $definition ) {
		return is_scalar( $value ) ? sanitize_text_field( (string) $value ) : '';
	}

	if ( 'boolean' === $definition['type'] ) {
		if ( null === $value || '' === $value ) {
			return 0;
		}
		return ( is_scalar( $value ) && pchap_option_to_boolean( $value ) ) ? 1 : 0;
	}

	if ( is_string( $value ) ) {
		$value = sanitize_text_field( $value );
	}

	$normalized = null;
	if ( pchap_check_option_value( $definition, $value, $normalized ) ) {
		return $normalized;
	}

	// Invalid or missing value: keep the current valid value, or the default.
	$fallback = pchap_validate_option_value( $option_name, get_option( $option_name, $definition['default'] ) );

	if ( null !== $value && ! isset( $reported[ $option_name ] ) && function_exists( 'add_settings_error' ) ) {
		$reported[ $option_name ] = true;
		add_settings_error(
			$option_name,
			'pchap-invalid-' . $option_name,
			sprintf(
				/* translators: %s: setting label, as displayed on the settings screen (e.g. "Navigation type"). */
				__( 'An invalid value was submitted for the %s setting. The previous value was kept.', 'paginova' ),
				esc_html( pchap_get_option_field_label( $option_name ) )
			),
			'error'
		);
	}

	return $fallback;
}

/**
 * Return the label of an option as displayed on the settings screen.
 *
 * Reads the field title registered with add_settings_field() (the settings are
 * registered on admin_init, before options.php sanitizes the submitted values).
 * Falls back to the option name when the field is not registered, e.g. when the
 * option is updated outside of the settings screens.
 *
 * @since 1.7.0
 * @access private
 *
 * @global array $wp_settings_fields Registered settings fields.
 *
 * @param string $option_name The option name.
 * @return string Plain-text label (not escaped).
 */
function pchap_get_option_field_label( $option_name ) {
	global $wp_settings_fields;

	if ( is_array( $wp_settings_fields ) ) {
		foreach ( $wp_settings_fields as $sections ) {
			foreach ( (array) $sections as $fields ) {
				if ( ! isset( $fields[ $option_name ]['title'] ) || ! is_string( $fields[ $option_name ]['title'] ) ) {
					continue;
				}

				$label = trim( wp_strip_all_tags( $fields[ $option_name ]['title'] ) );
				if ( '' !== $label ) {
					return html_entity_decode( $label, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
				}
			}
		}
	}

	return (string) $option_name;
}

/** Cache *********************************************************************/

/**
 * Retrieve a plugin option with in-memory caching.
 *
 * Reduces repeated get_option() calls to a single DB hit per request. Only the
 * stored value is cached: the default is applied per call.
 *
 * @since 1.5.13
 *
 * @param string $option_name The option name (with the pchap- prefix).
 * @param mixed  $default     Default value if not set.
 * @return mixed The option value.
 */
function pchap_get_option( $option_name, $default = false ) {
	static $cache   = array();
	static $missing = null;

	if ( null === $missing ) {
		$missing = new stdClass();
	}

	// Allow cache reset via sentinel key.
	if ( '__clear_cache__' === $option_name ) {
		$cache = array();
		return null;
	}

	if ( ! array_key_exists( $option_name, $cache ) ) {
		$cache[ $option_name ] = get_option( $option_name, $missing );
	}

	return $missing === $cache[ $option_name ] ? $default : $cache[ $option_name ];
}

/**
 * Clear the in-memory options cache (useful after saving settings).
 *
 * @since 1.5.13
 */
function pchap_clear_options_cache() {
	pchap_get_option( '__clear_cache__' );
}

/**
 * Clear the in-memory options cache when a Paginova option changes.
 *
 * @since 1.7.0
 *
 * @param string $option_name The option name.
 */
function pchap_maybe_clear_options_cache( $option_name ) {
	if ( is_string( $option_name ) && null !== pchap_get_option_definition( $option_name ) ) {
		pchap_clear_options_cache();
	}
}
add_action( 'added_option', 'pchap_maybe_clear_options_cache' );
add_action( 'updated_option', 'pchap_maybe_clear_options_cache' );
add_action( 'deleted_option', 'pchap_maybe_clear_options_cache' );

/**
 * Return the validated value of a Paginova option.
 *
 * @since 1.7.0
 * @access private
 *
 * @param string $option_name The option name.
 * @param mixed  $fallback    Fallback value used when the option is missing or invalid.
 * @return mixed The valid value.
 */
function pchap_get_valid_option( $option_name, $fallback ) {
	return pchap_validate_option_value( $option_name, pchap_get_option( $option_name, $fallback ), $fallback );
}

/** Getters *******************************************************************/

/**
 * Is the intro title hidden on multipage posts?
 *
 * @since 1.4
 *
 * @param bool $default Optional. Fallback value if not found in the database.
 *                      Default: false.
 * @return bool True if the intro title should be hidden, otherwise false.
 */
function pchap_hide_intro_title( $default = false ) {

	/**
	 * Filters whether or not the intro title is hidden.
	 *
	 * @since 1.4
	 *
	 * @param bool $value Whether the intro title is hidden.
	 */
	return (bool) pchap_apply_filters( 'pchap_hideintro_title', (bool) pchap_get_valid_option( 'pchap-hide-intro-title', (bool) $default ) );
}

/**
 * Output the pages where comments will appear.
 *
 * @since 1.4
 *
 * @param string $default Optional. Default: all.
 */
function pchap_comments_on_page( $default = 'all' ) {
	echo esc_html( pchap_get_comments_on_page( $default ) );
}

/**
 * Return the pages where to display comments.
 *
 * @since 1.4
 *
 * @param string $default Optional. Default: all.
 * @return string The pages where to display comments (all, first-page or last-page).
 */
function pchap_get_comments_on_page( $default = 'all' ) {

	/**
	 * Filters the pages where to display comments.
	 *
	 * @since 1.4
	 *
	 * @param string $value The pages where to display comments.
	 */
	return pchap_apply_filters( 'pchap_get_comments_on_page', pchap_get_valid_option( 'pchap-comments-on-page', $default ) );
}

/**
 * Output the navigation type (continue or next and previous).
 *
 * @since 1.4
 *
 * @param string $default Optional. Default: continue.
 */
function pchap_continue_or_prev_next( $default = 'continue' ) {
	echo esc_html( pchap_get_continue_or_prev_next( $default ) );
}

/**
 * Return the navigation type (continue or next and previous).
 *
 * @since 1.4
 *
 * @param string $default Optional. Default: continue.
 * @return string The navigation type (continue, next-previous or hidden).
 */
function pchap_get_continue_or_prev_next( $default = 'continue' ) {

	/**
	 * Filters the navigation type.
	 *
	 * @since 1.4
	 *
	 * @param string $value The navigation type.
	 */
	return pchap_apply_filters( 'pchap_get_continue_or_prev_next', pchap_get_valid_option( 'pchap-continue-or-prev-next', $default ) );
}

/**
 * Is the standard WordPress pagination disabled on multipage posts?
 *
 * @since 1.4
 *
 * @param bool $default Optional. Fallback value if not found in the database.
 *                      Default: true.
 * @return bool true if the standard WordPress pagination should be hidden otherwise false.
 */
function pchap_disable_standard_pagination( $default = true ) {

	/**
	 * Filters whether or not the standard WordPress pagination is disabled.
	 *
	 * @since 1.4
	 *
	 * @param bool $value Whether the standard pagination is disabled.
	 */
	return (bool) pchap_apply_filters( 'pchap_disable_standard_pagination', (bool) pchap_get_valid_option( 'pchap-disable-standard-pagination', (bool) $default ) );
}

/**
 * Is the table of contents only on the first page of the post?
 *
 * @since 1.4
 *
 * @param bool $default Optional. Fallback value if not found in the database.
 *                      Default: false.
 * @return bool true if the table of contents should be only on the first page of the post otherwise false.
 */
function pchap_toc_only_on_the_first_page( $default = false ) {

	/**
	 * Filters whether or not the table of contents is displayed only on the first page.
	 *
	 * @since 1.4
	 *
	 * @param bool $value Whether the table of contents is only on the first page.
	 */
	return (bool) pchap_apply_filters( 'pchap_toc_only_on_the_first_page', (bool) pchap_get_valid_option( 'pchap-toc-only-on-the-first-page', (bool) $default ) );
}

/**
 * Output the table of contents position.
 *
 * @since 1.4
 *
 * @param string $default Optional. Default: top-right.
 */
function pchap_toc_position( $default = 'top-right' ) {
	echo esc_html( pchap_get_toc_position( $default ) );
}

/**
 * Return the table of contents position.
 *
 * @since 1.4
 *
 * @param string $default Optional. Default: top-right.
 * @return string The table of contents position (top-left, top-right, top, bottom or hidden).
 */
function pchap_get_toc_position( $default = 'top-right' ) {

	/**
	 * Filters the table of contents position.
	 *
	 * @since 1.4
	 *
	 * @param string $value The table of contents position.
	 */
	return pchap_apply_filters( 'pchap_get_toc_position', pchap_get_valid_option( 'pchap-toc-position', $default ) );
}

/**
 * Output the table of contents row labels type.
 *
 * @since 1.4
 *
 * @param string $default Optional. Default: number.
 */
function pchap_toc_row_labels( $default = 'number' ) {
	echo esc_html( pchap_get_toc_row_labels( $default ) );
}

/**
 * Return the table of contents row labels type.
 *
 * @since 1.4
 *
 * @param string $default Optional. Default: number.
 * @return string The table of contents row labels type (number, page or hidden).
 */
function pchap_get_toc_row_labels( $default = 'number' ) {

	/**
	 * Filters the table of contents row labels type.
	 *
	 * @since 1.4
	 *
	 * @param string $value The table of contents row labels type.
	 */
	return pchap_apply_filters( 'pchap_get_toc_row_labels', pchap_get_valid_option( 'pchap-toc-row-labels', $default ) );
}

/**
 * Is the table of contents header hidden?
 *
 * @since 1.4
 *
 * @param bool $default Optional. Fallback value if not found in the database.
 *                      Default: false.
 * @return bool true if the table of contents header should be hidden otherwise false.
 */
function pchap_hide_toc_header( $default = false ) {

	/**
	 * Filters whether or not the table of contents header is hidden.
	 *
	 * @since 1.4
	 *
	 * @param bool $value Whether the table of contents header is hidden.
	 */
	return (bool) pchap_apply_filters( 'pchap_hide_toc_header', (bool) pchap_get_valid_option( 'pchap-hide-toc-header', (bool) $default ) );
}

/**
 * Is the table of contents including a link for comments?
 *
 * @since 1.4
 *
 * @param bool $default Optional. Fallback value if not found in the database.
 *                      Default: false.
 * @return bool true if the table of contents is including a link for comments.
 */
function pchap_comments_toc_link( $default = false ) {

	/**
	 * Filters whether or not the table of contents is including a link for comments.
	 *
	 * @since 1.4
	 *
	 * @param bool $value Whether the table of contents is including a link for comments.
	 */
	return (bool) pchap_apply_filters( 'pchap_comments_toc_link', (bool) pchap_get_valid_option( 'pchap-comments-toc-link', (bool) $default ) );
}

/**
 * Output the rewrite titles priority.
 *
 * @since 1.4
 *
 * @param int $default Optional. Default: 20 (normal).
 */
function pchap_rewrite_title_priority( $default = 20 ) {
	echo (int) pchap_get_rewrite_title_priority( $default );
}

/**
 * Return the rewrite titles priority.
 *
 * @since 1.4
 *
 * @param int $default Optional. Default: 20 (normal).
 * @return int The rewrite titles priority.
 */
function pchap_get_rewrite_title_priority( $default = 20 ) {

	/**
	 * Filters the rewrite titles priority.
	 *
	 * @since 1.4
	 *
	 * @param int $value The rewrite titles priority.
	 */
	return (int) pchap_apply_filters( 'pchap_get_rewrite_title_priority', (int) pchap_get_valid_option( 'pchap-rewrite-title-priority', $default ) );
}

/**
 * Output the rewrite content priority.
 *
 * @since 1.4
 *
 * @param int $default Optional. Default: 20 (normal).
 */
function pchap_rewrite_content_priority( $default = 20 ) {
	echo (int) pchap_get_rewrite_content_priority( $default );
}

/**
 * Return the rewrite content priority.
 *
 * @since 1.4
 *
 * @param int $default Optional. Default: 20 (normal).
 * @return int The rewrite content priority.
 */
function pchap_get_rewrite_content_priority( $default = 20 ) {

	/**
	 * Filters the rewrite content priority.
	 *
	 * @since 1.4
	 *
	 * @param int $value The rewrite content priority.
	 */
	return (int) pchap_apply_filters( 'pchap_get_rewrite_content_priority', (int) pchap_get_valid_option( 'pchap-rewrite-content-priority', $default ) );
}

/**
 * Are the TinyMCE Buttons disabled?
 *
 * @since 1.4
 *
 * @param bool $default Optional. Fallback value if not found in the database.
 *                      Default: false.
 * @return bool true if the TinyMCE Buttons should be hidden otherwise false.
 */
function pchap_disable_tinymce_buttons( $default = false ) {

	/**
	 * Filters whether or not TinyMCE Buttons are disabled.
	 *
	 * @since 1.4
	 *
	 * @param bool $value Whether TinyMCE Buttons are disabled.
	 */
	return (bool) pchap_apply_filters( 'pchap_disable_tinymce_buttons', (bool) pchap_get_valid_option( 'pchap-disable-tinymce-buttons', (bool) $default ) );
}
