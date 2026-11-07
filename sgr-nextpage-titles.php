<?php
/**
 * Plugin Name:       Multipage
 * Plugin URI:        https://wordpress.org/plugins/sgr-nextpage-titles/
 * Description:       Split your WordPress posts into multiple subpages, each with its own title and an automatic table of contents. Supports Gutenberg blocks, classic editor shortcodes, SEO-friendly markup, and customizable navigation.
 * Author:            Paul ARGOUD, based on an original idea by SGr33n (aka Sergio De Falco, Envire Web Solutions).
 * Version:           1.7.1
 * Author URI:        https://paul.argoud.net
 * Text Domain:       sgr-nextpage-titles
 * Domain Path:       /languages/
 * License:           GPL v3
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Requires at least: 6.3
 * Requires PHP:      7.4
 * Update URI:        https://github.com/PaulArgoud/sgr-nextpage-titles
 *
 * @package Multipage
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

define( 'MPP_VERSION', '1.7.1' );
define( 'MPP__PLUGIN_FILE', __FILE__ );

/**
 * The main function.
 *
 * @return Multipage Multipage instance.
 */
function multipage() {
	return Multipage::instance();
}

require_once plugin_dir_path( __FILE__ ) . 'class-mpp.php';

// Start.
multipage();
