<?php

class Process {

	public static function create( $command, $cwd = null ) {
		$proc = new self;

		$proc->command = $command;
		$proc->cwd = $cwd;

		return $proc;
	}

	private $command, $cwd;

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

		$proc = proc_open( $this->command, $descriptors, $pipes, $cwd, array(
			'PATH' =>  $bin_dir . ':' . getenv( 'PATH' ),
			'BEHAT_RUN' => 1
		) );

		$STDOUT = stream_get_contents( $pipes[1] );
		fclose( $pipes[1] );

		$STDERR = stream_get_contents( $pipes[2] );
		fclose( $pipes[2] );

		return new ProcessRun( array(
			'STDOUT' => $STDOUT,
			'STDERR' => $STDERR,
			'return_code' => proc_close( $proc ),
			'command' => $this->command,
			'cwd' => $cwd
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
		return sprintf( "%s: %s\n" . "cwd: %s\n" . "exit status: %d",
			$this->command, $this->STDERR, $this->cwd, $this->return_code );
	}
}

