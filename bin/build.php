<?php
/**
 * Build a clean WordPress.org-ready ZIP.
 */

$root     = dirname( __DIR__ );
$dist     = $root . DIRECTORY_SEPARATOR . 'dist';
$zip_path = $dist . DIRECTORY_SEPARATOR . 'zipquantum-smart-links.zip';

if ( ! extension_loaded( 'zip' ) ) {
	fwrite( STDERR, "The PHP zip extension is required.\n" );
	exit( 1 );
}

if ( ! is_dir( $dist ) && ! mkdir( $dist, 0777, true ) && ! is_dir( $dist ) ) {
	fwrite( STDERR, "Could not create dist directory.\n" );
	exit( 1 );
}

$excluded_roots = array( '.git', '.github', '.playwright-cli', 'bin', 'dist', 'docs', 'output', 'tests', 'vendor', 'wordpress-org-assets' );
$excluded_files = array( '.gitignore', '.phpunit.result.cache', 'composer.json', 'composer.lock', 'phpcs.xml.dist', 'phpunit.xml.dist', 'README.md' );
$zip            = new ZipArchive();
if ( true !== $zip->open( $zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
	fwrite( STDERR, "Could not create ZIP.\n" );
	exit( 1 );
}

$iterator = new RecursiveIteratorIterator(
	new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ),
	RecursiveIteratorIterator::LEAVES_ONLY
);

foreach ( $iterator as $file ) {
	if ( ! $file->isFile() ) {
		continue;
	}
	$relative = str_replace( '\\', '/', substr( $file->getPathname(), strlen( $root ) + 1 ) );
	$first    = strtok( $relative, '/' );
	if ( in_array( $first, $excluded_roots, true ) || in_array( $relative, $excluded_files, true ) ) {
		continue;
	}
	$zip->addFile( $file->getPathname(), 'zipquantum-smart-links/' . $relative );
}

$zip->close();
$size = filesize( $zip_path );
if ( $size > 10 * 1024 * 1024 ) {
	fwrite( STDERR, "ZIP exceeds the 10 MB WordPress.org limit.\n" );
	exit( 1 );
}

fwrite( STDOUT, $zip_path . ' (' . $size . " bytes)\n" );
