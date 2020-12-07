<?php

class WP_CLI_Test extends PHPUnit_Framework_TestCase {

	public function testGetPHPBinary() {
		$this->assertSame( WP_CLI\Utils\get_php_binary(), WP_CLI::get_php_binary() );
	}

	public function testErrorToString() {
		$this->setExpectedException( 'InvalidArgumentException', "Unsupported argument type passed to WP_CLI::error_to_string(): 'boolean'" );
		WP_CLI::error_to_string( true );
	}
}
