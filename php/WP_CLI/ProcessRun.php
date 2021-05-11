<?php

namespace WP_CLI;

/**
 * Results of an executed command.
 */
class ProcessRun {

	/**
	 * The full command executed by the system.
	 *
	 * @var string
	 */
	public $command;

	/**
	 * Captured output from the process' STDOUT.
	 *
	 * @var string
	 */
	public $stdout;

	/**
	 * Captured output from the process' STDERR.
	 *
	 * @var string
	 */
	public $stderr;

	/**
	 * The path of the working directory for the process or NULL if not specified.
	 *
	 * This defaults to current working directory.
	 *
	 * @var string|null
	 */
	public $cwd;

	/**
	 * Environment variables set for this process.
	 *
	 * @var array
	 */
	public $env;

	/**
	 * Exit code of the process.
	 *
	 * @var int
	 */
	public $return_code;

	/**
	 * The run time of the process.
	 *
	 * @var float
	 */
	public $run_time;

	/**
	 * @param array $props Properties of executed command.
	 */
	public function __construct( $props ) {
		foreach ( $props as $key => $value ) {
			$this->$key = $value;
		}
	}

	/**
	 * Return properties of executed command as a string.
	 *
	 * @return string
	 */
	public function __toString() {
		$out  = "$ $this->command\n";
		$out .= "$this->stdout\n$this->stderr";
		$out .= "cwd: $this->cwd\n";
		$out .= "run time: $this->run_time\n";
		$out .= "exit status: $this->return_code";

		return $out;
	}
}
