<?php

use WP_CLI\Tests\TestCase;

class WPCLITest extends TestCase {

	public function testGetPHPBinary(): void {
		$this->assertSame( WP_CLI\Utils\get_php_binary(), WP_CLI::get_php_binary() );
	}

	public function testErrorToString(): void {
		$this->expectException( 'InvalidArgumentException' );
		$this->expectExceptionMessage( "Unsupported argument type passed to WP_CLI::error_to_string(): 'boolean'" );
		// @phpstan-ignore argument.type
		WP_CLI::error_to_string( true );
	}
}
