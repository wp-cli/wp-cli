<?php

namespace WP_CLI;

/**
 * Results of an executed command.
 */
class ProcessRun {
	/**
	 * @var string The full command executed by the system.
	 */
	public $command;

	/**
	 * @var string Captured output from the process' STDOUT.
	 */
	public $stdout;

	/**
	 * @var string Captured output from the process' STDERR.
	 */
	public $stderr;

	/**
	 * @var string|null The path of the working directory for the process or NULL if not specified (defaults to current working directory).
	 */
	public $cwd;

	/**
	 * @var array Environment variables set for this process.
	 */
	public $env;

	/**
	 * @var int Exit code of the process.
	 */
	public $return_code;

	/**
	 * @var array $props Properties of executed command.
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
		$out .= "exit status: $this->return_code";

		return $out;
	}

}
