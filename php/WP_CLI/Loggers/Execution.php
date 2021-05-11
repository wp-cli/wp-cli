<?php

namespace WP_CLI\Loggers;

use WP_CLI;

/**
 * Execution logger captures all STDOUT and STDERR writes
 */
class Execution extends Regular {

	/**
	 * Captured writes to STDOUT.
	 */
	public $stdout = '';

	/**
	 * Captured writes to STDERR.
	 */
	public $stderr = '';

	/**
	 * @param bool $in_color Whether or not to Colorize strings.
	 */
	public function __construct( $in_color = false ) { // phpcs:ignore Generic.CodeAnalysis.UselessOverridingMethod.Found -- Provides a default value.
		parent::__construct( $in_color );
	}

	/**
	 * Similar to error( $message ), but outputs $message in a red box.
	 *
	 * @param array $message_lines Message to write.
	 */
	public function error_multi_line( $message_lines ) {
		$message = implode( "\n", $message_lines );

		$this->write( STDERR, WP_CLI::colorize( "%RError:%n\n$message\n" ) );
		$this->write( STDERR, WP_CLI::colorize( "%R---------%n\n\n" ) );
	}

	/**
	 * Write a string to a resource.
	 *
	 * @param resource $handle Commonly STDOUT or STDERR.
	 * @param string $str Message to write.
	 */
	protected function write( $handle, $str ) {
		switch ( $handle ) {
			case STDOUT:
				$this->stdout .= $str;
				break;
			case STDERR:
				$this->stderr .= $str;
				break;
		}
	}

	/**
	 * Starts output buffering, using a callback to capture output from `echo`, `print`, `printf` (which write to the output buffer 'php://output' rather than STDOUT).
	 */
	public function ob_start() {
		ob_start( [ $this, 'ob_start_callback' ], 1 );
	}

	/**
	 * Callback for `ob_start()`.
	 *
	 * @param string $str String to write.
	 * @return string Returns zero-length string so nothing gets written to the output buffer.
	 */
	public function ob_start_callback( $str ) {
		$this->write( STDOUT, $str );
		return '';
	}

	/**
	 * To match `ob_start() above. Does an `ob_end_flush()`.
	 */
	public function ob_end() {
		ob_end_flush();
	}
}
