<?php
/**
 * Add Internationalization to Post Chapters TinyMCE Plugin.
 *
 * This file is based on wp-includes/js/tinymce/langs/wp-langs.php
 *
 * Not loaded by the plugin anymore: the TinyMCE strings are added through
 * the 'wp_mce_translation' filter (PChap_Admin::pchap_wp_mce_translation()).
 * Kept for backward compatibility only.
 *
 * @package Post Chapters
 * @since 1.3
 * @deprecated 1.5 Use PChap_Admin::pchap_wp_mce_translation().
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( '_WP_Editors' ) ) {
	require ABSPATH . WPINC . '/class-wp-editor.php';
}

if ( ! function_exists( 'multipage_tinymce_plugin_translation' ) ) {
	/**
	 * Build the legacy TinyMCE translation script.
	 *
	 * @since 1.3
	 * @deprecated 1.5
	 *
	 * @return string JavaScript adding the strings to tinyMCE.
	 */
	function multipage_tinymce_plugin_translation() {
		$strings = array(
			'new_subpage'         => __( 'Start a new chapter', 'post-chapters' ),
			'enter_subpage_title' => __( 'Enter the chapter title', 'post-chapters' ),
		);

		$locale     = _WP_Editors::$mce_locale;
		$translated = 'tinyMCE.addI18n(' . wp_json_encode( $locale . '.multipage_tinymce_plugin' ) . ', ' . wp_json_encode( $strings ) . ");\n";

		return $translated;
	}
}

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- TinyMCE "external_languages" files must set $strings, read by _WP_Editors.
$strings = multipage_tinymce_plugin_translation();
