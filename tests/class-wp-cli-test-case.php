<?php

require_once __DIR__ . '/class-command-runner.php';

abstract class Wp_Cli_Test_Case extends PHPUnit_Framework_TestCase {
    public function install_wp_cli() {
        $temp_dir = $this->create_temporary_directory();
        $command_runner = new Command_Runner( $temp_dir );
        $installer = new Wordpress_Installer( $temp_dir, $command_runner );
        $installer->download_wordpress_files( $temp_dir );
        $installer->reset_database();
        $installer->create_config();
        $installer->run_install();
        return $command_runner;
    }
    
    public function create_temporary_directory() {
        $dir = sys_get_temp_dir() . '/' . uniqid( "wp-cli-test-", TRUE );
        mkdir( $dir );
        return $dir;
    }
}

class Wordpress_Installer {
    private $dbname = "wp_cli_test";
    private $dbuser = "wp_cli_test";
    private $dbpass = "password1";
    private $install_dir;
    private $command_runner;
    
    public function __construct( $install_dir, $command_runner ) {
        $this->install_dir = $install_dir;
        $this->command_runner = $command_runner;
    }
    
    public function reset_database() {
        exec( "mysql -u$this->dbname -p$this->dbpass -e 'DROP DATABASE $this->dbname'" );
        exec( "mysql -u$this->dbname -p$this->dbpass -e 'CREATE DATABASE $this->dbname'" );
    }
    
    public function create_config() {
        $this->command_runner->run_wp_cli(
            "core config --dbname=$this->dbname --dbuser=$this->dbuser --dbpass=$this->dbpass" );
    }
    
    public function run_install() {
        $this->command_runner->run_wp_cli(
            "core install --url=http://example.com/ --title=WordPress " .
            " --admin_email=admin@example.com --admin_password=password1"
        );
    }
    
    public function download_wordpress_files() {
        // We cache the results of "wp core download" to improve test performance
        // Ideally, we'd cache at the HTTP layer for more reliable tests
        $cache_dir = sys_get_temp_dir() . '/wp-cli-test-core-download-cache';
        if ( !file_exists( $cache_dir ) ) {
            mkdir( $cache_dir );
            $command_runner = new Command_Runner( $cache_dir );
            $command_runner->run_wp_cli( "core download" );
        }
        exec( "cp -r '$cache_dir/'* '$this->install_dir/'" );
    }
    
}
