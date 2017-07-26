<?php

class WP_CLI_Test extends PHPUnit_Framework_TestCase {

	public function testLaunchProcDisabled() {
		$err_msg = 'Error: Cannot do \'launch\': The PHP functions `proc_open()` and/or `proc_close()` are disabled';

		$cmd = "WP_CLI_PHP_ARGS='-ddisable_functions=proc_open' bin/wp eval 'WP_CLI::launch( null );' --skip-wordpress 2>&1";
		$output = array();
		exec( $cmd, $output );
		$this->assertTrue( 1 === count( $output ) );
		$this->assertTrue( false !== strpos( trim( $output[0] ), $err_msg ) );

		$cmd = "WP_CLI_PHP_ARGS='-ddisable_functions=proc_close' bin/wp eval 'WP_CLI::launch( null );' --skip-wordpress 2>&1";
		$output = array();
		exec( $cmd, $output );
		$this->assertTrue( 1 === count( $output ) );
		$this->assertTrue( false !== strpos( trim( $output[0] ), $err_msg ) );
	}

	public function testRuncommandLaunchProcDisabled() {
		$err_msg = 'Error: Cannot do \'launch option\': The PHP functions `proc_open()` and/or `proc_close()` are disabled';

		$cmd = "WP_CLI_PHP_ARGS='-ddisable_functions=proc_open' bin/wp eval 'WP_CLI::runcommand( null, array( \"launch\" => 1 ) );' --skip-wordpress 2>&1";
		$output = array();
		exec( $cmd, $output );
		$this->assertTrue( 1 === count( $output ) );
		$this->assertTrue( false !== strpos( trim( $output[0] ), $err_msg ) );

		$cmd = "WP_CLI_PHP_ARGS='-ddisable_functions=proc_close' bin/wp eval 'WP_CLI::runcommand( null, array( \"launch\" => 1 ) );' --skip-wordpress 2>&1";
		$output = array();
		exec( $cmd, $output );
		$this->assertTrue( 1 === count( $output ) );
		$this->assertTrue( false !== strpos( trim( $output[0] ), $err_msg ) );
	}

}
