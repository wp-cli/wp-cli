<?php

namespace WP_CLI;

use RuntimeException;

/**
 * Run a system process, and learn what happened.
 */
class Process {
	/**
	 * @var string The full command to execute by the system.
	 */
	private $command;

	/**
	 * @var string|null The path of the working directory for the process or NULL if not specified (defaults to current working directory).
	 */
	private $cwd;

	/**
	 * @var array|null Environment variables to set when running the command.
	 */
	private $env;

	/**
	 * @var array Descriptor spec for `proc_open()`.
	 */
	private static $descriptors = [
		0 => STDIN,
		1 => [ 'pipe', 'w' ],
		2 => [ 'pipe', 'w' ],
	];

	/**
	 * @var bool Whether to log run time info or not.
	 */
	public static $log_run_times = false;

	/**
	 * @var array Array of process run time info, keyed by process command, each a 2-element array containing run time and run count.
	 */
	public static $run_times = [];

	/**
	 * @param string      $command Command to execute.
	 * @param string|null $cwd     Directory to execute the command in.
	 * @param array|null  $env     Environment variables to set when running the command.
	 *
	 * @return Process
	 */
	public static function create( $command, $cwd = null, $env = [] ) {
		$proc = new self();

		$proc->command = $command;
		$proc->cwd     = $cwd;
		$proc->env     = $env;

		return $proc;
	}

	private function __construct() {}

	/**
	 * Run the command.
	 *
	 * @return ProcessRun
	 */
	public function run() {
		Utils\check_proc_available( 'Process::run' );

		$start_time = microtime( true );

		/**
		 * @var array<int, resource> $pipes
		 */
		$pipes = [];
		$proc  = Utils\proc_open_compat( $this->command, self::$descriptors, $pipes, $this->cwd, $this->env );

		$stdout = stream_get_contents( $pipes[1] );
		fclose( $pipes[1] );

		$stderr = stream_get_contents( $pipes[2] );
		fclose( $pipes[2] );

		$return_code = $proc ? proc_close( $proc ) : -1;

		$run_time = microtime( true ) - $start_time;

		if ( self::$log_run_times ) {
			if ( ! isset( self::$run_times[ $this->command ] ) ) {
				self::$run_times[ $this->command ] = [ 0, 0 ];
			}
			self::$run_times[ $this->command ][0] += $run_time;
			++self::$run_times[ $this->command ][1];
		}

		return new ProcessRun(
			[
				'stdout'      => $stdout,
				'stderr'      => $stderr,
				'return_code' => $return_code,
				'command'     => $this->command,
				'cwd'         => $this->cwd,
				'env'         => $this->env,
				'run_time'    => $run_time,
			]
		);
	}

	/**
	 * Run the command, but throw an Exception on error.
	 *
	 * @return ProcessRun
	 */
	public function run_check() {
		$r = $this->run();

		if ( $r->return_code ) {
			throw new RuntimeException( $r );
		}

		return $r;
	}

	/**
	 * Run the command, but throw an Exception on error.
	 * Same as `run_check()` above, but checks the correct stderr.
	 *
	 * @return ProcessRun
	 */
	public function run_check_stderr() {
		$r = $this->run();

		if ( $r->return_code ) {
			throw new RuntimeException( $r );
		}

		if ( ! empty( $r->stderr ) ) {
			// If the only thing that STDERR caught was the Requests deprecated message, ignore it.
			// This is a temporary fix until we have a better solution for dealing with Requests
			// as a dependency shared between WP Core and WP-CLI.
			$stderr_lines = array_filter( explode( "\n", $r->stderr ) );
			if ( 1 === count( $stderr_lines ) ) {
				$stderr_line = $stderr_lines[0];
				if (
					false !== strpos(
						$stderr_line,
						'The PSR-0 `Requests_...` class names in the Request library are deprecated.'
					)
				) {
					return $r;
				}
			}

			throw new RuntimeException( $r );
		}

		return $r;
	}
}
