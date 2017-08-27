<?php

namespace WP_CLI;

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
	 * @var array Environment variables to set when running the command.
	 */
	private $env;

	/**
	 * @var array Descriptor spec for `proc_open()`.
	 */
	private static $descriptors = array(
		0 => STDIN,
		1 => array( 'pipe', 'w' ),
		2 => array( 'pipe', 'w' ),
	);

	/**
	 * @var bool Whether to log run time info or not.
	 */
	public static $log_run_times = false;

	/**
	 * @var array Array of process run time info, keyed by process command, each a 2-element array containing run time and run count.
	 */
	public static $run_times = array();

	/**
	 * @param string $command Command to execute.
	 * @param string $cwd Directory to execute the command in.
	 * @param array $env Environment variables to set when running the command.
	 *
	 * @return Process
	 */
	public static function create( $command, $cwd = null, $env = array() ) {
		$proc = new self;

		$proc->command = $command;
		$proc->cwd = $cwd;
		$proc->env = $env;

		return $proc;
	}

	private function __construct() {}

	/**
	 * Run the command.
	 *
	 * @return ProcessRun
	 */
	public function run() {
		$start_time = microtime( true );

		$proc = proc_open( $this->command, self::$descriptors, $pipes, $this->cwd, $this->env );

		$stdout = stream_get_contents( $pipes[1] );
		fclose( $pipes[1] );

		$stderr = stream_get_contents( $pipes[2] );
		fclose( $pipes[2] );

		$return_code = proc_close( $proc );

		$run_time = microtime( true ) - $start_time;

		if ( self::$log_run_times ) {
			if ( ! isset( self::$run_times[ $this->command ] ) ) {
				self::$run_times[ $this->command ] = array( 0, 0 );
			}
			self::$run_times[ $this->command ][0] += $run_time;
			self::$run_times[ $this->command ][1]++;
		}

		return new ProcessRun( array(
			'stdout' => $stdout,
			'stderr' => $stderr,
			'return_code' => $return_code,
			'command' => $this->command,
			'cwd' => $this->cwd,
			'env' => $this->env,
			'run_time' => $run_time,
		) );
	}

	/**
	 * Run the command, but throw an Exception on error.
	 *
	 * @return ProcessRun
	 */
	public function run_check() {
		$r = $this->run();

		// $r->STDERR is incorrect, but kept incorrect for backwards-compat
		if ( $r->return_code || !empty( $r->STDERR ) ) {
			throw new \RuntimeException( $r );
		}

		return $r;
	}
}
