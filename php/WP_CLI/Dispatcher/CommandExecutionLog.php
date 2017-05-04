<?php

namespace WP_CLI\Dispatcher;

use WP_CLI;
use WP_CLI\Utils;

/**
 * Encapsulates analytical information about command execution.
 *
 * @package WP_CLI
 */
final class CommandExecutionLog implements \JsonSerializable {

	/**
	 * Unique identifier for this context.
	 *
	 * @var string
	 */
	private $uuid;

	/**
	 * Command being run, as a string.
	 *
	 * @var string
	 */
	private $command;

	/**
	 * Arguments passed to the command.
	 *
	 * @var array
	 */
	private $args;

	/**
	 * Arguments passed to the command.
	 *
	 * @var array
	 */
	private $assoc_args;

	/**
	 * Start time for the timer.
	 *
	 * @var integer|null
	 */
	private $start_time = null;

	/**
	 * Execution time for the command.
	 *
	 * @var integer|null
	 */
	private $exec_time = null;

	/**
	 * Instantiate a CommandExecution object.
	 *
	 * @param string $command    Command being run.
	 * @param array  $args       Arguments passed to the command.
	 * @param array  $assoc_args Arguments passed to the command.
	 */
	public function __construct( $command, $args, $assoc_args ) {
		$this->command = $command;
		$this->args = $args;
		$this->assoc_args = $assoc_args;
		$home = getenv( 'HOME' );
		if ( ! $home ) {
			// sometime in windows $HOME is not defined
			$home = getenv( 'HOMEDRIVE' ) . getenv( 'HOMEPATH' );
		}
		// @todo is this the best place for the uuid between sessions?
		$uuid_path = $home . '/.wp-cli/uuid';
		if ( is_file( $uuid_path ) ) {
			$this->uuid = file_get_contents( $uuid_path );
		} else {
			$this->uuid = self::gen_uuid();
			file_put_contents( $uuid_path, $this->uuid );
		}
	}

	/**
	 * Start the execution timer.
	 */
	public function start() {
		$this->start_time = microtime( true );
	}

	/**
	 * Stop the execution timer.
	 */
	public function stop() {
		$exec_time = microtime( true ) - $this->start_time;
		$this->start_time = null;
		$this->exec_time = round( $exec_time, 3 );
	}

	/**
	 * Prepare CommandExecution object for delivery.
	 *
	 * @return array
	 */
	public function jsonSerialize() {
		$type = Utils\inside_phar() ? 'phar' : 'composer';
		return array(
			'uuid'           => $this->uuid,
			'command'        => $this->command,
			'args'           => $this->args,
			'assoc_args'     => $this->assoc_args,
			'wp_version'     => isset( $GLOBALS['wp_version'] ) ? $GLOBALS['wp_version'] : null,
			'php_version'    => PHP_VERSION,
			'wp_cli_version' => WP_CLI_VERSION,
			'wp_cli_type'    => $type,
			'exec_time'      => $this->exec_time,
		);
	}

	/**
	 * Generate a UUID v4
	 *
	 * @return string
	 */
	private static function gen_uuid() {
		return sprintf( '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
			// 32 bits for "time_low"
			mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ),

			// 16 bits for "time_mid"
			mt_rand( 0, 0xffff ),

			// 16 bits for "time_hi_and_version",
			// four most significant bits holds version number 4
			mt_rand( 0, 0x0fff ) | 0x4000,

			// 16 bits, 8 bits for "clk_seq_hi_res",
			// 8 bits for "clk_seq_low",
			// two most significant bits holds zero and one for variant DCE1.1
			mt_rand( 0, 0x3fff ) | 0x8000,

			// 48 bits for "node"
			mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff )
		);
	}

}
