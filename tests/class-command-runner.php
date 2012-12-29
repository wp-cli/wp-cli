<?php

class Command_Runner {
	private $cwd;

	public function __construct( $cwd ) {
		$this->cwd = $cwd;
	}

	public function run_wp_cli( $wp_cli_command ) {
		$wp_cli_path = self::find_wp_cli();
		return self::run_command( "$wp_cli_path $wp_cli_command" );
	}

	private function find_wp_cli() {
		return getcwd() . "/bin/wp";
	}

	private function run_command( $command ) {
		$cwd = $this->cwd;
		$sh_command = "cd $cwd; $command 2>&1;";

		ob_start();
		system( $sh_command, $return_code );
		$output = ob_get_clean();

		return (object) compact( 'return_code', 'output' );
	}
}

