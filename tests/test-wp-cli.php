<?php

class WP_CLI_Test extends PHPUnit_Framework_TestCase {

	public function testGetPHPBinary() {
		$this->assertSame( WP_CLI\Utils\get_php_binary(), WP_CLI::get_php_binary() );
	}
}
