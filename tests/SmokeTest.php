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
}
