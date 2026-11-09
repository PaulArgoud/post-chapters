<?php
/**
 * Run `php -l` on every PHP file of the plugin (development helper).
 *
 * Usage: php bin/php-lint.php
 * Uses the PHP binary that runs this script, so it can be called with any
 * PHP version to check syntax compatibility (e.g. in the CI lint matrix).
 *
 * @package Post Chapters
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

$pchap_root     = dirname( __DIR__ );
$pchap_excluded = array( '.git', 'vendor', 'node_modules', 'build' );
$pchap_failures = 0;
$pchap_count    = 0;

$pchap_iterator = new RecursiveIteratorIterator(
	new RecursiveCallbackFilterIterator(
		new RecursiveDirectoryIterator( $pchap_root, FilesystemIterator::SKIP_DOTS ),
		static function ( SplFileInfo $file ) use ( $pchap_excluded ) {
			if ( $file->isDir() ) {
				// Skip dot folders (.git, .github, .claude/worktrees copies, .wp-env...).
				if ( 0 === strpos( $file->getFilename(), '.' ) ) {
					return false;
				}
				return ! in_array( $file->getFilename(), $pchap_excluded, true );
			}
			return 'php' === strtolower( $file->getExtension() );
		}
	)
);

foreach ( $pchap_iterator as $pchap_file ) {
	++$pchap_count;
	$pchap_output = array();
	$pchap_status = 0;
	exec( escapeshellarg( PHP_BINARY ) . ' -d display_errors=1 -l ' . escapeshellarg( $pchap_file->getPathname() ) . ' 2>&1', $pchap_output, $pchap_status );
	if ( 0 !== $pchap_status ) {
		++$pchap_failures;
		fwrite( STDERR, implode( PHP_EOL, $pchap_output ) . PHP_EOL );
	}
}

fwrite( STDOUT, sprintf( 'PHP %s: %d file(s) checked, %d error(s).', PHP_VERSION, $pchap_count, $pchap_failures ) . PHP_EOL );

exit( $pchap_failures > 0 ? 1 : 0 );
