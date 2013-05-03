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

		$proc = proc_open( $this->command, $descriptors, $pipes, $cwd );

		$STDOUT = stream_get_contents( $pipes[1] );
		fclose( $pipes[1] );

		$STDERR = stream_get_contents( $pipes[2] );
		fclose( $pipes[2] );

		return (object) array(
			'STDOUT' => $STDOUT,
			'STDERR' => $STDERR,
			'return_code' => proc_close( $proc ),
			'command' => $this->command,
			'cwd' => $cwd
		);
	}

	public function run_check( $subdir = '' ) {
		$r = $this->run( $subdir );

		if ( $r->return_code ) {
			throw new \RuntimeException( sprintf( "%s: %s\ncwd: %s",
				$r->command, $r->STDERR, $r->cwd ) );
		}

		return $r;
	}
}

