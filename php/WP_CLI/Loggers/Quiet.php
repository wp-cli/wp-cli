<?php

namespace WP_CLI\Loggers;

use WP_CLI;

/**
 * Quiet logger only logs errors.
 */
class Quiet extends Base {

	/**
	 * Informational messages aren't logged.
	 *
	 * @param string $message Message to write.
	 */
	public function info( $message ) {
		// Nothing.
	}

	/**
	 * Success messages aren't logged.
	 *
	 * @param string $message Message to write.
	 */
	public function success( $message ) {
		// Nothing.
	}

	/**
	 * Warning messages aren't logged.
	 *
	 * @param string $message Message to write.
	 */
	public function warning( $message ) {
		// Nothing.
	}

	/**
	 * Write an error message to STDERR, prefixed with "Error: ".
	 *
	 * @param string $message Message to write.
	 */
	public function error( $message ) {
		$this->write( STDERR, WP_CLI::colorize( "%RError:%n $message\n" ) );
	}

	/**
	 * Similar to error( $message ), but outputs $message in a red box.
	 *
	 * @param  array $message_lines Message to write.
	 */
	public function error_multi_line( $message_lines ) {
		$message = implode( "\n", $message_lines );

		$this->write( STDERR, WP_CLI::colorize( "%RError:%n\n$message\n" ) );
		$this->write( STDERR, WP_CLI::colorize( "%R---------%n\n\n" ) );
	}
}
