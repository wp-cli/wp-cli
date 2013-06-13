<?php

namespace WP_CLI\Loggers;

class Regular {

	private function _line( $message, $handle = STDOUT ) {
		fwrite( $handle, \WP_CLI::colorize( $message . "\n" ) );
	}

	function line( $message ) {
		$this->_line( $message );
	}

	function success( $message, $label ) {
		$this->line( '%G' . $label . ': %n' . $message );
	}

	function warning( $message, $label ) {
		$msg = '%C' . $label . ': %n' . $message;
		$this->_line( $msg, STDERR );
	}

	function error( $message, $label ) {
		$msg = '%R' . $label . ': %n' . $message;
		$this->_line( $msg, STDERR );
	}
}

