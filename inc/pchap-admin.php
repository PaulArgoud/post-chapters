<?php
/**
 * Main Post Chapters Admin Class.
 *
 * @package Post Chapters
 * @subpackage CoreAdministration
 * @since 1.4
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Setup Post Chapters Admin.
 *
 * @since 1.4
 */
function pchap_admin() {
	pchap()->admin = new PChap_Admin();
}
