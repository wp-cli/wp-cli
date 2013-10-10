<?php

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

		// Ensure we're using the expected `wp` binary
		$bin_dir = getenv( 'WP_CLI_BIN_DIR' ) ?: realpath( __DIR__ . "/../../bin" );
		$env = array_merge( $this->env, array(
			'PATH' =>  $bin_dir . ':' . getenv( 'PATH' ),
			'BEHAT_RUN' => 1
		) );

		$proc = proc_open( $this->command, $descriptors, $pipes, $cwd, $env );

		$STDOUT = stream_get_contents( $pipes[1] );
		fclose( $pipes[1] );

		$STDERR = stream_get_contents( $pipes[2] );
		fclose( $pipes[2] );

		return new ProcessRun( array(
			'STDOUT' => $STDOUT,
			'STDERR' => $STDERR,
			'return_code' => proc_close( $proc ),
			'command' => $this->command,
			'cwd' => $cwd,
			'env' => $env
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

