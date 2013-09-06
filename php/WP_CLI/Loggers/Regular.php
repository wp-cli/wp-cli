<?php

namespace WP_CLI\Loggers;

class Regular {

	private function _line( $message, $label, $color, $handle = STDOUT ) {
		fwrite( $handle, \WP_CLI::colorize( "$color$label:%n" ) . " $message\n" );
	}

	function info( $message ) {
		fwrite( STDOUT, $message . "\n" );
	}

	function success( $message ) {
		$this->_line( $message, 'Success', '%G' );
	}

	function warning( $message ) {
		$this->_line( $message, 'Warning', '%C', STDERR );
	}

	function error( $message ) {
		$this->_line( $message, 'Error', '%R', STDERR );
	}
}

