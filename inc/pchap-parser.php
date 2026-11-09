<?php
/**
 * Post Chapters Content Parser.
 *
 * Single source of truth for the subpage structure of a post: it finds the
 * subpage markers (Gutenberg blocks, classic [nextpage] shortcodes and core
 * page breaks) in document order and splits the content into pages.
 *
 * @package Post Chapters
 * @subpackage Parser
 * @since 1.5.14
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Post Chapters content parser.
 *
 * @since 1.5.14
 */
class PChap_Parser {

	/**
	 * Placeholder used for a titled subpage that has no body.
	 *
	 * It keeps the page alive through WordPress' own splitting (an empty first
	 * segment would be discarded by `generate_postdata()`) and through
	 * `the_content`, without rendering anything visible.
	 *
	 * @since 1.7.0
	 * @var string
	 */
	const EMPTY_PAGE_PLACEHOLDER = '<!-- mpp:empty-page -->';

	/**
	 * Page break used between the pages of block content.
	 *
	 * `WP_Query::generate_postdata()` normalizes the newlines and strips both
	 * `core/nextpage` delimiters before splitting, so the split is the same as with a
	 * bare `<!--nextpage-->`, while `has_block( 'core/nextpage' )` stays true (the
	 * core/post-content block only appends the standard page links in that case).
	 *
	 * @since 1.7.0
	 * @var string
	 */
	const BLOCK_PAGE_BREAK = "<!-- wp:nextpage -->\n<!--nextpage-->\n<!-- /wp:nextpage -->";

	/**
	 * Block delimiter regex, copied from WP_Block_Parser::next_token() (WordPress 7.1).
	 *
	 * @since 1.7.0
	 * @var string
	 */
	const BLOCK_DELIMITER_REGEX = '/<!--[ \t\f\r\n]+(?P<closer>\/)?wp:(?P<namespace>[a-z][a-z0-9_-]*\/)?(?P<name>[a-z][a-z0-9_-]*)[ \t\f\r\n]+(?P<attrs>{(?:(?:[^}]+|}+(?=})|(?!}[ \t\f\r\n]+\/?-->).)*+)?}[ \t\f\r\n]+)?(?P<void>\/)?-->/s';

	/**
	 * Inline markers: [nextpage ...] shortcodes (possibly escaped as [[nextpage]]) and core page breaks.
	 *
	 * @since 1.7.0
	 * @var string
	 */
	const INLINE_MARKER_REGEX = '/(\[?)\[nextpage(?![\w-])([^\]]*)\](\]?)|<!--nextpage-->/';

	/**
	 * HTML start or end tag, anchored at the current offset (quoted attribute values may contain ">").
	 *
	 * @since 1.7.0
	 * @var string
	 */
	const HTML_TAG_REGEX = '/\G<(\/?)([a-zA-Z][a-zA-Z0-9:-]*)(?=[\s\/>])((?:[^>"\']++|"[^"]*+"|\'[^\']*+\')*+)>/';

	/**
	 * Void HTML elements (never pushed on the open-element stack).
	 *
	 * @since 1.7.0
	 * @var string[]
	 */
	const VOID_ELEMENTS = array( 'area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'keygen', 'link', 'meta', 'param', 'source', 'track', 'wbr' );

	/**
	 * Elements whose content is raw text (markers inside them are not markers).
	 *
	 * @since 1.7.0
	 * @var string[]
	 */
	const RAW_TEXT_ELEMENTS = array( 'script', 'style', 'textarea', 'title', 'xmp', 'iframe', 'noembed', 'noframes', 'noscript', 'plaintext' );

	/**
	 * Elements that implicitly close an open <p>.
	 *
	 * @since 1.7.0
	 * @var string[]
	 */
	const CLOSES_P_ELEMENTS = array( 'address', 'article', 'aside', 'blockquote', 'details', 'dialog', 'div', 'dl', 'fieldset', 'figcaption', 'figure', 'footer', 'form', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'header', 'hgroup', 'hr', 'main', 'menu', 'nav', 'ol', 'p', 'pre', 'section', 'table', 'ul' );

	/**
	 * Elements that are kept even when a cut leaves them empty (they render or mean
	 * something without content). Void elements are never dropped either.
	 *
	 * @since 1.7.0
	 * @var string[]
	 */
	const KEPT_WHEN_EMPTY_ELEMENTS = array( 'audio', 'button', 'canvas', 'caption', 'col', 'colgroup', 'iframe', 'math', 'meter', 'noscript', 'object', 'optgroup', 'option', 'output', 'picture', 'progress', 'script', 'select', 'slot', 'style', 'svg', 'table', 'tbody', 'td', 'template', 'textarea', 'tfoot', 'th', 'thead', 'tr', 'video' );

	/**
	 * Whitespace trimmed next to a cut (HTML ASCII whitespace).
	 *
	 * @since 1.7.0
	 * @var string
	 */
	const WHITESPACE = " \t\n\r\f";

	/**
	 * Invisible fillers that do not keep an element alive when a cut leaves nothing else in
	 * it: non-breaking spaces (as typed in the visual editor) and line breaks.
	 *
	 * @since 1.7.0
	 * @var string
	 */
	const CUT_FILLER = '(?:&nbsp;|&#0*160;|&#x0*a0;|\xC2\xA0|<br(?:\s[^<>]*)?\/?>)';

	/**
	 * Parse post content into an ordered list of pages.
	 *
	 * Markers, in document order:
	 * - `multipage/subpage` blocks, with or without JSON attributes, including the
	 *   self-closing form `<!-- wp:multipage/subpage {...} /-->`. The `[nextpage]`
	 *   shortcode saved inside the block is part of the block and is never counted twice;
	 * - `[nextpage ...]` shortcodes outside subpage blocks. Escaped `[[nextpage]]` is ignored,
	 *   and so are shortcodes inside an HTML comment, an HTML tag (attribute values), a block
	 *   delimiter or a raw-text element (script, style, textarea...), where WordPress never
	 *   runs them as page breaks;
	 * - core page breaks (`<!--nextpage-->`, including the `core/nextpage` block wrapper),
	 *   because WordPress splits on them too.
	 *
	 * A post is "multipage" only when at least one page comes from a plugin marker
	 * (subpage block or shortcode). Posts with core page breaks only keep the pure
	 * core behavior.
	 *
	 * Titles come from the block JSON "title" attribute (a string, as saved by RichText,
	 * so possibly HTML-encoded) or from the shortcode "title" attribute. A missing, false or
	 * empty title, or a core page break, gives an empty title; the renderer shows a
	 * translated fallback. Slugs come from the "slug" attribute, else sanitize_title( title ),
	 * else "page-N"; duplicates get a "-1", "-2"... suffix.
	 *
	 * Content before the first marker is an intro page (title `%%intro%%`, slug `intro`) only
	 * when it is meaningful (see self::is_meaningful_content()). Content is never lost:
	 * leading content that is not meaningful (e.g. a script, a style or a separator) is
	 * prepended to the first page, and an untitled page without meaningful content (e.g. two
	 * adjacent breaks, or a break followed by a script) is appended to the previous page.
	 * Only disposable leftovers (whitespace, HTML comments, empty paragraphs, line breaks
	 * and `&nbsp;`) are dropped.
	 *
	 * Every page content is a self-contained fragment: when a marker sits inside other
	 * blocks or HTML elements (e.g. a group and its inner container), the open blocks and
	 * elements are closed, in reverse order, at the end of the page and reopened at the
	 * start of the next one. A shortcode that is the only content of a paragraph (or
	 * shortcode/HTML block, or `<p>` wrapper) consumes that wrapper. Elements and blocks
	 * that the cut would leave empty (e.g. `<h3 style="...">[nextpage]</h3>`, also with only
	 * `&nbsp;` or `<br>` next to the shortcode, or a heading block holding only the shortcode)
	 * are neither closed nor reopened, on both sides of the
	 * cut, and the whitespace next to the cut is trimmed (except in `<pre>`). Joining the contents
	 * with self::build_paginated_content() makes WordPress' own splitting return exactly
	 * these pages, in order.
	 *
	 * @since 1.7.0
	 *
	 * @param string $content The post content.
	 * @return array {
	 *     @type bool  $is_multipage Whether the post is a Post Chapters post.
	 *     @type bool  $has_intro    Whether the first page is the automatic intro.
	 *     @type array $pages        Ordered list of pages. Each page is an array with the keys
	 *                               'slug' (string), 'title' (string, raw, '' when untitled,
	 *                               '%%intro%%' for the intro), 'content' (string) and
	 *                               'kind' ('intro', 'block', 'shortcode' or 'core').
	 * }
	 */
	public static function parse( $content ) {
		$result = array(
			'is_multipage' => false,
			'has_intro'    => false,
			'pages'        => array(),
		);

		if ( ! is_string( $content ) || '' === $content ) {
			return $result;
		}

		// Cheap bail out: no plugin marker at all.
		if ( false === strpos( $content, 'wp:multipage/subpage' ) && false === strpos( $content, '[nextpage' ) ) {
			return $result;
		}

		$tokens  = self::tokenize_blocks( $content );
		$markers = self::find_markers( $content, $tokens );

		$has_plugin_marker = false;
		foreach ( $markers as $marker ) {
			if ( 'core' !== $marker['kind'] ) {
				$has_plugin_marker = true;
				break;
			}
		}

		if ( ! $has_plugin_marker ) {
			return $result;
		}

		$segments = self::build_segments( $content, $markers );

		// Assemble pages: segment 0 is the intro candidate, segment N belongs to marker N-1.
		$pages = array();
		$carry = '';

		if ( self::is_meaningful_content( $segments[0] ) ) {
			$pages[] = array(
				'slug'    => 'intro',
				'title'   => '%%intro%%',
				'content' => $segments[0],
				'kind'    => 'intro',
			);
		} elseif ( ! self::is_disposable_content( $segments[0] ) ) {
			// Not an intro, but never lose it: it goes to the top of the first page.
			$carry = $segments[0];
		}

		foreach ( $markers as $index => $marker ) {
			$segment = $segments[ $index + 1 ];

			if ( '' === $marker['title'] && ! self::is_meaningful_content( $segment ) ) {
				if ( self::is_disposable_content( $segment ) ) {
					continue;
				}

				// Untitled page without meaningful content: merge it with its neighbor.
				if ( empty( $pages ) ) {
					$carry = self::join_fragments( $carry, $segment );
				} else {
					$last                      = count( $pages ) - 1;
					$pages[ $last ]['content'] = self::join_fragments( $pages[ $last ]['content'], $segment );
				}
				continue;
			}

			$segment = self::join_fragments( $carry, $segment );
			$carry   = '';

			$pages[] = array(
				'slug'    => $marker['slug'],
				'title'   => $marker['title'],
				'content' => '' === $segment ? self::EMPTY_PAGE_PLACEHOLDER : $segment,
				'kind'    => $marker['kind'],
			);
		}

		$is_multipage = false;
		foreach ( $pages as $page ) {
			if ( 'block' === $page['kind'] || 'shortcode' === $page['kind'] ) {
				$is_multipage = true;
				break;
			}
		}

		if ( ! $is_multipage ) {
			return $result;
		}

		// Slugs: explicit slug attribute, else the title, else page-N; then de-duplicate.
		$used = array();
		foreach ( $pages as $i => $page ) {
			if ( 'intro' === $page['kind'] ) {
				$base = 'intro';
			} else {
				$base = '' !== $page['slug'] ? sanitize_title( $page['slug'] ) : '';
				if ( '' === $base ) {
					$base = sanitize_title( $page['title'] );
				}
				if ( '' === $base ) {
					$base = 'page-' . ( $i + 1 );
				}
			}

			$slug   = $base;
			$suffix = 1;
			while ( isset( $used[ $slug ] ) ) {
				$slug = $base . '-' . $suffix;
				++$suffix;
			}
			$used[ $slug ] = true;

			$pages[ $i ]['slug'] = $slug;
		}

		$result['is_multipage'] = true;
		$result['has_intro']    = 'intro' === $pages[0]['kind'];
		$result['pages']        = $pages;

		return $result;
	}

	/**
	 * Parse post content and return an associative array of subpage slugs to titles.
	 *
	 * Derived from self::parse(). This is the format stored in the `_mpp_data` post meta.
	 *
	 * @since 1.5
	 *
	 * @param string $content The post content.
	 * @return array|false Associative array of slug => title ('%%intro%%' for the intro,
	 *                     '' for an untitled page), or false if not a multipage post.
	 */
	public static function multipage_return_array( $content ) {
		$parsed = self::parse( $content );

		if ( ! $parsed['is_multipage'] ) {
			return false;
		}

		$_multipage = array();
		foreach ( $parsed['pages'] as $page ) {
			$_multipage[ $page['slug'] ] = $page['title'];
		}

		return $_multipage;
	}

	/**
	 * Join page contents with core page breaks.
	 *
	 * The result, split by WP_Query::generate_postdata(), yields exactly the given pages.
	 * Block content is joined with the `core/nextpage` block (self::BLOCK_PAGE_BREAK), so
	 * that block themes still show the standard page links when they are enabled; classic
	 * content is joined with a bare `<!--nextpage-->`.
	 *
	 * @since 1.7.0
	 *
	 * @param array     $pages     Pages as returned in the 'pages' key of self::parse(), or a list of strings.
	 * @param bool|null $use_block Optional. Whether to use the block page break. Default null: only when
	 *                             the pages contain block markup.
	 * @return string The paginated content.
	 */
	public static function build_paginated_content( $pages, $use_block = null ) {
		$contents = array();

		foreach ( (array) $pages as $page ) {
			$contents[] = is_array( $page ) ? (string) $page['content'] : (string) $page;
		}

		if ( null === $use_block ) {
			$use_block = false;
			foreach ( $contents as $page_content ) {
				if ( false !== strpos( $page_content, '<!-- wp:' ) ) {
					$use_block = true;
					break;
				}
			}
		}

		return implode( $use_block ? self::BLOCK_PAGE_BREAK : '<!--nextpage-->', $contents );
	}

	/**
	 * Whether a content fragment renders something meaningful.
	 *
	 * True when, after removing HTML comments and tags, non-whitespace text remains, or
	 * when the fragment contains media/embeds/interactive elements, or a self-closing
	 * (dynamic) block such as `<!-- wp:latest-posts /-->`. Empty paragraphs, spacers,
	 * separators, scripts, styles and whitespace are not meaningful (they do not make an
	 * intro or an untitled page on their own, but they are kept, see self::parse()).
	 *
	 * @since 1.7.0
	 *
	 * @param string $html Content fragment.
	 * @return bool
	 */
	public static function is_meaningful_content( $html ) {
		if ( ! is_string( $html ) || '' === trim( $html ) || self::EMPTY_PAGE_PLACEHOLDER === $html ) {
			$meaningful = false;
		} elseif ( self::has_void_block( $html ) ) {
			// A self-closing block is a dynamic block: it renders server-side.
			$meaningful = true;
		} else {
			$without_comments = preg_replace( '/<!--.*?-->/s', '', $html );
			if ( null === $without_comments ) {
				$without_comments = $html;
			}

			if ( preg_match( '/<(?:img|picture|video|audio|iframe|embed|object|svg|canvas|table|form|input|select|textarea|button|math)\b/i', $without_comments ) ) {
				$meaningful = true;
			} else {
				$text = wp_strip_all_tags( $without_comments );
				$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

				$found = preg_match( '/[^\s\x{00A0}\x{200B}\x{FEFF}]/u', $text );
				if ( false === $found ) {
					// Invalid UTF-8: fall back to a byte-level check.
					$found = '' !== trim( $text );
				}

				$meaningful = (bool) $found;
			}
		}

		if ( function_exists( 'apply_filters' ) ) {
			/**
			 * Filters whether a content fragment counts as meaningful (intro detection,
			 * untitled empty pages).
			 *
			 * @since 1.7.0
			 *
			 * @param bool   $meaningful Whether the fragment is meaningful.
			 * @param string $html       The fragment.
			 */
			$meaningful = (bool) pchap_apply_filters( 'pchap_parser_is_meaningful_content', $meaningful, $html );
		}

		return $meaningful;
	}

	/**
	 * Whether a fragment only holds leftovers that can be dropped without losing anything.
	 *
	 * Disposable: a fragment that is not meaningful (see self::is_meaningful_content())
	 * and only holds whitespace, HTML comments (including block delimiters), empty
	 * paragraphs or empty wrappers (such as the part of a group block before its first
	 * subpage). Anything with a visible or side effect is kept: scripts, styles, links,
	 * meta, templates, separators, inline styles (spacers, backgrounds), anchors (id or
	 * name attributes) and background classes.
	 *
	 * @since 1.7.0
	 *
	 * @param string $html Content fragment.
	 * @return bool
	 */
	private static function is_disposable_content( $html ) {
		if ( ! is_string( $html ) || '' === trim( $html ) || self::EMPTY_PAGE_PLACEHOLDER === $html ) {
			return true;
		}

		if ( self::is_meaningful_content( $html ) ) {
			return false;
		}

		$without_comments = preg_replace( '/<!--.*?-->/s', '', $html );
		if ( null === $without_comments ) {
			return false;
		}

		return ! preg_match( '/<(?:script|style|noscript|link|meta|template|hr|base)\b|\s(?:style|id|name)\s*=|has-background|wp-block-spacer|wp-block-separator/i', $without_comments );
	}

	/**
	 * Join two content fragments (either may be empty).
	 *
	 * @since 1.7.0
	 *
	 * @param string $first  First fragment.
	 * @param string $second Second fragment.
	 * @return string
	 */
	private static function join_fragments( $first, $second ) {
		if ( self::EMPTY_PAGE_PLACEHOLDER === $first ) {
			$first = '';
		}

		if ( '' === $first ) {
			return $second;
		}

		if ( '' === $second ) {
			return $first;
		}

		return $first . "\n\n" . $second;
	}

	/**
	 * Whether a fragment contains a self-closing block delimiter.
	 *
	 * @since 1.7.0
	 *
	 * @param string $html Content fragment.
	 * @return bool
	 */
	private static function has_void_block( $html ) {
		if ( false === strpos( $html, '/-->' ) ) {
			return false;
		}

		foreach ( self::tokenize_blocks( $html ) as $token ) {
			if ( $token['void'] ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Clean post content for shortcode parsing.
	 *
	 * Strips HTML tags (except inline formatting) and removes HTML comments
	 * to expose raw [nextpage] shortcodes.
	 *
	 * @since 1.4
	 *
	 * @param string $content The post content.
	 * @return string Cleaned content.
	 */
	public static function multipage_clean_content( $content ) {
		if ( ! $content || ! is_string( $content ) ) {
			return $content;
		}

		// The shortcodes could be closed inside p tags, so we remove them from inside the content.
		$content = strip_tags( $content, array( 'br', 'img', 'b', 'strong', 'i', 'code', 'blockquote' ) );

		// Also remove HTML comments (Gutenberg).
		$result = preg_replace( '/<!--[\s\S]*?-->/', '', $content );
		if ( null !== $result ) {
			$content = $result;
		}

		return trim( $content );
	}

	/**
	 * Find all [nextpage] shortcodes in the content.
	 *
	 * @since 1.4
	 *
	 * @param string $content The post content.
	 * @return array Regex matches array.
	 */
	public static function parse_nextpage_shortcode( $content ) {
		$matches = array( array() );

		if ( is_string( $content ) && preg_match_all( PCHAP_PATTERN, $content, $found ) ) {
			$matches = $found;
		}

		return $matches;
	}

	/**
	 * Tokenize block delimiters and pair openers with their closers.
	 *
	 * Matches one delimiter at a time, like WP_Block_Parser::next_token(), to keep the
	 * memory use low on long posts.
	 *
	 * @since 1.7.0
	 *
	 * @param string $content The post content.
	 * @return array List of tokens (start, end, name, raw_name, attrs, closer, void, pair).
	 *               'attrs' is only kept for subpage blocks.
	 */
	private static function tokenize_blocks( $content ) {
		$tokens = array();
		$offset = 0;
		$length = strlen( $content );

		while ( $offset < $length ) {
			$next = strpos( $content, '<!--', $offset );
			if ( false === $next ) {
				break;
			}

			if ( 1 !== preg_match( self::BLOCK_DELIMITER_REGEX, $content, $match, PREG_OFFSET_CAPTURE, $next ) ) {
				break;
			}

			$namespace = isset( $match['namespace'] ) && -1 !== $match['namespace'][1] ? $match['namespace'][0] : '';
			$raw_name  = $namespace . $match['name'][0];
			$name      = '' !== $namespace ? $raw_name : 'core/' . $raw_name;
			$attrs     = '';

			if ( 'multipage/subpage' === $name && isset( $match['attrs'] ) && -1 !== $match['attrs'][1] ) {
				$attrs = $match['attrs'][0];
			}

			$tokens[] = array(
				'start'    => $match[0][1],
				'end'      => $match[0][1] + strlen( $match[0][0] ),
				'raw_name' => $raw_name,
				'name'     => $name,
				'attrs'    => $attrs,
				'closer'   => isset( $match['closer'] ) && '/' === $match['closer'][0],
				'void'     => isset( $match['void'] ) && '/' === $match['void'][0],
				'pair'     => null,
			);

			$offset = $match[0][1] + strlen( $match[0][0] );
		}

		// Pair openers and closers.
		$stack = array();
		foreach ( $tokens as $index => $token ) {
			if ( $token['void'] ) {
				continue;
			}

			if ( ! $token['closer'] ) {
				$stack[] = $index;
				continue;
			}

			for ( $depth = count( $stack ) - 1; $depth >= 0; $depth-- ) {
				if ( $tokens[ $stack[ $depth ] ]['name'] === $token['name'] ) {
					$opener                    = $stack[ $depth ];
					$tokens[ $opener ]['pair'] = $index;
					$tokens[ $index ]['pair']  = $opener;
					$stack                     = array_slice( $stack, 0, $depth );
					break;
				}
			}
		}

		return $tokens;
	}

	/**
	 * Find the page markers, in document order.
	 *
	 * A single forward scan over block delimiters, HTML tags, comments, raw-text elements
	 * and inline markers. Block delimiters win over everything else, as in WP_Block_Parser.
	 * Inline markers inside an HTML comment, a tag, a block delimiter or a raw-text element
	 * are ignored. Each marker carries the stack of blocks and HTML elements open at its
	 * position, so that build_segments() can close and reopen them. A block that contains
	 * no marker candidate is skipped as a whole (its content cannot change the stack).
	 *
	 * @since 1.7.0
	 *
	 * @param string $content The post content.
	 * @param array  $tokens  Block tokens from self::tokenize_blocks().
	 * @return array List of markers (start, end, kind, title, slug, stack, openers).
	 */
	private static function find_markers( $content, $tokens ) {
		$length = strlen( $content );

		$inline = array();
		if ( preg_match_all( self::INLINE_MARKER_REGEX, $content, $found, PREG_SET_ORDER | PREG_OFFSET_CAPTURE ) ) {
			$inline = $found;
		}
		unset( $found );

		// Positions of every marker candidate (inline markers, subpage and nextpage openers), in order.
		$candidates = array();
		$i          = 0;
		$inline_n   = count( $inline );
		foreach ( $tokens as $token ) {
			if ( $token['closer'] || ( 'multipage/subpage' !== $token['name'] && 'core/nextpage' !== $token['name'] ) ) {
				continue;
			}
			while ( $i < $inline_n && $inline[ $i ][0][1] < $token['start'] ) {
				$candidates[] = $inline[ $i ][0][1];
				++$i;
			}
			$candidates[] = $token['start'];
		}
		for ( ; $i < $inline_n; $i++ ) {
			$candidates[] = $inline[ $i ][0][1];
		}

		$token_n        = count( $tokens );
		$candidate_n    = count( $candidates );
		$last_candidate = $candidate_n > 0 ? $candidates[ $candidate_n - 1 ] : -1;
		$raw_elements   = array_flip( self::RAW_TEXT_ELEMENTS );
		$void_elements  = array_flip( self::VOID_ELEMENTS );
		$ti             = 0;
		$mi             = 0;
		$ci             = 0;
		$pos            = 0;
		$markers        = array();
		$stack          = array();

		// Nothing after the last candidate can change a marker.
		while ( $pos <= $last_candidate ) {
			// Block delimiters that a skipped range covered (or that are behind us).
			while ( $ti < $token_n && $tokens[ $ti ]['start'] < $pos ) {
				++$ti;
			}

			$next_token  = $ti < $token_n ? $tokens[ $ti ]['start'] : $length;
			$next_inline = $mi < $inline_n ? $inline[ $mi ][0][1] : $length;
			$lt          = strpos( $content, '<', $pos );
			if ( false === $lt || $lt > $next_token ) {
				$lt = $next_token;
			}

			// Inline marker: shortcode or core page break.
			if ( $mi < $inline_n && $next_inline <= $lt && $next_inline <= $next_token ) {
				$match = $inline[ $mi ];
				++$mi;

				// Inside a range that was skipped (comment, tag, raw text, block delimiter, subpage block).
				if ( $next_inline < $pos ) {
					continue;
				}

				$marker = self::make_inline_marker( $match, $stack );
				if ( null !== $marker ) {
					$markers[] = $marker;
				}
				$pos = max( $pos, $match[0][1] + strlen( $match[0][0] ) );
				continue;
			}

			if ( $lt >= $length ) {
				break;
			}

			// Block delimiter.
			if ( $lt === $next_token ) {
				$index = $ti;
				$token = $tokens[ $index ];
				$pos   = $token['end'];
				++$ti;

				$is_subpage  = 'multipage/subpage' === $token['name'];
				$is_nextpage = 'core/nextpage' === $token['name'];

				if ( $is_subpage || $is_nextpage ) {
					if ( $token['closer'] ) {
						// Stray closer: removed from the segments later.
						continue;
					}

					$end = $token['end'];
					if ( ! $token['void'] && null !== $token['pair'] ) {
						$end = $tokens[ $token['pair'] ]['end'];
					} elseif ( $is_subpage && ! $token['void'] && preg_match( '/\G\s*\[nextpage(?![\w-])[^\]]*\]/', $content, $inner, 0, $end ) ) {
						// Unclosed subpage block: its saved shortcode belongs to it.
						$end += strlen( $inner[0] );
					}

					$title = '';
					$slug  = '';
					if ( $is_subpage && '' !== $token['attrs'] ) {
						$attrs = json_decode( $token['attrs'], true );
						if ( is_array( $attrs ) ) {
							$title = isset( $attrs['title'] ) ? self::normalize_string( $attrs['title'] ) : '';
							$slug  = isset( $attrs['slug'] ) ? self::normalize_string( $attrs['slug'] ) : '';
						}
					}

					$markers[] = self::make_marker( $token['start'], $end, $is_subpage ? 'block' : 'core', $title, $slug, $stack );
					$pos       = $end;
					continue;
				}

				if ( $token['void'] ) {
					continue;
				}

				if ( $token['closer'] ) {
					// Close the block, and every element left open inside it.
					if ( null !== $token['pair'] ) {
						for ( $depth = count( $stack ) - 1; $depth >= 0; $depth-- ) {
							if ( 'block' === $stack[ $depth ]['type'] && $stack[ $depth ]['token'] === $token['pair'] ) {
								$stack = array_slice( $stack, 0, $depth );
								break;
							}
						}
					}
					continue;
				}

				// Unpaired openers cannot be closed/reopened reliably.
				if ( null === $token['pair'] ) {
					continue;
				}

				$closer = $tokens[ $token['pair'] ];

				// No marker candidate inside the block: skip it as a whole.
				while ( $ci < $candidate_n && $candidates[ $ci ] < $pos ) {
					++$ci;
				}
				if ( $ci >= $candidate_n || $candidates[ $ci ] >= $closer['start'] ) {
					$pos = $closer['end'];
					continue;
				}

				$stack[] = array(
					'type'  => 'block',
					'token' => $index,
					'name'  => $token['name'],
					'open'  => substr( $content, $token['start'], $token['end'] - $token['start'] ),
					'close' => '<!-- /wp:' . $token['raw_name'] . ' -->',
				);
				continue;
			}

			// Comments, CDATA, doctype: opaque up to their end.
			if ( $lt + 1 < $length && '!' === $content[ $lt + 1 ] ) {
				$close = 0 === substr_compare( $content, '<!--', $lt, 4 ) ? strpos( $content, '-->', $lt + 4 ) : strpos( $content, '>', $lt + 2 );
				$end   = false === $close ? $length : $close + ( '-' === $content[ $close ] ? 3 : 1 );
				$pos   = min( $end, $next_token );
				continue;
			}

			if ( ! preg_match( self::HTML_TAG_REGEX, $content, $tag, 0, $lt ) ) {
				$pos = $lt + 1;
				continue;
			}

			$end    = min( $lt + strlen( $tag[0] ), $next_token );
			$name   = strtolower( $tag[2] );
			$closer = '/' === $tag[1];
			$pos    = $end;

			// Raw-text element: opaque up to its end tag.
			if ( ! $closer && isset( $raw_elements[ $name ] ) ) {
				$close_tag = stripos( $content, '</' . $name, $end );
				$raw_end   = false === $close_tag ? false : strpos( $content, '>', $close_tag );
				$raw_end   = false === $raw_end ? $length : $raw_end + 1;
				$pos       = min( $raw_end, $next_token );
				continue;
			}

			if ( $closer ) {
				self::close_element( $stack, $name );
			} elseif ( '/' !== substr( $tag[3], -1 ) && ! isset( $void_elements[ $name ] ) ) {
				self::open_element( $stack, $name, $tag[0] );
			}
		}

		return self::expand_markers( $content, $markers, $tokens );
	}

	/**
	 * Build the marker of an inline match (shortcode or core page break).
	 *
	 * @since 1.7.0
	 *
	 * @param array $inline_match Match of self::INLINE_MARKER_REGEX (PREG_OFFSET_CAPTURE).
	 * @param array $stack        Blocks and elements open at the marker.
	 * @return array|null The marker, or null for an escaped shortcode.
	 */
	private static function make_inline_marker( $inline_match, $stack ) {
		$text  = $inline_match[0][0];
		$start = $inline_match[0][1];
		$end   = $start + strlen( $text );

		if ( '<!--nextpage-->' === $text ) {
			return self::make_marker( $start, $end, 'core', '', '', $stack );
		}

		$open_bracket  = isset( $inline_match[1] ) && '[' === $inline_match[1][0];
		$close_bracket = isset( $inline_match[3] ) && ']' === $inline_match[3][0];

		// Escaped shortcode: [[nextpage ...]] is displayed literally by WordPress.
		if ( $open_bracket && $close_bracket ) {
			return null;
		}

		if ( $open_bracket ) {
			++$start;
		}
		if ( $close_bracket ) {
			--$end;
		}

		$atts  = self::parse_shortcode_atts( isset( $inline_match[2] ) ? $inline_match[2][0] : '' );
		$title = isset( $atts['title'] ) ? self::normalize_string( $atts['title'] ) : '';
		$slug  = isset( $atts['slug'] ) ? self::normalize_string( $atts['slug'] ) : '';

		return self::make_marker( $start, $end, 'shortcode', $title, $slug, $stack );
	}

	/**
	 * Parse the attributes of a [nextpage] shortcode.
	 *
	 * Same syntax as core shortcode_parse_atts(), without its "reject any unclosed HTML
	 * elements" step: core empties any value holding a "<" that does not close (e.g. the
	 * raw-text title "a < b & c"), which made such titles fall back to "Page N". The
	 * values are never output as is: titles are sanitized for their output context by
	 * pchap_get_subpage_title_html() / pchap_get_subpage_title_text() ('text' format, where
	 * a stray "<" is text), slugs by sanitize_title().
	 *
	 * @since 1.7.0
	 *
	 * @param string $text Attribute text of the shortcode.
	 * @return array Named attributes (lowercase keys) and positional values.
	 */
	private static function parse_shortcode_atts( $text ) {
		$atts = array();
		$text = (string) $text;

		if ( '' === trim( $text ) ) {
			return $atts;
		}

		$pattern = function_exists( 'get_shortcode_atts_regex' )
			? get_shortcode_atts_regex()
			: '/([\w-]+)\s*=\s*"([^"]*)"(?:\s|$)|([\w-]+)\s*=\s*\'([^\']*)\'(?:\s|$)|([\w-]+)\s*=\s*([^\s\'"]+)(?:\s|$)|"([^"]*)"(?:\s|$)|\'([^\']*)\'(?:\s|$)|(\S+)(?:\s|$)/';

		$normalized = preg_replace( "/[\x{00a0}\x{200b}]+/u", ' ', $text );
		if ( null !== $normalized ) {
			$text = $normalized;
		}

		if ( ! preg_match_all( $pattern, $text, $matches, PREG_SET_ORDER ) ) {
			return $atts;
		}

		foreach ( $matches as $m ) {
			if ( ! empty( $m[1] ) ) {
				$atts[ strtolower( $m[1] ) ] = stripcslashes( $m[2] );
			} elseif ( ! empty( $m[3] ) ) {
				$atts[ strtolower( $m[3] ) ] = stripcslashes( $m[4] );
			} elseif ( ! empty( $m[5] ) ) {
				$atts[ strtolower( $m[5] ) ] = stripcslashes( $m[6] );
			} elseif ( isset( $m[7] ) && strlen( $m[7] ) ) {
				$atts[] = stripcslashes( $m[7] );
			} elseif ( isset( $m[8] ) && strlen( $m[8] ) ) {
				$atts[] = stripcslashes( $m[8] );
			} elseif ( isset( $m[9] ) ) {
				$atts[] = stripcslashes( $m[9] );
			}
		}

		return $atts;
	}

	/**
	 * Close an HTML element on the open-element stack.
	 *
	 * Closes the matching element (and the elements opened after it) within the current
	 * block; an unmatched end tag is ignored, like a browser does.
	 *
	 * @since 1.7.0
	 *
	 * @param array  $stack Open blocks and elements (outermost first), modified in place.
	 * @param string $name  Lowercase tag name.
	 */
	private static function close_element( &$stack, $name ) {
		for ( $depth = count( $stack ) - 1; $depth >= 0; $depth-- ) {
			if ( 'block' === $stack[ $depth ]['type'] ) {
				return;
			}
			if ( $stack[ $depth ]['name'] === $name ) {
				array_splice( $stack, $depth );
				return;
			}
		}
	}

	/**
	 * Open an HTML element on the open-element stack.
	 *
	 * The common implied end tags are applied first (p, li, dt/dd, option, table cells
	 * and rows).
	 *
	 * @since 1.7.0
	 *
	 * @param array  $stack Open blocks and elements (outermost first), modified in place.
	 * @param string $name  Lowercase tag name.
	 * @param string $text  The start tag, as written.
	 */
	private static function open_element( &$stack, $name, $text ) {
		static $closes_p = null;
		if ( null === $closes_p ) {
			$closes_p = array_flip( self::CLOSES_P_ELEMENTS );
		}

		$implied = array();
		if ( isset( $closes_p[ $name ] ) ) {
			$implied = array( 'p' );
		} elseif ( 'li' === $name ) {
			$implied = array( 'li', 'p' );
		} elseif ( 'dt' === $name || 'dd' === $name ) {
			$implied = array( 'dt', 'dd', 'p' );
		} elseif ( 'option' === $name ) {
			$implied = array( 'option' );
		} elseif ( 'td' === $name || 'th' === $name ) {
			$implied = array( 'td', 'th' );
		} elseif ( 'tr' === $name ) {
			$implied = array( 'td', 'th', 'tr' );
		}

		while ( ! empty( $implied ) && ! empty( $stack ) ) {
			$top = $stack[ count( $stack ) - 1 ];
			if ( 'tag' !== $top['type'] || ! in_array( $top['name'], $implied, true ) ) {
				break;
			}
			array_pop( $stack );
		}

		$stack[] = array(
			'type'  => 'tag',
			'name'  => $name,
			'open'  => $text,
			'close' => '</' . $name . '>',
		);
	}

	/**
	 * Build a marker array.
	 *
	 * @since 1.7.0
	 *
	 * @param int    $start Start offset.
	 * @param int    $end   End offset.
	 * @param string $kind  'block', 'shortcode' or 'core'.
	 * @param string $title Raw title.
	 * @param string $slug  Raw slug.
	 * @param array  $stack Blocks and elements open at the marker (outermost first).
	 * @return array
	 */
	private static function make_marker( $start, $end, $kind, $title, $slug, $stack ) {
		return array(
			'start' => $start,
			'end'   => $end,
			'kind'  => $kind,
			'title' => $title,
			'slug'  => $slug,
			'stack' => $stack,
		);
	}

	/**
	 * Let inline markers consume the wrappers they are the only content of.
	 *
	 * `<p>[nextpage]</p>` and `<!-- wp:paragraph --><p>[nextpage]</p><!-- /wp:paragraph -->`
	 * (also shortcode and HTML blocks) are removed as a whole, so that no empty paragraph is
	 * left at the page boundaries.
	 *
	 * @since 1.7.0
	 *
	 * @param string $content The post content.
	 * @param array  $markers Markers.
	 * @param array  $tokens  Block tokens.
	 * @return array Markers, each with an 'openers' list (outermost first) of array( 'type', 'name', 'open', 'close' ).
	 */
	private static function expand_markers( $content, $markers, $tokens ) {
		$wrappers = array( 'core/paragraph', 'core/shortcode', 'core/html' );
		$prev_end = 0;

		foreach ( $markers as $i => $marker ) {
			if ( 'block' !== $marker['kind'] ) {
				// <p>marker</p>.
				$top = end( $marker['stack'] );
				if ( false !== $top && 'tag' === $top['type'] && 'p' === $top['name'] ) {
					$before = substr( $content, $prev_end, $marker['start'] - $prev_end );
					$after  = substr( $content, $marker['end'], 64 );

					if ( preg_match( '/<p(?:\s[^>]*)?>\s*$/i', $before, $open ) && preg_match( '/^\s*<\/p>/i', $after, $close ) ) {
						$marker['start'] -= strlen( $open[0] );
						$marker['end']   += strlen( $close[0] );
						array_pop( $marker['stack'] );
					}
				}

				// Paragraph / shortcode / HTML block whose only content is the marker.
				while ( ! empty( $marker['stack'] ) ) {
					$block_depth = null;
					for ( $depth = count( $marker['stack'] ) - 1; $depth >= 0; $depth-- ) {
						if ( 'block' === $marker['stack'][ $depth ]['type'] ) {
							$block_depth = $depth;
							break;
						}
					}

					if ( null === $block_depth ) {
						break;
					}

					// Only a <p> element may be open between the block and the marker.
					$above = array_slice( $marker['stack'], $block_depth + 1 );
					if ( count( $above ) > 1 || ( 1 === count( $above ) && 'p' !== $above[0]['name'] ) ) {
						break;
					}

					$opener = $tokens[ $marker['stack'][ $block_depth ]['token'] ];

					if ( ! in_array( $opener['name'], $wrappers, true ) ) {
						break;
					}

					$closer = $tokens[ $opener['pair'] ];
					$before = substr( $content, $opener['end'], $marker['start'] - $opener['end'] );
					$after  = substr( $content, $marker['end'], $closer['start'] - $marker['end'] );

					if ( ! preg_match( '/^\s*(?:<p(?:\s[^>]*)?>)?\s*$/i', $before ) || ! preg_match( '/^\s*(?:<\/p>)?\s*$/i', $after ) ) {
						break;
					}

					$marker['start'] = $opener['start'];
					$marker['end']   = $closer['end'];
					$marker['stack'] = array_slice( $marker['stack'], 0, $block_depth );
				}
			}

			// Never overlap the previous marker.
			if ( $marker['start'] < $prev_end ) {
				$marker['start'] = $prev_end;
			}

			$marker['openers'] = array();
			foreach ( $marker['stack'] as $entry ) {
				$marker['openers'][] = array(
					'type'  => $entry['type'],
					'name'  => $entry['name'],
					'open'  => $entry['open'],
					'close' => $entry['close'],
				);
			}

			$markers[ $i ] = $marker;
			$prev_end      = $marker['end'];
		}

		return $markers;
	}

	/**
	 * Cut the content into segments around the markers.
	 *
	 * Segment 0 is the content before the first marker; segment N is the content
	 * after marker N-1. Enclosing blocks and elements are closed/reopened at each boundary.
	 * Elements and blocks that the cut leaves empty (e.g. `<h3>[nextpage]</h3>`) are not
	 * closed/reopened at all, and the whitespace next to the cut is trimmed inside the
	 * elements that are (see self::drop_empty_reopened() and self::drop_empty_closed()).
	 *
	 * @since 1.7.0
	 *
	 * @param string $content The post content.
	 * @param array  $markers Markers.
	 * @return string[] Segments (count = count( $markers ) + 1).
	 */
	private static function build_segments( $content, $markers ) {
		$segments    = array();
		$prev_end    = 0;
		$prev_opened = array();
		$count       = count( $markers );

		for ( $k = 0; $k <= $count; $k++ ) {
			$end  = $k < $count ? $markers[ $k ]['start'] : strlen( $content );
			$body = (string) substr( $content, $prev_end, max( 0, $end - $prev_end ) );

			// Top of the page: elements reopened after the previous cut.
			$reopened = self::drop_empty_reopened( $prev_opened, $body, $k === $count );

			$head = '';
			foreach ( $reopened as $entry ) {
				$head .= $entry['open'];
			}
			$head .= $body;

			// Bottom of the page: elements closed before the next cut.
			$closed = $k < $count ? self::drop_empty_closed( $markers[ $k ]['openers'], $head ) : array();

			$suffix = '';
			foreach ( array_reverse( $closed ) as $entry ) {
				$suffix .= $entry['close'];
			}

			$segments[] = self::clean_segment( $head . $suffix );

			if ( $k < $count ) {
				$prev_end    = $markers[ $k ]['end'];
				$prev_opened = $markers[ $k ]['openers'];
			}
		}

		return $segments;
	}

	/**
	 * Elements reopened at the top of a page, without those the cut left empty.
	 *
	 * From the innermost reopened element outwards: when the page body starts (after
	 * whitespace, `&nbsp;` and `<br>`) with the end tag of the element, or with something that implicitly
	 * closes it (e.g. a `<div>` after a `<p>`), the element would be empty only because of
	 * the cut: it is not reopened and its end tag (or block closer) is removed from the
	 * body. Then the whitespace at the top of the innermost kept element is trimmed.
	 *
	 * @since 1.7.0
	 *
	 * @param array  $openers Elements and blocks open at the previous cut (outermost first).
	 * @param string $body    Page body, modified in place.
	 * @param bool   $at_end  Whether the body runs to the end of the content.
	 * @return array The openers to reopen (outermost first).
	 */
	private static function drop_empty_reopened( $openers, &$body, $at_end ) {
		$keep = count( $openers );

		while ( $keep > 0 && self::is_droppable_when_empty( $openers[ $keep - 1 ] ) ) {
			$entry = $openers[ $keep - 1 ];
			$rest  = self::ltrim_cut_fillers( $body );

			$closer = self::match_closer( $entry, $rest );
			if ( '' !== $closer ) {
				$body = (string) substr( $rest, strlen( $closer ) );
				--$keep;
				continue;
			}

			if ( self::is_implicitly_closed( $entry, $rest, array_slice( $openers, 0, $keep - 1 ), $at_end ) ) {
				$body = $rest;
				--$keep;
				continue;
			}

			break;
		}

		$kept = array_slice( $openers, 0, $keep );

		if ( ! empty( $kept ) && ! self::preserves_whitespace( $kept ) ) {
			$body = ltrim( $body, self::WHITESPACE );
		}

		return $kept;
	}

	/**
	 * Elements closed at the bottom of a page, without those the cut left empty.
	 *
	 * From the innermost open element outwards: when the page ends (before whitespace,
	 * `&nbsp;` and `<br>`) with the start tag of the element (or the block opener), the element would be empty
	 * only because of the cut: the start tag is removed and the element is not closed.
	 * Then the whitespace at the bottom of the innermost kept element is trimmed.
	 *
	 * @since 1.7.0
	 *
	 * @param array  $openers Elements and blocks open at the cut (outermost first).
	 * @param string $head    Page content up to the cut, modified in place.
	 * @return array The openers to close (outermost first).
	 */
	private static function drop_empty_closed( $openers, &$head ) {
		$keep = count( $openers );

		while ( $keep > 0 && self::is_droppable_when_empty( $openers[ $keep - 1 ] ) ) {
			$open    = (string) $openers[ $keep - 1 ]['open'];
			$trimmed = self::rtrim_cut_fillers( $head );
			$length  = strlen( $open );

			if ( 0 === $length || strlen( $trimmed ) < $length || 0 !== substr_compare( $trimmed, $open, -$length ) ) {
				break;
			}

			$head = (string) substr( $trimmed, 0, strlen( $trimmed ) - $length );
			--$keep;
		}

		$kept = array_slice( $openers, 0, $keep );

		if ( ! empty( $kept ) && ! self::preserves_whitespace( $kept ) ) {
			$head = rtrim( $head, self::WHITESPACE );
		}

		return $kept;
	}

	/**
	 * Strip the whitespace and invisible fillers (see self::CUT_FILLER) at the start of a string.
	 *
	 * @since 1.7.0
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	private static function ltrim_cut_fillers( $html ) {
		do {
			$before = $html;
			$html   = ltrim( $html, self::WHITESPACE );

			if ( '' !== $html && ( '&' === $html[0] || '<' === $html[0] || "\xC2" === $html[0] ) && preg_match( '/^' . self::CUT_FILLER . '/i', $html, $match ) ) {
				$html = (string) substr( $html, strlen( $match[0] ) );
			}
		} while ( $html !== $before );

		return $html;
	}

	/**
	 * Strip the whitespace and invisible fillers (see self::CUT_FILLER) at the end of a string.
	 *
	 * @since 1.7.0
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	private static function rtrim_cut_fillers( $html ) {
		do {
			$before = $html;
			$html   = rtrim( $html, self::WHITESPACE );

			// Only the tail can hold a filler: never run the regex on the whole page.
			if ( '' !== $html && preg_match( '/' . self::CUT_FILLER . '\z/i', substr( $html, -64 ), $match ) ) {
				$html = (string) substr( $html, 0, strlen( $html ) - strlen( $match[0] ) );
			}
		} while ( $html !== $before );

		return $html;
	}

	/**
	 * Whether an element or block may be dropped when the cut leaves it empty.
	 *
	 * Blocks, and the elements that render nothing meaningful when empty (headings,
	 * paragraphs, inline formatting, lists, quotes, wrappers...). Never media, embedded
	 * content, form controls, scripting and table structure.
	 *
	 * @since 1.7.0
	 *
	 * @param array $entry Opener (type, name, open, close).
	 * @return bool
	 */
	private static function is_droppable_when_empty( $entry ) {
		if ( ! isset( $entry['type'], $entry['name'] ) ) {
			return false;
		}

		if ( 'block' === $entry['type'] ) {
			return true;
		}

		return ! in_array( $entry['name'], self::KEPT_WHEN_EMPTY_ELEMENTS, true ) && ! in_array( $entry['name'], self::VOID_ELEMENTS, true );
	}

	/**
	 * Whether whitespace is significant inside the given open elements.
	 *
	 * @since 1.7.0
	 *
	 * @param array $openers Openers.
	 * @return bool
	 */
	private static function preserves_whitespace( $openers ) {
		foreach ( $openers as $entry ) {
			if ( isset( $entry['type'], $entry['name'] ) && 'tag' === $entry['type'] && in_array( $entry['name'], array( 'pre', 'listing', 'plaintext', 'textarea' ), true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * The end tag (or block closer) of an opener at the start of a string.
	 *
	 * @since 1.7.0
	 *
	 * @param array  $entry Opener (type, name, open, close).
	 * @param string $html  HTML.
	 * @return string The matched closer, or '' when the string does not start with it.
	 */
	private static function match_closer( $entry, $html ) {
		if ( '' === $html || '<' !== $html[0] ) {
			return '';
		}

		if ( 'block' === $entry['type'] ) {
			$name = (string) $entry['name'];
			if ( 0 === strpos( $name, 'core/' ) ) {
				$name_regex = '(?:core\/)?' . preg_quote( substr( $name, 5 ), '/' );
			} else {
				$name_regex = preg_quote( $name, '/' );
			}
			$regex = '/^<!--[ \t\f\r\n]+\/wp:' . $name_regex . '[ \t\f\r\n]+-->/';
		} else {
			$regex = '/^<\/' . preg_quote( (string) $entry['name'], '/' ) . '[ \t\f\r\n]*>/i';
		}

		return preg_match( $regex, $html, $match ) ? $match[0] : '';
	}

	/**
	 * Whether a string starts with something that implicitly closes an open element.
	 *
	 * Covers the implied end tags of `<p>`, `<li>`, `<dt>` and `<dd>` (a block-level start
	 * tag, a sibling item, or the end tag of an enclosing element), and the end of the content.
	 *
	 * @since 1.7.0
	 *
	 * @param array  $entry  Opener (type, name, open, close).
	 * @param string $html   HTML following the element start tag (left-trimmed).
	 * @param array  $outer  Openers enclosing the element (outermost first).
	 * @param bool   $at_end Whether the HTML runs to the end of the content.
	 * @return bool
	 */
	private static function is_implicitly_closed( $entry, $html, $outer, $at_end ) {
		if ( '' === $html ) {
			return $at_end;
		}

		if ( 'tag' !== $entry['type'] || '<' !== $html[0] ) {
			return false;
		}

		if ( 'p' === $entry['name'] ) {
			$siblings = self::CLOSES_P_ELEMENTS;
		} elseif ( 'li' === $entry['name'] ) {
			$siblings = array( 'li' );
		} elseif ( 'dt' === $entry['name'] || 'dd' === $entry['name'] ) {
			$siblings = array( 'dt', 'dd' );
		} else {
			return false;
		}

		if ( preg_match( '/^<([a-zA-Z][a-zA-Z0-9:-]*)[\s\/>]/', $html, $match ) ) {
			return in_array( strtolower( $match[1] ), $siblings, true );
		}

		foreach ( $outer as $parent ) {
			if ( isset( $parent['type'] ) && 'tag' === $parent['type'] && '' !== self::match_closer( $parent, $html ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Remove leftovers that WordPress would otherwise split or strip, and trim.
	 *
	 * @since 1.7.0
	 *
	 * @param string $segment Segment.
	 * @return string
	 */
	private static function clean_segment( $segment ) {
		// Stray subpage/nextpage closers.
		$cleaned = preg_replace( '/<!--[ \t\f\r\n]+\/wp:(?:multipage\/subpage|(?:core\/)?nextpage)[ \t\f\r\n]+-->/', '', $segment );
		if ( null !== $cleaned ) {
			$segment = $cleaned;
		}

		// generate_postdata() strips these literally; never leave them in a page.
		$segment = str_replace( array( '<!-- wp:nextpage -->', '<!-- /wp:nextpage -->', '<!--nextpage-->' ), '', $segment );

		return trim( $segment );
	}

	/**
	 * Normalize an attribute value to a string.
	 *
	 * @since 1.7.0
	 *
	 * @param mixed $value Attribute value.
	 * @return string The value, or '' for false, null, arrays and objects.
	 */
	private static function normalize_string( $value ) {
		if ( is_string( $value ) ) {
			return trim( $value );
		}

		if ( is_int( $value ) || is_float( $value ) ) {
			return (string) $value;
		}

		return '';
	}
}
