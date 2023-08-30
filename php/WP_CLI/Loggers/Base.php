<?php

namespace WP_CLI\Loggers;

use cli\Colors;
use WP_CLI;
use WP_CLI\Runner;

/**
 * Base logger class
 */
abstract class Base {

	protected $in_color = false;

	abstract public function info( $message );

	abstract public function success( $message );

	abstract public function warning( $message );

	/**
	 * Retrieve the runner instance from the base CLI object. This facilitates
	 * unit testing, where the WP_CLI instance isn't available
	 *
	 * @return Runner Instance of the runner class
	 */
	protected function get_runner() {
		return WP_CLI::get_runner();
	}

	/**
	 * Write a message to STDERR, prefixed with "Debug: ".
	 *
	 * @param string $message Message to write.
	 * @param string|bool $group Organize debug message to a specific group.
	 * Use `false` for no group.
	 */
	public function debug( $message, $group = false ) {
		static $start_time = null;
		if ( null === $start_time ) {
			$start_time = microtime( true );
		}
		$debug = $this->get_runner()->config['debug'];
		if ( ! $debug ) {
			return;
		}
		if ( true !== $debug && $group !== $debug ) {
			return;
		}
		$time   = round( microtime( true ) - ( defined( 'WP_CLI_START_MICROTIME' ) ? WP_CLI_START_MICROTIME : $start_time ), 3 );
		$prefix = 'Debug';
		if ( $group && true === $debug ) {
			$prefix = 'Debug (' . $group . ')';
		}
		$this->_line( "$message ({$time}s)", $prefix, '%B', STDERR );
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
	protected function _line( $message, $label, $color, $handle = STDOUT ) { // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore -- Used in third party extensions.
		if ( class_exists( 'cli\Colors' ) ) {
			$label = Colors::colorize( "$color$label:%n", $this->in_color );
		} else {
			$label = "$label:";
		}
		$this->write( $handle, "$label $message\n" );
	}
}
