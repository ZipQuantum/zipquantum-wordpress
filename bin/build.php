<?php
/**
 * Build a clean, tracked-only WordPress.org ZIP and integrity manifest.
 */

$root          = dirname( __DIR__ );
$dist          = $root . DIRECTORY_SEPARATOR . 'dist';
$zip_path      = $dist . DIRECTORY_SEPARATOR . 'zipquantum-smart-links.zip';
$manifest_path = $dist . DIRECTORY_SEPARATOR . 'zipquantum-smart-links.manifest.json';
$sha_path      = $zip_path . '.sha256';
$sums_path     = $dist . DIRECTORY_SEPARATOR . 'zipquantum-smart-links.SHA256SUMS';

if ( ! extension_loaded( 'zip' ) ) {
	fwrite( STDERR, "The PHP zip extension is required.\n" );
	exit( 1 );
}

function zq_git( array $arguments, string $root ): string {
	$command = array_merge( array( 'git' ), $arguments );
	$spec    = array(
		0 => array( 'pipe', 'r' ),
		1 => array( 'pipe', 'w' ),
		2 => array( 'pipe', 'w' ),
	);
	$process = proc_open( $command, $spec, $pipes, $root );
	if ( ! is_resource( $process ) ) {
		fwrite( STDERR, "Unable to start Git.\n" );
		exit( 1 );
	}
	fclose( $pipes[0] );
	$stdout = stream_get_contents( $pipes[1] );
	$stderr = stream_get_contents( $pipes[2] );
	fclose( $pipes[1] );
	fclose( $pipes[2] );
	$status = proc_close( $process );
	if ( 0 !== $status ) {
		fwrite( STDERR, "Git command failed: " . trim( $stderr ) . "\n" );
		exit( 1 );
	}
	return $stdout;
}

$status = zq_git( array( 'status', '--porcelain=v1', '--untracked-files=all' ), $root );
if ( '' !== trim( $status ) && '1' !== getenv( 'ZQ_ALLOW_DIRTY_BUILD' ) ) {
	fwrite( STDERR, "Refusing to package a dirty worktree. Commit reviewed files first, or set ZQ_ALLOW_DIRTY_BUILD=1 for a non-release local test.\n" );
	exit( 1 );
}

if ( ! is_dir( $dist ) && ! mkdir( $dist, 0777, true ) && ! is_dir( $dist ) ) {
	fwrite( STDERR, "Could not create dist directory.\n" );
	exit( 1 );
}

$excluded_roots = array( '.github', 'bin', 'dist', 'docs', 'output', 'scripts', 'tests', 'wordpress-org-assets' );
$excluded_files = array( '.gitignore', 'composer.json', 'composer.lock', 'phpcs.xml.dist', 'phpunit.xml.dist', 'README.md' );
$tracked        = array_values( array_filter( explode( "\0", zq_git( array( 'ls-files', '-z', '--' ), $root ) ) ) );
sort( $tracked, SORT_STRING );

$zip = new ZipArchive();
if ( true !== $zip->open( $zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
	fwrite( STDERR, "Could not create ZIP.\n" );
	exit( 1 );
}

$release_files = array();
foreach ( $tracked as $relative ) {
	$relative = str_replace( '\\', '/', $relative );
	$first    = strtok( $relative, '/' );
	if ( in_array( $first, $excluded_roots, true ) || in_array( $relative, $excluded_files, true ) ) {
		continue;
	}
	$source = $root . DIRECTORY_SEPARATOR . str_replace( '/', DIRECTORY_SEPARATOR, $relative );
	if ( ! is_file( $source ) ) {
		fwrite( STDERR, "Tracked release file is missing: {$relative}\n" );
		$zip->close();
		exit( 1 );
	}
	$archive_path                   = 'zipquantum-smart-links/' . $relative;
	$release_files[ $archive_path ] = hash_file( 'sha256', $source );
	$zip->addFile( $source, $archive_path );
}

$zip->close();
$size = filesize( $zip_path );
if ( false === $size || $size > 10 * 1024 * 1024 ) {
	fwrite( STDERR, "ZIP exceeds the 10 MB WordPress.org limit or its size is unreadable.\n" );
	exit( 1 );
}

$manifest = array(
	'schema'         => 1,
	'component'      => 'wordpress-plugin',
	'git_commit'     => trim( zq_git( array( 'rev-parse', 'HEAD' ), $root ) ),
	'dirty_override' => '' !== trim( $status ),
	'archive'        => basename( $zip_path ),
	'archive_sha256' => hash_file( 'sha256', $zip_path ),
	'file_count'     => count( $release_files ),
	'release_files'  => $release_files,
);
file_put_contents( $manifest_path, json_encode( $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR ) . "\n" );
file_put_contents( $sha_path, $manifest['archive_sha256'] . '  ' . basename( $zip_path ) . "\n" );
file_put_contents(
	$sums_path,
	$manifest['archive_sha256'] . '  ' . basename( $zip_path ) . "\n" .
	hash_file( 'sha256', $manifest_path ) . '  ' . basename( $manifest_path ) . "\n"
);

fwrite( STDOUT, $zip_path . ' (' . $size . ' bytes, ' . count( $release_files ) . " tracked files)\n" );
