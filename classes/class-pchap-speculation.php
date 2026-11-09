<?php
/**
 * Post Chapters speculative loading: the next chapter is fetched before the click.
 *
 * @package Post Chapters
 * @since 2.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Don't access directly.
}

/**
 * Adds a rule to the speculation rules of WordPress (6.8+): the links of the navigation and
 * of the table of contents of a chapter are prefetched when the visitor hovers or focuses
 * them, instead of on the click, which makes turning a page almost instant.
 *
 * WordPress prints speculation rules for visitors who are not logged in, on sites with
 * pretty permalinks; otherwise the rules of this class are not used. Available as
 * pchap()->speculation.
 *
 * @since 2.1.0
 */
class PChap_Speculation {

	/**
	 * The main plugin instance.
	 *
	 * @var PChap
	 */
	private $pchap;

	/**
	 * Constructor.
	 *
	 * @since 2.1.0
	 *
	 * @param PChap $pchap The main plugin instance.
	 */
	public function __construct( PChap $pchap ) {
		$this->pchap = $pchap;
	}

	/**
	 * Register the hooks of the current multipage view.
	 *
	 * @since 2.1.0
	 */
	public function register_hooks() {
		if ( class_exists( 'WP_Speculation_Rules' ) ) {
			add_action( 'wp_load_speculation_rules', array( $this, 'add_rules' ) );
		}
	}

	/**
	 * Add the rule of the chapter links.
	 *
	 * @since 2.1.0
	 *
	 * @param WP_Speculation_Rules $rules The speculation rules of WordPress.
	 */
	public function add_rules( $rules ) {
		if ( ! $rules instanceof WP_Speculation_Rules || ! $this->pchap->is_multipage_view() ) {
			return;
		}

		$rule = array(
			'source'    => 'document',
			'where'     => array(
				'selector_matches' => '.mpp-post-navigation a, .mpp-toc-container a',
			),
			'eagerness' => 'moderate',
		);

		/**
		 * Filters the speculation rule of the chapter links.
		 *
		 * Links with the CSS class `no-prefetch` are skipped by WordPress. Return an empty value
		 * to add no rule, or change `eagerness` (conservative: on press, moderate: on hover,
		 * eager: sooner).
		 *
		 * @since 2.1.0
		 *
		 * @param array $rule The rule: source, where and eagerness.
		 */
		$rule = pchap_apply_filters( 'pchap_speculation_rule', $rule );

		if ( empty( $rule ) || ! is_array( $rule ) ) {
			return;
		}

		/**
		 * Filters the speculation mode of the chapter links.
		 *
		 * @since 2.1.0
		 *
		 * @param string $mode 'prefetch' (the HTML of the next chapter) or 'prerender' (the whole page, scripts included).
		 */
		$mode = (string) pchap_apply_filters( 'pchap_speculation_mode', 'prefetch' );

		if ( ! WP_Speculation_Rules::is_valid_mode( $mode ) ) {
			$mode = 'prefetch';
		}

		$rules->add_rule( $mode, 'post-chapters', $rule );
	}
}
