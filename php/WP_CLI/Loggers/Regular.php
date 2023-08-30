<?php

namespace WP_CLI\Loggers;

use cli\Colors;

/**
 * Default logger for success, warning, error, and standard messages.
 */
class Regular extends Base {

	/**
	 * @param bool $in_color Whether or not to Colorize strings.
	 */
	public function __construct( $in_color ) {
		$this->in_color = $in_color;
	}

	/**
	 * Write an informational message to STDOUT.
	 *
	 * @param string $message Message to write.
	 */
	public function info( $message ) {
		$this->write( STDOUT, $message . "\n" );
	}

	/**
	 * Write a success message, prefixed with "Success: ".
	 *
	 * @param string $message Message to write.
	 */
	public function success( $message ) {
		$this->_line( $message, 'Success', '%G' );
	}

	/**
	 * Write a warning message to STDERR, prefixed with "Warning: ".
	 *
	 * @param string $message Message to write.
	 */
	public function warning( $message ) {
		$this->_line( $message, 'Warning', '%C', STDERR );
	}

	/**
	 * Write an message to STDERR, prefixed with "Error: ".
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
		// Convert tabs to four spaces, as some shells will output the tabs as variable-length.
		$message_lines = array_map(
			function ( $line ) {
				return str_replace( "\t", '    ', $line );
			},
			$message_lines
		);

		$longest = max( array_map( 'strlen', $message_lines ) );

		// Write an empty line before the message.
		$empty_line = Colors::colorize( '%w%1 ' . str_repeat( ' ', $longest ) . ' %n' );
		$this->write( STDERR, "\n\t$empty_line\n" );

		foreach ( $message_lines as $line ) {
			$padding = str_repeat( ' ', $longest - strlen( $line ) );
			$line    = Colors::colorize( "%w%1 $line $padding%n" );
			$this->write( STDERR, "\t$line\n" );
		}

		// Write an empty line after the message.
		$this->write( STDERR, "\t$empty_line\n\n" );
	}
}
