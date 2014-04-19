<?php

namespace WP_CLI\Loggers;

class Regular {

	function __construct( $in_color ) {
		$this->in_color = $in_color;
		$this->stdout = fopen('php://stdout', 'w');
		$this->stderr = fopen('php://stderr', 'w');
	}

	protected function write( $handle, $str ) {

		fwrite( $this->$handle, $str );
	}

	private function _line( $message, $label, $color, $handle = 'stdout' ) {
		$label = \cli\Colors::colorize( "$color$label:%n", $this->in_color );
		$this->write( $handle, "$label $message\n" );
	}

	function info( $message ) {
		$this->write( 'stdout', $message . "\n" );
	}

	function success( $message ) {
		$this->_line( $message, 'Success', '%G' );
	}

	function warning( $message ) {
		$this->_line( $message, 'Warning', '%C', 'stderr' );
	}

	function error( $message ) {
		$this->_line( $message, 'Error', '%R', 'stderr' );
	}
}

