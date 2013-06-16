<?php

namespace WP_CLI\Loggers;

class Regular {

	private function _line( $message, $handle = STDOUT ) {
		fwrite( $handle, \WP_CLI::colorize( $message . "\n" ) );
	}

	function info( $message ) {
		fwrite( STDOUT, $message . "\n" );
	}

	function success( $message, $label ) {
		$this->_line( "%G$label:%n $message" );
	}

	function warning( $message, $label ) {
		$this->_line( "%C$label:%n $message", STDERR );
	}

	function error( $message, $label ) {
		$msg = '%R' . $label . ': %n' . $message;
		$this->_line( $msg, STDERR );
	}
}

