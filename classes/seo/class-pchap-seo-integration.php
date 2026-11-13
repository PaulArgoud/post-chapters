<?php
/**
 * Base class of the SEO plugin integrations.
 *
 * @package Paginova
 * @since 2.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Don't access directly.
}

/**
 * An SEO plugin integration: makes the plugin describe each chapter by itself
 * (title, canonical and social URLs, robots, schema) instead of as a copy of the post.
 *
 * @since 2.1.0
 */
abstract class PChap_SEO_Integration {

	/**
	 * The main plugin instance.
	 *
	 * @var PChap
	 */
	protected $pchap;

	/**
	 * The SEO component.
	 *
	 * @var PChap_SEO
	 */
	protected $seo;

	/**
	 * Constructor.
	 *
	 * @param PChap     $pchap The main plugin instance.
	 * @param PChap_SEO $seo   The SEO component.
	 */
	public function __construct( PChap $pchap, PChap_SEO $seo ) {
		$this->pchap = $pchap;
		$this->seo   = $seo;
	}

	/**
	 * Whether the SEO plugin is active.
	 *
	 * @return bool
	 */
	abstract public function is_active();

	/**
	 * Register the hooks of the SEO plugin, on a multipage view.
	 */
	abstract public function register_hooks();
}
