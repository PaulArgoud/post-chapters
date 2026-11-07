<?php
/**
 * Multipage Templates.
 *
 * @package Multipage
 * @subpackage Templates
 * @since 1.5
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Inline HTML allowed in subpage titles.
 *
 * @since 1.7.0
 *
 * @return array Allowed tags for wp_kses().
 */
function mpp_get_subpage_title_allowed_html() {
	$allowed = array(
		'strong' => array(),
		'b'      => array(),
		'em'     => array(),
		'i'      => array(),
		'code'   => array(),
		'mark'   => array(),
		'sub'    => array(),
		'sup'    => array(),
		's'      => array(),
		'span'   => array(),
	);

	/**
	 * Filters the inline HTML allowed in subpage titles.
	 *
	 * @since 1.7.0
	 *
	 * @param array $allowed Allowed tags for wp_kses().
	 */
	return (array) apply_filters( 'mpp_subpage_title_allowed_html', $allowed );
}

/**
 * Encode the "<" of a raw-text title that do not start an allowed inline tag.
 *
 * Classic [nextpage] titles typed in the text editor are raw text ("a < b", "C++ <T>"),
 * while the allowed inline tags (see mpp_get_subpage_title_allowed_html()) stay markup.
 *
 * @since 1.7.0
 * @access private
 *
 * @param string $title Raw-text title.
 * @return string
 */
function _mpp_encode_title_stray_lt( $title ) {
	if ( false === strpos( $title, '<' ) ) {
		return $title;
	}

	$names = array();
	foreach ( array_keys( mpp_get_subpage_title_allowed_html() ) as $name ) {
		if ( is_string( $name ) && '' !== $name ) {
			$names[] = preg_quote( $name, '/' );
		}
	}

	if ( empty( $names ) ) {
		return str_replace( '<', '&lt;', $title );
	}

	$encoded = preg_replace( '/<(?!\/?(?:' . implode( '|', $names ) . ')(?:\s[^<>]*)?\/?>)/i', '&lt;', $title );

	return null === $encoded ? str_replace( '<', '&lt;', $title ) : $encoded;
}

/**
 * Subpage title as safe HTML (page title heading, table of contents, navigation).
 *
 * Gutenberg titles are HTML-encoded strings ("Tom &amp; Jerry", possibly with inline
 * tags such as <strong>): format 'html'. Classic shortcode titles may be raw text
 * containing &, <, > or quotes: format 'text', where a "<" that does not start an
 * allowed inline tag is text. Both are normalized without double encoding: entities
 * are kept, a small inline allowlist of tags is kept, everything else is stripped
 * ('html') or shown as text ('text').
 *
 * @since 1.7.0
 *
 * @param mixed  $title  The subpage title.
 * @param string $format Optional. 'html' (block titles, default) or 'text' (shortcode titles).
 * @return string Safe HTML.
 */
function mpp_get_subpage_title_html( $title, $format = 'html' ) {
	if ( ! is_scalar( $title ) ) {
		return '';
	}

	$title = (string) $title;
	if ( 'text' === $format ) {
		$title = _mpp_encode_title_stray_lt( $title );
	}

	return trim( force_balance_tags( wp_kses( $title, mpp_get_subpage_title_allowed_html() ) ) );
}

/**
 * Subpage title as plain text (document title, SEO plugins).
 *
 * The result is NOT escaped: escape it for the output context (WordPress escapes the
 * document title parts itself).
 *
 * @since 1.7.0
 *
 * @param mixed  $title  The subpage title.
 * @param string $format Optional. 'html' (block titles, default) or 'text' (shortcode titles).
 * @return string Plain text.
 */
function mpp_get_subpage_title_text( $title, $format = 'html' ) {
	if ( ! is_scalar( $title ) ) {
		return '';
	}

	$title = (string) $title;
	if ( 'text' === $format ) {
		$title = _mpp_encode_title_stray_lt( $title );
	}

	// Strip the tags first, so that an encoded "&lt;b&gt;" stays text.
	$text = wp_strip_all_tags( $title );
	$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

	$normalized = preg_replace( '/\s+/u', ' ', $text );
	if ( null !== $normalized ) {
		$text = $normalized;
	}

	return trim( $text );
}

/**
 * Title format of a subpage for the template functions.
 *
 * @since 1.7.0
 * @access private
 *
 * @param Multipage|mixed $multipage The Multipage instance.
 * @param int             $index     0-based subpage index.
 * @param int             $count     Number of titles being rendered.
 * @return string 'html' or 'text'.
 */
function _mpp_get_title_format( $multipage, $index, $count ) {
	if ( $multipage instanceof Multipage && is_array( $multipage->pages ) && count( $multipage->pages ) === (int) $count ) {
		return $multipage->get_title_format( $index );
	}

	return 'html';
}

/**
 * Current subpage (1-based) for the template functions.
 *
 * @since 1.7.0
 * @access private
 *
 * @global int $page
 *
 * @param Multipage|mixed $multipage The Multipage instance.
 * @return int
 */
function _mpp_get_current_page( $multipage ) {
	global $page;

	if ( $multipage instanceof Multipage && ! empty( $multipage->post_id ) ) {
		return $multipage->get_current_page();
	}

	return max( 1, (int) $page );
}

/**
 * The formatted output of a list of pages.
 *
 * Displays page links for paginated posts (i.e. includes the <!--nextpage-->
 * Quicktag one or more times). This tag must be within The Loop.
 *
 * @since 1.2.0
 *
 * @param Multipage    $multipage The Multipage instance.
 * @param string|array $args {
 *     Optional. Array or string of default arguments.
 *
 *     @type string   $before                HTML or text to prepend to each link. Default is `<div>`.
 *     @type string   $after                 HTML or text to append to each link. Default is `</div>`.
 *     @type string   $link_before           HTML or text to prepend to each link, inside the `<a>` tag.
 *                                           Also prepended to the current item, which is not linked. Default empty.
 *     @type string   $link_after            HTML or text to append to each Pages link inside the `<a>` tag.
 *                                           Also appended to the current item, which is not linked. Default empty.
 *     @type string   $continue_or_prev_next Indicates whether continue should be used. Valid values are continue,
 *                                           next-previous and hidden. Default is 'continue'.
 *     @type string   $separator             Text between pagination links. Default is ' '.
 *     @type string   $nextpagelink          Link text for the next page link. Default is 'Next page'.
 *     @type string   $previouspagelink      Link text for the previous page link, if available. Default is 'Previous page'.
 *     @type int|bool $echo                  Whether to echo or not. Accepts 1|true or 0|false. Default 0|false.
 * }
 * @return string Formatted output in HTML.
 */
function mpp_link_pages( $multipage, $args = '' ) {
	$defaults = array(
		'before'                => '<div>',
		'after'                 => '</div>',
		'link_before'           => '',
		'link_after'            => '',
		'continue_or_prev_next' => 'continue',
		'separator'             => ' ',
		'nextpagelink'          => esc_html__( 'Next page', 'sgr-nextpage-titles' ),
		'previouspagelink'      => esc_html__( 'Previous page', 'sgr-nextpage-titles' ),
		'echo'                  => 0,
	);

	$params = wp_parse_args( $args, $defaults );

	/**
	 * Filters the arguments used in retrieving page links for paginated posts.
	 *
	 * @since 1.4
	 *
	 * @param array $params An array of arguments for page links for paginated posts.
	 */
	$r = apply_filters( 'mpp_link_pages_args', $params );

	$output = '';
	$titles = ( $multipage instanceof Multipage && is_array( $multipage->mpp_data ) ) ? array_values( $multipage->mpp_data ) : array();

	if ( ! empty( $titles ) ) {
		$current  = _mpp_get_current_page( $multipage );
		$numpages = count( $titles );

		if ( 'continue' === $r['continue_or_prev_next'] ) {
			$output .= $r['before'];
			$next    = $current + 1;
			if ( $next <= $numpages ) {
				$link = _mpp_link_page( $next, 'next' ) . $r['link_before'] . mpp_get_subpage_title_html( $titles[ $next - 1 ], _mpp_get_title_format( $multipage, $next - 1, $numpages ) ) . $r['link_after'] . '</a>';
				/* translators: %s: link to the next subpage. */
				$text = sprintf( esc_html__( 'Continue: %s', 'sgr-nextpage-titles' ), $link );
			} else {
				$link = _mpp_link_page( 1 ) . $r['link_before'] . mpp_get_subpage_title_html( $titles[0], _mpp_get_title_format( $multipage, 0, $numpages ) ) . $r['link_after'] . '</a>';
				/* translators: %s: link to the first subpage. */
				$text = sprintf( esc_html__( 'Back to: %s', 'sgr-nextpage-titles' ), $link );
			}

			/**
			 * Filters the HTML output of the "Continue" / "Back to" link.
			 *
			 * @since 1.4
			 *
			 * @param string $text The text with the link.
			 * @param string $link The link HTML.
			 */
			$output .= apply_filters( 'mpp_link_pages_link', $text, $link );

			$output .= $r['after'];
		} elseif ( 'next-previous' === $r['continue_or_prev_next'] ) {
			$output .= $r['before'];
			$prev    = $current - 1;
			if ( $prev > 0 ) {
				$link = _mpp_link_page( $prev, 'previous' ) . $r['link_before'] . $r['previouspagelink'] . $r['link_after'] . '</a>';

				/** This filter is documented in inc/mpp-template.php */
				$output .= apply_filters( 'mpp_link_pages_link', $link, $prev );
			}
			$next = $current + 1;
			if ( $next <= $numpages ) {
				if ( $prev > 0 ) {
					$output .= $r['separator'];
				}
				$link = _mpp_link_page( $next, 'next' ) . $r['link_before'] . $r['nextpagelink'] . $r['link_after'] . '</a>';

				/** This filter is documented in inc/mpp-template.php */
				$output .= apply_filters( 'mpp_link_pages_link', $link, $next );
			}
			$output .= $r['after'];
		}
	}

	/**
	 * Filters the HTML output of page links for paginated posts.
	 *
	 * @since 1.4
	 *
	 * @param string $output HTML output of paginated posts' page links.
	 * @param array  $args   An array of arguments.
	 */
	$html = apply_filters( 'mpp_link_pages', $output, $args );

	if ( $r['echo'] ) {
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped parts.
	}

	return $html;
}

/**
 * The formatted output of a list of pages.
 *
 * Displays page links for paginated posts (i.e. includes the <!--nextpage-->.
 * Quicktag one or more times). This tag must be within The Loop.
 *
 * @since 1.4
 *
 * @global int $more
 *
 * @param Multipage    $multipage The Multipage instance.
 * @param string|array $args {
 *     Optional. Array or string of default arguments.
 *
 *     @type int|bool   $hide_header Whether to add the table of contents header or not.
 *     @type string     $position    Where to display the table of contents?
 *     @type string|int $comments    Opening `<a>` tag for the comments link, or 0 to hide.
 *     @type string     $before      HTML or text to prepend.
 *     @type string     $after       HTML or text to append.
 *     @type string     $row_before  HTML or text to prepend to each row.
 *     @type string     $row_after   HTML or text to append to each row.
 *     @type string     $link_before HTML or text to prepend to each link, inside the `<a>` tag.
 *                                   Also prepended to the current item, which is not linked. Default empty.
 *     @type string     $link_after  HTML or text to append to each Pages link inside the `<a>` tag.
 *                                   Also appended to the current item, which is not linked. Default empty.
 *     @type string     $separator   Text between pagination links. Default is ''.
 *     @type string     $pagelink    Page number template; `%` is replaced by the page number. Default empty.
 *     @type int|bool   $echo        Whether to echo or not. Accepts 1|true or 0|false. Default 0|false.
 * }
 * @return string Formatted output in HTML.
 */
function mpp_toc( $multipage, $args = '' ) {
	global $more;

	$defaults = array(
		'hide_header' => 0,
		'position'    => 'top-right',
		'comments'    => 0,
		'before'      => '<nav><ul>',
		'after'       => '</ul></nav>',
		'row_before'  => '<li>',
		'row_after'   => '</li>',
		'link_before' => '',
		'link_after'  => '',
		'separator'   => '',
		'pagelink'    => '',
		'echo'        => 0,
	);

	$params = wp_parse_args( $args, $defaults );

	/**
	 * Filters the arguments used in retrieving page links for paginated posts.
	 *
	 * @since 1.4
	 *
	 * @param array $params An array of arguments for page links for paginated posts.
	 */
	$r = apply_filters( 'mpp_toc_args', $params );

	$current = _mpp_get_current_page( $multipage );
	$data    = ( $multipage instanceof Multipage && is_array( $multipage->mpp_data ) ) ? $multipage->mpp_data : array();

	$i      = 1;
	$count  = count( $data );
	$output = $r['before'];

	foreach ( $data as $title ) {
		$safe_title = mpp_get_subpage_title_html( $title, _mpp_get_title_format( $multipage, $i - 1, $count ) );
		$row        = $r['row_before'] . str_replace( '%', (string) $i, (string) $r['pagelink'] ) . $r['separator'];
		if ( $i !== $current || ( ! $more && 1 === $current ) ) {
			$link = _mpp_link_page( $i ) . $safe_title . '</a>';
			$row .= $r['link_before'] . $link . $r['link_after'];
		} else {
			$row .= '<span class="current" aria-current="page">' . $safe_title . '</span>';
		}
		$row .= $r['row_after'];

		/**
		 * Filters the HTML output of an individual TOC row.
		 *
		 * @since 1.4
		 *
		 * @param string $row   The full row HTML output.
		 * @param string $title The subpage title.
		 * @param int    $i     Page number (1-based).
		 */
		$row = apply_filters( 'mpp_toc_pages_row', $row, $title, $i );

		$output .= $row;
		++$i;
	}

	// Add a link for comments.
	if ( $r['comments'] ) {
		$link    = $r['comments'] . esc_html__( 'Comments', 'sgr-nextpage-titles' ) . '</a>';
		$row     = $r['row_before'] . $r['link_before'] . $link . $r['link_after'] . $r['row_after'];
		$output .= $row;
	}

	$output .= $r['after'];

	$container_class = isset( $r['position'] ) && '' !== $r['position'] ? ' ' . sanitize_html_class( $r['position'] ) : '';
	$template        = '
	<div class="mpp-toc-container' . $container_class . '">';

	// Add header.
	if ( ! $r['hide_header'] ) {
		$template .= '<div class="mpp-toc-title">
			<h2>%1$s</h2>
		</div>';
	}

	$template .= '%2$s</div>';

	$output = sprintf(
		$template,
		esc_html__( 'Contents', 'sgr-nextpage-titles' ),
		$output
	);

	/**
	 * Filters the HTML output of page links for paginated posts.
	 *
	 * @since 1.4
	 *
	 * @param string $output HTML output of paginated posts' page links.
	 * @param array  $args   An array of arguments.
	 */
	$html = apply_filters( 'mpp_toc', $output, $args );

	if ( $r['echo'] ) {
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped parts.
	}

	return $html;
}

/**
 * Helper function for mpp_link_pages().
 *
 * @since 1.4
 * @since 1.7.0 Added the `$post` parameter.
 * @access private
 *
 * @global WP_Rewrite $wp_rewrite
 *
 * @param int              $i    Page number.
 * @param string           $p    Fragment identifier.
 * @param int|WP_Post|null $post Optional. Post. Default: the current post.
 * @return string Link.
 */
function _mpp_link_page_url( $i, $p = '', $post = null ) {
	global $wp_rewrite;

	$post = get_post( $post );

	if ( ! $post ) {
		return '';
	}

	$i          = (int) $i;
	$query_args = array();
	$permalink  = get_permalink( $post );

	if ( ! $permalink ) {
		return '';
	}

	if ( $i <= 1 ) {
		$url = $permalink;
	} elseif ( ! get_option( 'permalink_structure' ) || in_array( $post->post_status, array( 'draft', 'pending', 'future', 'private' ), true ) ) {
		$url = add_query_arg( 'page', $i, $permalink );
	} elseif ( 'page' === get_option( 'show_on_front' ) && (int) get_option( 'page_on_front' ) === (int) $post->ID ) {
		$url = trailingslashit( $permalink ) . user_trailingslashit( "$wp_rewrite->pagination_base/" . $i, 'single_paged' );
	} else {
		$url = trailingslashit( $permalink ) . user_trailingslashit( (string) $i, 'single_paged' );
	}

	if ( is_preview() ) {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only, mirrors _wp_link_page().
		if ( ( 'draft' !== $post->post_status ) && isset( $_GET['preview_id'], $_GET['preview_nonce'] ) ) {
			$query_args['preview_id']    = intval( $_GET['preview_id'] );
			$query_args['preview_nonce'] = sanitize_text_field( wp_unslash( $_GET['preview_nonce'] ) );
		}
		// phpcs:enable

		$url = get_preview_post_link( $post, $query_args, $url );
	}

	if ( $p ) {
		$url .= '#' . $p;
	}

	return $url;
}

/**
 * Helper function to build an opening `<a>` tag for a multipage link.
 *
 * @since 1.4
 * @access private
 *
 * @param int    $i   Page number.
 * @param string $rel Optional. Rel attribute value (e.g. 'next', 'previous'). Default empty.
 * @param string $p   Optional. Fragment identifier (e.g. 'comments'). Default empty.
 * @return string Opening `<a>` tag HTML.
 */
function _mpp_link_page( $i, $rel = '', $p = '' ) {
	$post_id = multipage()->post_id ? multipage()->post_id : null;
	$url     = _mpp_link_page_url( $i, $p, $post_id );

	$rel_attr = '';
	if ( $rel ) {
		$rel_attr = ' rel="' . esc_attr( $rel ) . '"';
	}

	return '<a href="' . esc_url( $url ) . '"' . $rel_attr . '>';
}
