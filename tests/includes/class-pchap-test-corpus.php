<?php
/**
 * Content corpus shared by the Post Chapters tests.
 *
 * @package Post Chapters
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Test classes use the required Test_PChap_ prefix and tests apply core and third-party hooks.

/**
 * Post Chapters post contents, as saved by the classic editor, the block editor
 * (1.7 and legacy 1.6 serializations) and by hand, with the expected pages.
 *
 * Every item gives:
 * - content: the raw post content;
 * - titles:  the expected display titles (fallbacks applied: "Intro", "Page N");
 * - kinds:   the expected page kinds (intro, block, shortcode, core).
 *
 * The content of page N contains the marker "BODY-N" and no other BODY marker.
 */
class PChap_Test_Corpus {

	/**
	 * Subpage block, as serialized by the 1.6 and 1.7 block editors.
	 *
	 * @param string|null $title Title attribute, null for the default (false) one.
	 * @return string
	 */
	public static function block( $title = null ) {
		if ( null === $title ) {
			// Default "title": false is not serialized; save() writes title="false".
			return "<!-- wp:multipage/subpage -->\n[nextpage title=\"false\"]\n<!-- /wp:multipage/subpage -->";
		}

		$json = wp_json_encode( array( 'title' => $title ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT );
		// Gutenberg serializeAttributes(): - for "--" and the JSON_HEX_* escapes.
		$json = str_replace( '--', '\\u002d\\u002d', $json );

		return '<!-- wp:multipage/subpage ' . $json . " -->\n[nextpage title=\"" . str_replace( '"', '&quot;', $title ) . "\"]\n<!-- /wp:multipage/subpage -->";
	}

	/**
	 * Paragraph block.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	public static function paragraph( $text ) {
		return "<!-- wp:paragraph -->\n<p>{$text}</p>\n<!-- /wp:paragraph -->";
	}

	/**
	 * The corpus.
	 *
	 * @return array Name => array( content, titles, kinds ).
	 */
	public static function items() {
		$p = array( __CLASS__, 'paragraph' );
		$b = array( __CLASS__, 'block' );

		return array(
			'classic plain text'                   => array(
				'content' => "Intro BODY-1\n\n[nextpage title=\"Deux\"]\n\nBODY-2\n\n[nextpage title=\"Trois\"]\n\nBODY-3",
				'titles'  => array( 'Intro', 'Deux', 'Trois' ),
				'kinds'   => array( 'intro', 'shortcode', 'shortcode' ),
			),
			'classic shortcodes inside p'          => array(
				'content' => "<p>BODY-1</p>\n<p>[nextpage title=\"Deux\"]</p>\n<p>BODY-2</p>\n<p>[nextpage title=\"Trois\"]</p>\n<p>BODY-3</p>",
				'titles'  => array( 'Intro', 'Deux', 'Trois' ),
				'kinds'   => array( 'intro', 'shortcode', 'shortcode' ),
			),
			'classic shortcode inside p with text' => array(
				'content' => "<p>BODY-1 before [nextpage title=\"Deux\"] BODY-2 after</p>\n<p>more BODY-2</p>",
				'titles'  => array( 'Intro', 'Deux' ),
				'kinds'   => array( 'intro', 'shortcode' ),
			),
			'classic shortcodes inside h3 style'   => array(
				'content' => "<p>BODY-1</p>\n<h3 style=\"font-size: 24px; font-weight: bold;\">[nextpage title=\"Deux\"]</h3>\n<p>BODY-2</p>\n<h3 style=\"color: #333;\">[nextpage title=\"Trois\"]</h3>\n<p>BODY-3</p>",
				'titles'  => array( 'Intro', 'Deux', 'Trois' ),
				'kinds'   => array( 'intro', 'shortcode', 'shortcode' ),
			),
			'classic shortcode inside h2'          => array(
				'content' => "<p>BODY-1</p>\n<h2>[nextpage title=\"Deux\"]</h2>\n<p>BODY-2</p>",
				'titles'  => array( 'Intro', 'Deux' ),
				'kinds'   => array( 'intro', 'shortcode' ),
			),
			'classic shortcode inside div'         => array(
				'content' => "<div class=\"box\">\n<p>BODY-1</p>\n[nextpage title=\"Deux\"]\n<p>BODY-2</p>\n</div>\n<p>BODY-2 outside</p>",
				'titles'  => array( 'Intro', 'Deux' ),
				'kinds'   => array( 'intro', 'shortcode' ),
			),
			'classic without intro'                => array(
				'content' => "[nextpage title=\"Un\"]\n\nBODY-1\n\n[nextpage title=\"Deux\"]\n\nBODY-2",
				'titles'  => array( 'Un', 'Deux' ),
				'kinds'   => array( 'shortcode', 'shortcode' ),
			),
			'classic untitled shortcodes'          => array(
				'content' => "BODY-1\n\n[nextpage]\n\nBODY-2\n\n[nextpage title=\"\"]\n\nBODY-3",
				'titles'  => array( 'Intro', 'Chapter 2', 'Chapter 3' ),
				'kinds'   => array( 'intro', 'shortcode', 'shortcode' ),
			),
			'classic CRLF'                         => array(
				'content' => "Intro BODY-1\r\n\r\n[nextpage title=\"Deux\"]\r\n\r\nBODY-2\r\n\r\n[nextpage title=\"Trois\"]\r\n\r\nBODY-3",
				'titles'  => array( 'Intro', 'Deux', 'Trois' ),
				'kinds'   => array( 'intro', 'shortcode', 'shortcode' ),
			),
			'classic unicode and emoji titles'     => array(
				'content' => "BODY-1\n\n[nextpage title=\"Éléphant 🐘\"]\n\nBODY-2\n\n[nextpage title=\"日本語のタイトル\"]\n\nBODY-3",
				'titles'  => array( 'Intro', 'Éléphant 🐘', '日本語のタイトル' ),
				'kinds'   => array( 'intro', 'shortcode', 'shortcode' ),
			),
			'classic duplicate titles'             => array(
				'content' => "BODY-1\n\n[nextpage title=\"Same\"]\n\nBODY-2\n\n[nextpage title=\"Same\"]\n\nBODY-3",
				'titles'  => array( 'Intro', 'Same', 'Same' ),
				'kinds'   => array( 'intro', 'shortcode', 'shortcode' ),
			),
			'classic script before first marker'   => array(
				'content' => "<script>window.mppTest = 1;</script>\n[nextpage title=\"Un\"]\n\nBODY-1\n\n[nextpage title=\"Deux\"]\n\nBODY-2",
				'titles'  => array( 'Un', 'Deux' ),
				'kinds'   => array( 'shortcode', 'shortcode' ),
			),
			'blocks 1.7'                           => array(
				'content' => call_user_func( $p, 'BODY-1' ) . "\n\n" . call_user_func( $b, 'Deux' ) . "\n\n" . call_user_func( $p, 'BODY-2' ) . "\n\n" . call_user_func( $b, 'Trois' ) . "\n\n" . call_user_func( $p, 'BODY-3' ),
				'titles'  => array( 'Intro', 'Deux', 'Trois' ),
				'kinds'   => array( 'intro', 'block', 'block' ),
			),
			'blocks legacy 1.6 exact'              => array(
				'content' => "<!-- wp:paragraph -->\n<p>BODY-1</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:multipage/subpage {\"title\":\"Deux\"} -->\n[nextpage title=\"Deux\"]\n<!-- /wp:multipage/subpage -->\n\n<!-- wp:paragraph -->\n<p>BODY-2</p>\n<!-- /wp:paragraph -->",
				'titles'  => array( 'Intro', 'Deux' ),
				'kinds'   => array( 'intro', 'block' ),
			),
			'blocks legacy default title false'    => array(
				'content' => call_user_func( $p, 'BODY-1' ) . "\n\n" . call_user_func( $b, null ) . "\n\n" . call_user_func( $p, 'BODY-2' ) . "\n\n" . call_user_func( $b, null ) . "\n\n" . call_user_func( $p, 'BODY-3' ),
				'titles'  => array( 'Intro', 'Chapter 2', 'Chapter 3' ),
				'kinds'   => array( 'intro', 'block', 'block' ),
			),
			'blocks self-closing'                  => array(
				'content' => call_user_func( $p, 'BODY-1' ) . "\n\n<!-- wp:multipage/subpage {\"title\":\"Deux\"} /-->\n\n" . call_user_func( $p, 'BODY-2' ),
				'titles'  => array( 'Intro', 'Deux' ),
				'kinds'   => array( 'intro', 'block' ),
			),
			'blocks without intro'                 => array(
				'content' => call_user_func( $b, 'Un' ) . "\n\n" . call_user_func( $p, 'BODY-1' ) . "\n\n" . call_user_func( $b, 'Deux' ) . "\n\n" . call_user_func( $p, 'BODY-2' ),
				'titles'  => array( 'Un', 'Deux' ),
				'kinds'   => array( 'block', 'block' ),
			),
			'blocks inside a group'                => array(
				'content' => "<!-- wp:group {\"layout\":{\"type\":\"constrained\"}} -->\n<div class=\"wp-block-group\">" . call_user_func( $p, 'BODY-1' ) . "\n\n" . call_user_func( $b, 'Deux' ) . "\n\n" . call_user_func( $p, 'BODY-2' ) . "</div>\n<!-- /wp:group -->",
				'titles'  => array( 'Intro', 'Deux' ),
				'kinds'   => array( 'intro', 'block' ),
			),
			'blocks intro image only'              => array(
				'content' => "<!-- wp:image -->\n<figure class=\"wp-block-image\"><img src=\"https://example.org/BODY-1.jpg\" alt=\"\"/></figure>\n<!-- /wp:image -->\n\n" . call_user_func( $b, 'Deux' ) . "\n\n" . call_user_func( $p, 'BODY-2' ),
				'titles'  => array( 'Intro', 'Deux' ),
				'kinds'   => array( 'intro', 'block' ),
			),
			'blocks empty paragraph before first'  => array(
				'content' => "<!-- wp:paragraph -->\n<p></p>\n<!-- /wp:paragraph -->\n\n" . call_user_func( $b, 'Un' ) . "\n\n" . call_user_func( $p, 'BODY-1' ) . "\n\n" . call_user_func( $b, 'Deux' ) . "\n\n" . call_user_func( $p, 'BODY-2' ),
				'titles'  => array( 'Un', 'Deux' ),
				'kinds'   => array( 'block', 'block' ),
			),
			'mixed blocks shortcodes core breaks'  => array(
				'content' => call_user_func( $p, 'BODY-1' ) . "\n\n" . call_user_func( $b, 'Bloc' ) . "\n\n" . call_user_func( $p, 'BODY-2' ) . "\n\n<!-- wp:shortcode -->\n[nextpage title=\"Code\"]\n<!-- /wp:shortcode -->\n\n" . call_user_func( $p, 'BODY-3' ) . "\n\n<!-- wp:nextpage -->\n<!--nextpage-->\n<!-- /wp:nextpage -->\n\n" . call_user_func( $p, 'BODY-4' ),
				'titles'  => array( 'Intro', 'Bloc', 'Code', 'Chapter 4' ),
				'kinds'   => array( 'intro', 'block', 'shortcode', 'core' ),
			),
			'classic shortcodes and core breaks'   => array(
				'content' => "BODY-1\n\n[nextpage title=\"Deux\"]\n\nBODY-2\n\n<!--nextpage-->\n\nBODY-3",
				'titles'  => array( 'Intro', 'Deux', 'Chapter 3' ),
				'kinds'   => array( 'intro', 'shortcode', 'core' ),
			),
		);
	}
}
