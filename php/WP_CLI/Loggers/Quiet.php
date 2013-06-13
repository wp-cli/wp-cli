<?php

namespace WP_CLI\Loggers;

class Quiet {

	function success( $message, $label ) {
		// nothing
	}

	function warning( $message, $label ) {
		// nothing
	}

	function error( $message, $label ) {
		fwrite( STDERR, \WP_CLI::colorize( "%R$label:%n $message\n" ) );
	}
}

