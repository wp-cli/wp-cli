<?php

namespace WP_CLI\Loggers;

use WP_CLI;

/**
 * Quiet logger only logs errors.
 */
class Quiet extends Base {

	/**
	 * @param bool $in_color Whether or not to Colorize strings.
	 */
	public function __construct( $in_color = false ) {
		$this->in_color = $in_color;
	}

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
		$this->_line( $message, 'Error', '%R', STDERR );
	}

	/**
	 * Similar to error( $message ), but outputs $message in a red box.
	 *
	 * @param  array $message_lines Message to write.
	 */
	public function error_multi_line( $message_lines ) {
		$message = implode( "\n", $message_lines );

		$this->_line( $message, 'Error', '%R', STDERR );
		$this->_line( '', '---------', '%R', STDERR );
	}
}
