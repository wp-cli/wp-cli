<?php

require __DIR__ . '/class-command-runner.php';

class CoreTest extends PHPUnit_Framework_TestCase {
    public function test_is_installed_exits_with_1_if_wordpress_not_installed() {
        $temp_dir = self::create_temporary_directory();
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
    
    private function install_wp_cli() {
        $temp_dir = self::create_temporary_directory();
        $command_runner = new Command_Runner( $temp_dir );
        self::download_wordpress_files( $temp_dir );
        $dbname = "wp_cli_test";
        $dbuser = "wp_cli_test";
        $dbpass = "password1";
        exec( "mysql -u$dbname -p$dbpass -e 'DROP DATABASE $dbname'" );
        exec( "mysql -u$dbname -p$dbpass -e 'CREATE DATABASE $dbname'" );
        $command_runner->run_wp_cli( "core config --dbname=$dbname --dbuser=$dbuser --dbpass=$dbpass" );
        $command_runner->run_wp_cli(
            "core install --url=http://example.com/ --title=WordPress " .
            " --admin_email=admin@example.com --admin_password=password1"
        );
        return $command_runner;
    }
    
    private static function download_wordpress_files( $target_dir ) {
        // We cache the results of "wp core download" to improve test performance
        // Ideally, we'd cache at the HTTP layer for more reliable tests
        $cache_dir = sys_get_temp_dir() . '/wp-cli-test-core-download-cache';
        if ( !file_exists( $cache_dir ) ) {
            mkdir( $cache_dir );
            $command_runner = new CommandRunner( $cache_dir );
            $command_runner->run_wp_cli( "core download" );
        }
        exec( "cp -r '$cache_dir/'* '$target_dir/'" );
    }
    
    private static function create_temporary_directory() {
        $dir = sys_get_temp_dir() . '/' . uniqid( "wp-cli-test-", TRUE );
        mkdir( $dir );
        return $dir;
    }
}
