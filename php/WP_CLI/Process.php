<?php

namespace WP_CLI;

class Process {

	public static function create( $command, $cwd = null, $env = array() ) {
		$proc = new self;

		$proc->command = $command;
		$proc->cwd = $cwd;
		$proc->env = $env;

		return $proc;
	}

	private $command, $cwd, $env;

	private function __construct() {}

	public function run( $subdir = '' ) {
		$cwd = $this->cwd;
		if ( $subdir ) {
			$cwd .= '/' . $subdir;
		}

		$descriptors = array(
			0 => STDIN,
			1 => array( 'pipe', 'w' ),
			2 => array( 'pipe', 'w' ),
		);

		$proc = proc_open( $this->command, $descriptors, $pipes, $cwd, $this->env );

		$stdout = stream_get_contents( $pipes[1] );
		fclose( $pipes[1] );

		$stderr = stream_get_contents( $pipes[2] );
		fclose( $pipes[2] );

		return new ProcessRun( array(
			'stdout' => $stdout,
			'stderr' => $stderr,
			'return_code' => proc_close( $proc ),
			'command' => $this->command,
			'cwd' => $cwd,
			'env' => $this->env
		) );
	}

	public function run_check( $subdir = '' ) {
		$r = $this->run( $subdir );

		if ( $r->return_code || !empty( $r->STDERR ) ) {
			throw new \RuntimeException( $r );
		}

		return $r;
	}
}


class ProcessRun {

	public function __construct( $props ) {
		foreach ( $props as $key => $value ) {
			$this->$key = $value;
		}
	}

	public function __toString() {
		$out  = "$ $this->command\n";
		$out .= "$this->STDOUT\n$this->STDERR";
		$out .= "cwd: $this->cwd\n";
		$out .= "exit status: $this->return_code";

		return $out;
	}
}

