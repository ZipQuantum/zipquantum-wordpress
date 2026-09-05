<?php

use PHPUnit\Framework\TestCase;

final class SmokeTest extends TestCase {
	public function test_main_plugin_file_has_frozen_identity(): void {
		$contents = file_get_contents( dirname( __DIR__ ) . '/zipquantum-smart-links.php' );
		$this->assertStringContainsString( 'Plugin Name: ZipQuantum – Smart Links & QR Codes', $contents );
		$this->assertStringContainsString( "Text Domain: zipquantum-smart-links", $contents );
		$this->assertStringContainsString( "License: GPL-2.0-or-later", $contents );
	}

	public function test_readme_discloses_external_service_and_no_tracking(): void {
		$contents = file_get_contents( dirname( __DIR__ ) . '/readme.txt' );
		$this->assertStringContainsString( '== External Service ==', $contents );
		$this->assertStringContainsString( 'visitor fingerprints', $contents );
		$this->assertStringContainsString( 'https://zq.tn/privacy-policy/', $contents );
	}

	public function test_wordpress_identifiers_use_the_unique_long_prefix(): void {
		$root      = dirname( __DIR__ );
		$patterns   = array(
			'/ZQ_/',
			'/zq_/',
			'/zq-/',
			'/ZQSmartLinks/',
			'/ZIPQ(?!UANTUM)/',
			'/zipq(?!uantum)/',
		);
		$violations = array();
		$paths      = array(
			$root . '/zipquantum-smart-links.php',
			$root . '/uninstall.php',
			$root . '/includes',
			$root . '/assets',
		);

		foreach ( $paths as $path ) {
			$files = is_dir( $path )
				? new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $path, FilesystemIterator::SKIP_DOTS ) )
				: array( new SplFileInfo( $path ) );

			foreach ( $files as $file ) {
				if ( ! $file->isFile() || ! preg_match( '/\.(php|js|css)$/', $file->getPathname() ) ) {
					continue;
				}

				$contents = file_get_contents( $file->getPathname() );
				foreach ( $patterns as $pattern ) {
					if ( preg_match( $pattern, $contents ) ) {
						$violations[] = str_replace( $root . DIRECTORY_SEPARATOR, '', $file->getPathname() ) . ' matches ' . $pattern;
					}
				}
			}
		}

		$this->assertSame( array(), $violations, implode( PHP_EOL, $violations ) );
		$this->assertStringContainsString( "final class ZIPQUANTUM_Options", file_get_contents( $root . '/includes/class-zipquantum-options.php' ) );
	}
}
