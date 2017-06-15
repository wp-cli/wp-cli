<?php
namespace WP_CLI;

/**
 * Mock the get_cfg_var function because the opcache.enable_cli isn't editable on runtime
 *
 * @return bool
 */
function get_cfg_var() {
	return false;
}

class OpCache extends \PHPUnit_Framework_TestCase {

	function testOpcache() {
		$runner = new Runner();
		$this->assertTrue( $runner->check_opcache() );
	}
}
