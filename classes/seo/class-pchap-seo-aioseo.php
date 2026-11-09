<?php
/**
 * All in One SEO integration.
 *
 * @package Post Chapters
 * @since 2.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Don't access directly.
}

/**
 * All in One SEO: titles and descriptions, robots, rel prev/next, schema WebPage URL.
 *
 * @since 2.1.0
 */
class PChap_SEO_AIOSEO extends PChap_SEO_Integration {

	/**
	 * Whether All in One SEO is active.
	 *
	 * @return bool
	 */
	public function is_active() {
		return function_exists( 'aioseo' );
	}

	/**
	 * Register the hooks.
	 */
	public function register_hooks() {
		add_filter( 'aioseo_title', array( $this, 'filter_title' ), 10, 1 );
		add_filter( 'aioseo_description', array( $this, 'filter_description' ), 10, 1 );
		add_filter( 'aioseo_robots_meta', array( $this, 'filter_robots_meta' ), 10, 1 );
		add_filter( 'aioseo_prev_link', array( $this, 'filter_adjacent_link' ), 10, 1 );
		add_filter( 'aioseo_next_link', array( $this, 'filter_adjacent_link' ), 10, 1 );
		add_filter( 'aioseo_schema_output', array( $this, 'filter_schema_output' ), 10, 1 );
	}

	/**
	 * Title: the chapter title replaces the "Page N" suffix of the paged format.
	 *
	 * @param string $title The title.
	 * @return string
	 */
	public function filter_title( $title ) {
		return $this->seo->ensure_chapter_title( $title );
	}

	/**
	 * Description: without the "Page N" suffix of the paged format (the description is the
	 * one of the post, and the title says which chapter it is).
	 *
	 * @param string $description The description.
	 * @return string
	 */
	public function filter_description( $description ) {
		return $this->seo->is_subpage_view() ? $this->seo->strip_page_number( $description ) : $description;
	}

	/**
	 * Robots of the chapters: AIOSEO adds noindex (and nofollow) to every "paginated page",
	 * by default, and a chapter after the first one counts as one. The chapters get the robots
	 * of the post itself instead, as the chapters of a post are pages to be found in search results.
	 *
	 * @param array $attributes The robots meta attributes.
	 * @return array
	 */
	public function filter_robots_meta( $attributes ) {
		if ( ! is_array( $attributes ) || ! $this->seo->is_subpage_view() ) {
			return $attributes;
		}

		if ( empty( $attributes['noindex'] ) && empty( $attributes['nofollow'] ) ) {
			return $attributes;
		}

		$post_robots = $this->get_post_robots( get_post( $this->pchap->post_id ) );
		if ( null === $post_robots ) {
			return $attributes;
		}

		$attributes['noindex']  = $post_robots['noindex'] ? 'noindex' : '';
		$attributes['nofollow'] = $post_robots['nofollow'] ? 'nofollow' : '';

		return $attributes;
	}

	/**
	 * Drop the rel prev/next of AIOSEO on the multipage post (PChap_SEO::print_rel_links() prints them).
	 *
	 * @param mixed $link The link.
	 * @return mixed
	 */
	public function filter_adjacent_link( $link ) {
		return $this->pchap->is_multipage_view() ? '' : $link;
	}

	/**
	 * Schema WebPage `url` of subpages >= 2: the subpage, not the post.
	 *
	 * @param array $graph The schema graph.
	 * @return array
	 */
	public function filter_schema_output( $graph ) {
		if ( ! is_array( $graph ) ) {
			return $graph;
		}

		foreach ( $graph as $index => $piece ) {
			if ( is_array( $piece ) && isset( $piece['@type'], $piece['url'] ) && 'WebPage' === $piece['@type'] ) {
				$graph[ $index ]['url'] = $this->seo->maybe_chapter_url( $piece['url'] );
			}
		}

		return $graph;
	}

	/**
	 * Robots (noindex, nofollow) that AIOSEO gives to a post, without any pagination rule:
	 * the settings of the post, else those of its post type, else the global ones.
	 *
	 * @param WP_Post|null $post The post.
	 * @return bool[]|null Array( 'noindex' => bool, 'nofollow' => bool ), or null when unknown.
	 */
	private function get_post_robots( $post ) {
		if ( ! $post instanceof WP_Post || ! function_exists( 'aioseo' ) ) {
			return null;
		}

		try {
			$meta = aioseo()->meta->metaData->getMetaData( $post );
			if ( ! empty( $meta ) && ! $meta->robots_default ) {
				return array(
					'noindex'  => (bool) $meta->robots_noindex,
					'nofollow' => (bool) $meta->robots_nofollow,
				);
			}

			// Same traversal as AIOSEO's own Meta\Robots::globalValues(): its option groups
			// are stateful, and a camelCase property name is theirs.
			// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			$global = aioseo()->options->noConflict()->searchAppearance->advanced->globalRobotsMeta->all();
			$robots = $global;

			$options = aioseo()->dynamicOptions->noConflict( true )->searchAppearance;
			if ( $options->has( 'postTypes', false ) ) {
				$options = $options->postTypes;
				if ( $options->has( $post->post_type, false ) ) {
					$options = $options->{$post->post_type};

					$cloned = clone $options;
					if ( ! $cloned->show ) {
						return array(
							'noindex'  => true,
							'nofollow' => ! empty( $global['nofollow'] ),
						);
					}

					if ( $options->has( 'advanced', false ) ) {
						$options = $options->advanced;
						if ( $options->has( 'robotsMeta', false ) ) {
							$type_robots = $options->robotsMeta->all();
							if ( empty( $type_robots['default'] ) ) {
								$robots = $type_robots;
							}
						}
					}
				}
			}
			// phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

			if ( ! empty( $robots['default'] ) ) {
				return array(
					'noindex'  => false,
					'nofollow' => false,
				);
			}

			return array(
				'noindex'  => ! empty( $robots['noindex'] ),
				'nofollow' => ! empty( $robots['nofollow'] ),
			);
		} catch ( \Throwable $e ) {
			return null;
		}
	}
}
