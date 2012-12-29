<?php

require_once getcwd() . '/php/utils.php';

require_once __DIR__ . '/class-wp-cli-spec.php';

function run_wp_cli( $command, $cwd ) {
	$wp_cli_path = getcwd() . "/bin/wp";

	$sh_command = "cd $cwd; $wp_cli_path $command 2>&1;";

	ob_start();
	system( $sh_command, $return_code );
	$output = ob_get_clean();

	return (object) compact( 'return_code', 'output' );
}

class Wordpress_Installer {

	private $install_dir;

	public function __construct( $install_dir ) {
		$this->install_dir = $install_dir;
	}

	public function create_config( $db_settings ) {
		$cmd = 'core config' . \WP_CLI\Utils\compose_assoc_args( $db_settings );

		run_wp_cli( $cmd, $this->install_dir );
	}

	public function run_install() {
		$cmd = 'core install' . \WP_CLI\Utils\compose_assoc_args( array(
			'url' => 'http://example.com',
			'title' => 'WP CLI Tests',
			'admin_email' => 'admin@example.com',
			'admin_password' => 'password1'
		) );

		$install_result = run_wp_cli( $cmd, $this->install_dir );

		$this->assert_process_exited_successfully( $install_result );
	}

	public function download_wordpress_files() {
		// We cache the results of "wp core download" to improve test performance
		// Ideally, we'd cache at the HTTP layer for more reliable tests
		$cache_dir = sys_get_temp_dir() . '/wp-cli-test-core-download-cache';
		if ( !file_exists( $cache_dir ) ) {
			mkdir( $cache_dir );
			run_wp_cli( "core download", $cache_dir );
		}

		exec( "cp -r '$cache_dir/'* '$this->install_dir/'" );
	}

	private function assert_process_exited_successfully( $result ) {
		if ( $result->return_code !== 0 ) {
			$message = "return code was $result->return_code, output was: $result->output";
			throw new Exception( $message );
		}
	}
}
