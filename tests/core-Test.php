<?php

require_once __DIR__ . '/class-command-runner.php';
require_once __DIR__ . '/class-wp-cli-test-case.php';

class CoreTest extends Wp_Cli_Test_Case {
	public function test_is_installed_exits_with_1_if_wordpress_not_installed() {
		$temp_dir = $this->create_temporary_directory();
		$command_runner = new Command_Runner( $temp_dir );
		$result = $command_runner->run_wp_cli( "core is-installed" );
		$this->assertEquals(1, $result->return_code);
	}

	public function test_is_installed_exits_with_0_after_running_install_command() {
		$command_runner = $this->install_wp_cli();
		$result = $command_runner->run_wp_cli( "core is-installed" );
		$this->assertEquals(0, $result->return_code);
	}

	public function test_install_command_creates_default_blog_post() {
		$command_runner = $this->install_wp_cli();
		$result = $command_runner->run_wp_cli( "post list --ids" );
		$this->assertEquals( "1", $result->output );
	}

	public function test_message_explains_that_config_must_be_present_before_install() {
		$temp_dir = $this->create_temporary_directory();
		$command_runner = new Command_Runner( $temp_dir );
		$installer = new Wordpress_Installer( $temp_dir, $command_runner );
		$installer->download_wordpress_files( $temp_dir );
		$result = $command_runner->run_wp_cli( "core install" );
		$this->assertEquals(
			"^[[31;1mError: ^[[0mwp-config.php not found\n" .
			"Either create one manually or use wp core config\n",
			$result->output
		);
	}

	public function test_wp_config_can_be_placed_in_parent_directory() {
		$temp_dir = $this->create_temporary_directory();
		$install_dir = $temp_dir . '/www-root';
		mkdir( $install_dir );
		$command_runner = new Command_Runner( $install_dir );
		$installer = new Wordpress_Installer( $install_dir, $command_runner );
		$installer->download_wordpress_files( $install_dir );
		$installer->create_config( $this->database_settings );
		rename( $install_dir . '/wp-config.php', $temp_dir . '/wp-config.php' );
		$installer->run_install();
		$result = $command_runner->run_wp_cli( "post list --ids" );
		$this->assertEquals( "1", $result->output );
	}
}
