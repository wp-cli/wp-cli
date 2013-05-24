<?php

namespace WP_CLI\Loggers;

class Regular {

	private $colorize;

	function __construct( $colorize ) {
		$this->colorize = $colorize;
	}

	function line( $message, $handle = STDOUT ) {
		fwrite( $handle, \cli\Colors::colorize( $message . "\n", $this->colorize ) );
	}

	function success( $message, $label ) {
		$this->line( '%G' . $label . ': %n' . $message );
	}

	function warning( $message, $label ) {
		$msg = '%C' . $label . ': %n' . \WP_CLI::error_to_string( $message );
		$this->line( $msg, STDERR );
	}

	function error( $message, $label ) {
		$msg = '%R' . $label . ': %n' . \WP_CLI::error_to_string( $message );
		$this->line( $msg, STDERR );
	}
}

