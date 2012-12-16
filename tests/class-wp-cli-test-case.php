<?php

require_once __DIR__ . '/class-command-runner.php';

abstract class Wp_Cli_Test_Case extends PHPUnit_Framework_TestCase {
	protected $database_settings = array(
		"dbname" => "wp_cli_test",
		"dbuser" => "wp_cli_test",
		"dbpass" => "password1"
	);

	protected function setUp() {
		$this->reset_database();
	}

	private function reset_database() {
		$dbname = $this->database_settings["dbname"];
		$this->run_sql( "DROP DATABASE $dbname" );
		$this->run_sql( "CREATE DATABASE $dbname" );
	}

	private function run_sql( $sql ) {
		$dbuser = $this->database_settings["dbuser"];
		$dbpass = $this->database_settings["dbpass"];
		exec( "mysql -u$dbuser -p$dbpass -e '$sql'" );
	}

	public function full_wp_install() {
		$temp_dir = $this->create_temporary_directory();
		$runner = new Command_Runner( $temp_dir );
		$installer = new Wordpress_Installer( $temp_dir, $runner );
		$installer->download_wordpress_files( $temp_dir );
		$installer->create_config( $this->database_settings );
		$installer->run_install();
		return $runner;
	}

	public function create_temporary_directory() {
		$dir = sys_get_temp_dir() . '/' . uniqid( "wp-cli-test-", TRUE );
		mkdir( $dir );
		return $dir;
	}
}

class Wordpress_Installer {
	private $install_dir;
	private $runner;

	public function __construct( $install_dir, $runner ) {
		$this->install_dir = $install_dir;
		$this->runner = $runner;
	}

	public function create_config( $database_settings ) {
		$dbname = $database_settings["dbname"];
		$dbuser = $database_settings["dbuser"];
		$dbpass = $database_settings["dbpass"];
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

	private function assert_process_exited_successfully( $result ) {
		if ( $result->return_code !== 0 ) {
			$message = "return code was $result->return_code, output was: $result->output";
			throw new Exception( $message );
		}
	}
}
