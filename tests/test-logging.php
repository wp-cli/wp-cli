<?php

class MockRegularLogger extends WP_CLI\Loggers\Regular {

	protected function get_runner() {
		return (object) array (
			'config' => array (
				'debug' => true
			)
		);
	}

	protected function write( $handle, $str ) {
		echo $str;
	}
}

class MockQuietLogger extends WP_CLI\Loggers\Quiet {

	protected function get_runner() {
		return (object) array (
			'config' => array (
				'debug' => true
			)
		);
	}
}

class LoggingTests extends PHPUnit_Framework_TestCase {

	function testLogDebug() {
		define( 'WP_CLI_START_MICROTIME', microtime( true ) );
		$message = 'This is a test message.';

		$regularLogger = new MockRegularLogger( false );
		$this->expectOutputRegex( "/Debug: {$message} \(\d+\.*\d*s\)/" );
		$regularLogger->debug( $message );

		$quietLogger = new MockQuietLogger();
		$this->expectOutputRegex( "/Debug: {$message} \(\d+\.*\d*s\)/" );
		$quietLogger->debug( $message );
	}

	function testLogEscaping() {
		$logger = new MockRegularLogger( false );

		$message = 'foo%20bar';

		$this->expectOutputString( "Success: $message\n" );
		$logger->success( $message );
	}
}
