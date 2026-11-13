<?php
/**
 * Main Paginova Admin Class.
 *
 * @package Paginova
 * @subpackage CoreAdministration
 * @since 1.4
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Setup Paginova Admin.
 *
 * @since 1.4
 */
function pchap_admin() {
	pchap()->admin = new PChap_Admin();
}
