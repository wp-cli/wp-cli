<?php

use WP_CLI\Tests\TestCase;

class WP_CLI_Test extends TestCase {

	public function testGetPHPBinary() {
		$this->assertSame( WP_CLI\Utils\get_php_binary(), WP_CLI::get_php_binary() );
	}

	public function testErrorToString() {
		$this->expectException( 'InvalidArgumentException' );
		$this->expectExceptionMessage( "Unsupported argument type passed to WP_CLI::error_to_string(): 'boolean'" );
		WP_CLI::error_to_string( true );
	}
}
