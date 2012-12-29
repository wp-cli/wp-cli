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

		return new Execution_Result( $return_code, $output );
	}
}

class Execution_Result {

	public function __construct( $return_code, $output ) {
		$this->return_code = $return_code;
		$this->output = $output;
	}
}
