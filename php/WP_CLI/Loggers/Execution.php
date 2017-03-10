<?php

namespace WP_CLI\Loggers;

/**
 * Execution logger captures all STDOUT and STDERR writes
 */
class Execution extends Base {

	/**
	 * Captured writes to STDOUT.
	 */
	public $stdout = '';

	/**
	 * Captured writes to STDERR.
	 */
	public $stderr = '';

	/**
	 * Write an informational message to STDOUT.
	 *
	 * @param string $message Message to write.
	 */
	public function info( $message ) {
		$this->write( 'STDOUT', $message . "\n" );
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
		$this->_line( $message, 'Warning', '%C', 'STDERR' );
	}

	/**
	 * Write an message to STDERR, prefixed with "Error: ".
	 *
	 * @param string $message Message to write.
	 */
	public function error( $message ) {
		$this->_line( $message, 'Error', '%R', 'STDERR' );
	}

	/**
	 * Similar to error( $message ), but outputs $message in a red box
	 *
	 * @param  array $message Message to write.
	 */
	public function error_multi_line( $message_lines ) {
		$message = implode( "\n", $message_lines );

		$this->write( 'STDERR', \WP_CLI::colorize( "%RError:%n\n$message\n" ) );
		$this->write( 'STDERR', \WP_CLI::colorize( "%R---------%n\n\n" ) );
	}

	/**
	 * Write a string to a resource.
	 *
	 * @param resource $handle Commonly STDOUT or STDERR.
	 * @param string $str Message to write.
	 */
	protected function write( $handle, $str ) {
		switch( $handle ) {
			case 'STDOUT':
				$this->stdout .= $str;
				break;
			case 'STDERR':
				$this->stderr .= $str;
				break;
		}
	}
}
