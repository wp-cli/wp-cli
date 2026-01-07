<?php

use WP_CLI\Tests\TestCase;

/**
 * Test the boot-fs.php extension checks
 */
class BootFsTest extends TestCase {

	/**
	 * Test that the boot-fs.php file contains the extension check
	 */
	public function testExtensionCheckExists() {
		$boot_fs_content = file_get_contents( WP_CLI_ROOT . '/php/boot-fs.php' );
		
		// Check that the file contains the extension check
		$this->assertStringContainsString( 'extension_loaded', $boot_fs_content );
		$this->assertStringContainsString( 'mbstring', $boot_fs_content );
		$this->assertStringContainsString( 'iconv', $boot_fs_content );
		$this->assertStringContainsString( 'WP-CLI requires the mbstring or iconv PHP extension', $boot_fs_content );
	}

	/**
	 * Test that at least one of the required extensions is loaded in the current environment
	 */
	public function testAtLeastOneExtensionIsLoaded() {
		$has_mbstring = extension_loaded( 'mbstring' );
		$has_iconv    = extension_loaded( 'iconv' );
		
		// At least one extension should be available
		$this->assertTrue(
			$has_mbstring || $has_iconv,
			'Either mbstring or iconv extension must be available for WP-CLI to function'
		);
	}
}
