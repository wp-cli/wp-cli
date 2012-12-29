<?php

require_once getcwd() . '/php/utils.php';

require_once __DIR__ . '/class-wp-cli-spec.php';

class WP_CLI_Command_Runner {

	private $install_dir;

	public function __construct() {
		$this->install_dir = sys_get_temp_dir() . '/' . uniqid( "wp-cli-test-", TRUE );
		mkdir( $this->install_dir );
	}

	public function run( $command, $cwd = false ) {
		if ( !$cwd )
			$cwd = $this->install_dir;

		$wp_cli_path = getcwd() . "/bin/wp";

		$sh_command = "cd $cwd; $wp_cli_path $command 2>&1;";

		ob_start();
		system( $sh_command, $return_code );
		$output = ob_get_clean();

		return (object) compact( 'return_code', 'output' );
	}

	public function create_config( $db_settings ) {
		return $this->run( 'core config' . \WP_CLI\Utils\compose_assoc_args( $db_settings ) );
	}

	public function run_install() {
		$cmd = 'core install' . \WP_CLI\Utils\compose_assoc_args( array(
			'url' => 'http://example.com',
			'title' => 'WP CLI Tests',
			'admin_email' => 'admin@example.com',
			'admin_password' => 'password1'
		) );

		return $this->run( $cmd );
	}

	public function download_wordpress_files() {
		// We cache the results of "wp core download" to improve test performance
		// Ideally, we'd cache at the HTTP layer for more reliable tests
		$cache_dir = sys_get_temp_dir() . '/wp-cli-test-core-download-cache';
		if ( !file_exists( $cache_dir ) ) {
			mkdir( $cache_dir );
			$this->run( "core download", $cache_dir );
		}

		exec( "cp -r '$cache_dir/'* '$this->install_dir/'" );
	}
}
