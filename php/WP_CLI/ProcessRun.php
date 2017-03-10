<?php

namespace WP_CLI;

/**
 * Results of an executed command.
 */
class ProcessRun {

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
