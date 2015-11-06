<?php

namespace WP_CLI\Loggers;

/**
 * Base logger class
 */
abstract class Base {

	abstract public function info( $message );

	abstract public function success( $message );

	abstract public function warning( $message );

	/**
	 * Write a message to STDERR, prefixed with "Debug: ".
	 *
	 * Appends time sense last debug() call, and total execution time (e.g. "(3.095s/38.057s)")
	 *
	 * @param string $message Message to write.
	 */
	public function debug( $message ) {
		static $last_call;
		if ( \WP_CLI::get_runner()->config['debug'] ) {
			$total_time = round( microtime( true ) - WP_CLI_START_MICROTIME, 3 );
			$since_last = isset( $last_call ) ? round( microtime( true ) - $last_call, 3 ) : 0.000;
			$last_call = microtime( true );
			$this->_line( "$message ({$since_last}s/{$total_time}s)", 'Debug', '%B', STDERR );
		}
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
	protected function _line( $message, $label, $color, $handle = STDOUT ) {
		$label = \cli\Colors::colorize( "$color$label:%n", $this->in_color );
		$this->write( $handle, "$label $message\n" );
	}

}
