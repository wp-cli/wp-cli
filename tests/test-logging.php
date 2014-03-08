<?php

class LoggerMock extends WP_CLI\Loggers\Regular {

	protected function write( $handle, $str ) {
		echo $str;
	}
}


class LoggingTests extends PHPUnit_Framework_TestCase {

	function testLogEscaping() {
		$logger = new LoggerMock( false );

		$message = 'foo%20bar';

		$this->expectOutputString( "Success: $message\n" );
		$logger->success( $message );
	}
}

