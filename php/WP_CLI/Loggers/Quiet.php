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

	/**
	 * Similar to error( $message ), but outputs $message in a red box
	 *
	 * @param  array $message Message to write.
	 */
	public function error_multi_line( $message_lines ) {
		$message = implode( "\n", $message_lines );

		fwrite( STDERR, \WP_CLI::colorize( "%RError:%n\n$message\n" ) );
		fwrite( STDERR, \WP_CLI::colorize( "%R---------%n\n\n" ) );
	}
}
