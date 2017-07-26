<?php

class HelpTest extends PHPUnit_Framework_TestCase {

	public function testPassThroughPagerProcDisabled() {
		$err_msg = 'Warning: check_proc_available() failed in pass_through_pager().';

		$cmd = "WP_CLI_PHP_ARGS='-ddisable_functions=proc_open' bin/wp help --debug 2>&1";
		$output = array();
		exec( $cmd, $output );
		$this->assertTrue( count( $output ) > 0 );
		$last = array_pop( $output );
		$this->assertTrue( false !== strpos( trim( $last ), $err_msg ) );

		$cmd = "WP_CLI_PHP_ARGS='-ddisable_functions=proc_close' bin/wp help --debug 2>&1";
		$output = array();
		exec( $cmd, $output );
		$this->assertTrue( count( $output ) > 0 );
		$last = array_pop( $output );
		$this->assertTrue( false !== strpos( trim( $last ), $err_msg ) );
	}

}
