<?php

namespace WP_CLI\Loggers;

/**
 * Quiet logger only logs errors.
 */
class Quiet {

	/**
	 * Informational messages aren't logged.
	 *
	 * @param string $message Message to write.
	 */
	public function info( $message ) {
		// nothing
	}

	/**
	 * Success messages aren't logged.
	 *
	 * @param string $message Message to write.
	 */
	public function success( $message ) {
		// nothing
	}

	/**
	 * Warning messages aren't logged.
	 *
	 * @param string $message Message to write.
	 */
	public function warning( $message ) {
		// nothing
	}

	/**
	 * Write an error message to STDERR, prefixed with "Error: ".
	 *
	 * @param string $message Message to write.
	 */
	public function error( $message ) {
		fwrite( STDERR, \WP_CLI::colorize( "%RError:%n $message\n" ) );
	}

}
