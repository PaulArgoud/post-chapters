<?php
/**
 * Script dependencies and version for the Subpage block editor script.
 *
 * Read by register_block_type() for the block.json "editorScript" field.
 *
 * @package Post Chapters
 * @since 1.7.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

return array(
	'dependencies' => array(
		'wp-block-editor',
		'wp-blocks',
		'wp-element',
		'wp-i18n',
		'wp-primitives',
	),
	'version'      => defined( 'PCHAP_VERSION' ) ? PCHAP_VERSION : '2.0.0',
);
