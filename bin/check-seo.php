<?php
/**
 * Checks the head of the chapters of a post against a running site (see bin/check-seo.sh).
 *
 * Usage: php bin/check-seo.php <base URL> <post path, e.g. /guide/> <label>
 *
 * Fetches the first and the second page of the post as a visitor and checks what an SEO
 * plugin must say about the second one: its own title, canonical, Open Graph URL and
 * robots, and a single pair of rel prev/next links. Exits with 1 when a check fails.
 *
 * @package Post Chapters
 */

if ( 'cli' !== PHP_SAPI ) {
	exit;
}

/**
 * Fetch a page and read its head.
 *
 * @param string $url URL.
 * @return array title, canonicals, prev, next, metas (name or property => content).
 */
function pchap_check_seo_fetch( $url ) {
	$html = (string) file_get_contents( $url ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	$dom  = new DOMDocument();
	libxml_use_internal_errors( true );
	$dom->loadHTML( '<?xml encoding="utf-8" ?>' . $html );
	libxml_clear_errors();
	$xpath = new DOMXPath( $dom );

	$head = array(
		'title'      => '',
		'canonicals' => array(),
		'prev'       => array(),
		'next'       => array(),
		'metas'      => array(),
	);

	foreach ( $xpath->query( '//head/title' ) as $node ) {
		$text          = $node->textContent; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM API.
		$head['title'] = html_entity_decode( trim( $text ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	}
	foreach ( $xpath->query( '//head/link[@rel]' ) as $node ) {
		$rel = $node->getAttribute( 'rel' );
		if ( 'canonical' === $rel ) {
			$head['canonicals'][] = $node->getAttribute( 'href' );
		} elseif ( 'prev' === $rel || 'next' === $rel ) {
			$head[ $rel ][] = $node->getAttribute( 'href' );
		}
	}
	foreach ( $xpath->query( '//head/meta' ) as $node ) {
		$key = $node->getAttribute( 'property' ) ? $node->getAttribute( 'property' ) : $node->getAttribute( 'name' );
		if ( '' !== $key ) {
			$head['metas'][ $key ] = $node->getAttribute( 'content' );
		}
	}

	return $head;
}


/**
 * Check the chapters of a post.
 *
 * @param string[] $args Command line arguments: script, base URL, post path, label.
 * @return int Exit code.
 */
function pchap_check_seo_main( $args ) {
	list( , $base, $path, $label ) = array_pad( $args, 4, '' );

	if ( '' === $base || '' === $path ) {
		fwrite( STDERR, "Usage: php bin/check-seo.php <base URL> <post path> <label>\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		return 2;
	}

	$page_1  = rtrim( $base, '/' ) . $path;
	$page_2  = $page_1 . '2/';
	$page_3  = $page_1 . '3/';
	$head    = pchap_check_seo_fetch( $page_2 );
	$chapter = 'Making the starter';
	$failed  = array();

	$check = static function ( $ok, $message ) use ( &$failed ) {
		if ( ! $ok ) {
			$failed[] = $message;
		}
	};

	$check( false !== strpos( $head['title'], $chapter ), 'the <title> has the chapter title (got "' . $head['title'] . '")' );
	$check( array( $page_2 ) === $head['canonicals'], 'a single canonical, the chapter URL (got ' . json_encode( $head['canonicals'] ) . ')' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	$check( array( $page_1 ) === $head['prev'], 'a single rel=prev, the previous chapter (got ' . json_encode( $head['prev'] ) . ')' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	$check( array( $page_3 ) === $head['next'], 'a single rel=next, the next chapter (got ' . json_encode( $head['next'] ) . ')' ); // phpcs:ignore WordPress.WP.AlternativeFunctions

	if ( isset( $head['metas']['og:url'] ) ) {
		$check( $head['metas']['og:url'] === $page_2, 'og:url is the chapter URL (got ' . $head['metas']['og:url'] . ')' );
	}
	foreach ( array( 'og:title', 'twitter:title' ) as $key ) {
		if ( isset( $head['metas'][ $key ] ) ) {
			$check( false !== strpos( $head['metas'][ $key ], $chapter ), $key . ' has the chapter title (got "' . $head['metas'][ $key ] . '")' );
		}
	}
	if ( isset( $head['metas']['robots'] ) ) {
		$check( false === strpos( $head['metas']['robots'], 'noindex' ), 'the chapter is indexable (robots: ' . $head['metas']['robots'] . ')' );
	}

	if ( $failed ) {
		echo "  FAIL {$label}\n"; // phpcs:ignore WordPress.Security.EscapeOutput
		foreach ( $failed as $message ) {
			echo "       - {$message}\n"; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		return 1;
	}

	echo "  ok   {$label}\n"; // phpcs:ignore WordPress.Security.EscapeOutput
	return 0;
}

exit( pchap_check_seo_main( isset( $argv ) ? $argv : array() ) );
