<?php
/**
 * Run `php -l` on every PHP file of the plugin (development helper).
 *
 * Usage: php bin/php-lint.php
 * Uses the PHP binary that runs this script, so it can be called with any
 * PHP version to check syntax compatibility (e.g. in the CI lint matrix).
 *
 * @package Multipage
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

$mpp_root     = dirname( __DIR__ );
$mpp_excluded = array( '.git', 'vendor', 'node_modules', 'build' );
$mpp_failures = 0;
$mpp_count    = 0;

$mpp_iterator = new RecursiveIteratorIterator(
	new RecursiveCallbackFilterIterator(
		new RecursiveDirectoryIterator( $mpp_root, FilesystemIterator::SKIP_DOTS ),
		static function ( SplFileInfo $file ) use ( $mpp_excluded ) {
			if ( $file->isDir() ) {
				// Skip dot folders (.git, .github, .claude/worktrees copies, .wp-env...).
				if ( 0 === strpos( $file->getFilename(), '.' ) ) {
					return false;
				}
				return ! in_array( $file->getFilename(), $mpp_excluded, true );
			}
			return 'php' === strtolower( $file->getExtension() );
		}
	)
);

foreach ( $mpp_iterator as $mpp_file ) {
	++$mpp_count;
	$mpp_output = array();
	$mpp_status = 0;
	exec( escapeshellarg( PHP_BINARY ) . ' -d display_errors=1 -l ' . escapeshellarg( $mpp_file->getPathname() ) . ' 2>&1', $mpp_output, $mpp_status );
	if ( 0 !== $mpp_status ) {
		++$mpp_failures;
		fwrite( STDERR, implode( PHP_EOL, $mpp_output ) . PHP_EOL );
	}
}

fwrite( STDOUT, sprintf( 'PHP %s: %d file(s) checked, %d error(s).', PHP_VERSION, $mpp_count, $mpp_failures ) . PHP_EOL );

exit( $mpp_failures > 0 ? 1 : 0 );
