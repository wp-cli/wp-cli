<?php

namespace WP_CLI\Loggers;

class Regular {

	private function _line( $message, $label, $color, $handle = STDOUT ) {
		fwrite( $handle, \WP_CLI::colorize( "$color$label:%n" ) . " $message\n" );
	}

	function info( $message ) {
		fwrite( STDOUT, $message . "\n" );
	}

	function success( $message, $label ) {
		$this->_line( $message, $label, '%G' );
	}

	function warning( $message, $label ) {
		$this->_line( $message, $label, '%C', STDERR );
	}

	function error( $message, $label ) {
		$this->_line( $message, $label, '%R', STDERR );
	}
}

