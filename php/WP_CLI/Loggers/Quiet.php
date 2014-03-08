<?php

namespace WP_CLI\Loggers;

class Quiet {

	function info( $message ) {
		// nothing
	}

	function success( $message ) {
		// nothing
	}

	function warning( $message ) {
		// nothing
	}

	function error( $message ) {
		fwrite( STDERR, \WP_CLI::colorize( "%RError:%n $message\n" ) );
	}
}

