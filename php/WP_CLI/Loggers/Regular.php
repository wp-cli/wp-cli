<?php

namespace WP_CLI\Loggers;

/**
 * Default logger for success, warning, error, and standard messages.
 */
class Regular {

	/**
	 * @param bool $in_color Whether or not to Colorize strings.
	 */
	function __construct( $in_color ) {
		$this->in_color = $in_color;
	}

	/**
	 * Write a string to a resource.
	 *
	 * @param resource $handle Commonly STDOUT or STDERR.
	 * @param string $str Message to write.
	 */
	protected function write( $handle, $str ) {
		fwrite( $handle, $str );
	}

	/**
	 * Output one line of message to a resource.
	 *
	 * @param string $message Message to write.
	 * @param string $label Prefix message with a label.
	 * @param string $color Colorize label with a given color.
	 * @param resource $handle Resource to write to. Defaults to STDOUT.
	 */
	private function _line( $message, $label, $color, $handle = STDOUT ) {
		$label = \cli\Colors::colorize( "$color$label:%n", $this->in_color );
		$this->write( $handle, "$label $message\n" );
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
	 * Similar to error( $message ), but outputs $message in a red box
	 *
	 * @param  array $message Message to write.
	 */
	public function error_multi_line( $message_lines ) {
		// convert tabs to four spaces, as some shells will output the tabs as variable-length
		$message_lines = array_map( function( $line ) {
			return str_replace( "\t", '    ', $line );
		} , $message_lines );

		$longest = max( array_map( 'strlen', $message_lines ) );

		// write an empty line before the message
		$empty_line = \cli\Colors::colorize( '%w%1 ' . str_repeat( ' ', $longest ) . ' %n' );
		$this->write( STDERR, "\n\t$empty_line\n" );

		foreach ( $message_lines as $line ) {
			$padding = str_repeat( ' ', $longest - strlen( $line ) );
			$line = \cli\Colors::colorize( "%w%1 $line $padding%n" );
			$this->write( STDERR, "\t$line\n" );
		}

		// write an empty line after the message
		$this->write( STDERR, "\t$empty_line\n\n" );
	}
}
