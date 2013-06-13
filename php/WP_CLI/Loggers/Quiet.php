<?php

namespace WP_CLI\Loggers;

class Quiet {

	function line( $message ) {
		// nothing
	}

	function success( $message, $label ) {
		// nothing
	}

	function warning( $message, $label ) {
		// nothing
	}

	function error( $message, $label ) {
		$msg = '%R' . $label . ': %n' . $message;
		fwrite( STDERR, \WP_CLI::colorize( $msg . "\n" ) );
	}
}

