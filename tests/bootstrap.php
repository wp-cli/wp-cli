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

class Wordpress_Installer {

	private $install_dir;
	private $runner;

	public function __construct( $install_dir ) {
		$this->install_dir = $install_dir;
		$this->runner = new Command_Runner( $install_dir );
	}

	public function create_config( $db_settings ) {
		$dbname = $db_settings["dbname"];
		$dbuser = $db_settings["dbuser"];
		$dbpass = $db_settings["dbpass"];
		$this->runner->run_wp_cli(
			"core config --dbname=$dbname --dbuser=$dbuser --dbpass=$dbpass" );
	}

	public function run_install() {
		$install_result = $this->runner->run_wp_cli(
			"core install --url=http://example.com/ --title=WordPress " .
			" --admin_email=admin@example.com --admin_password=password1"
		);
		$this->assert_process_exited_successfully( $install_result );
	}

	public function download_wordpress_files() {
		// We cache the results of "wp core download" to improve test performance
		// Ideally, we'd cache at the HTTP layer for more reliable tests
		$cache_dir = sys_get_temp_dir() . '/wp-cli-test-core-download-cache';
		if ( !file_exists( $cache_dir ) ) {
			mkdir( $cache_dir );
			$runner = new Command_Runner( $cache_dir );
			$runner->run_wp_cli( "core download" );
		}
		exec( "cp -r '$cache_dir/'* '$this->install_dir/'" );
	}

	public function full_install( $db_settings ) {
		$this->download_wordpress_files();
		$this->create_config( $db_settings );
		$this->run_install();
	}

	private function assert_process_exited_successfully( $result ) {
		if ( $result->return_code !== 0 ) {
			$message = "return code was $result->return_code, output was: $result->output";
			throw new Exception( $message );
		}
	}
}
