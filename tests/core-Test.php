<?php
class CoreTest extends PHPUnit_Framework_TestCase {
    
    
    public function testIsInstalledExitsWith1IfWordPressNotInstalled() {
        $temp_dir = self::create_temporary_directory();
        $command_runner = new CommandRunner( $temp_dir );
        $result = $command_runner->run_wp_cli("core is-installed");
        $this->assertEquals(1, $result->return_code);
    }
    
    public function testIsInstalledExitsWith0AfterRunningInstallCommand() {
        $command_runner = $this->install_wp_cli();
        $result = $command_runner->run_wp_cli("core is-installed");
        $this->assertEquals(0, $result->return_code);
    }
    
    public function testInstallCommandCreatesDefaultBlogPost() {
        $command_runner = $this->install_wp_cli();
        $result = $command_runner->run_wp_cli( "post list --ids" );
        $this->assertEquals( "1", $result->output );
    }
    
    private function install_wp_cli() {
        $temp_dir = self::create_temporary_directory();
        $command_runner = new CommandRunner( $temp_dir );
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
            mkdir($cache_dir);
            $command_runner = new CommandRunner( $cache_dir );
            $command_runner->run_wp_cli( "core download" );
        }
        exec( "cp -r '$cache_dir/'* '$target_dir/'" );
    }
    
    private static function create_temporary_directory() {
        $dir = sys_get_temp_dir() . '/' . uniqid("wp-cli-test-", TRUE);
        mkdir($dir);
        return $dir;
    }
}

class CommandRunner {
    private $cwd;
    
    public function __construct( $cwd ) {
        $this->cwd = $cwd;
    }
    
    public function run_wp_cli( $wp_cli_command ) {
        $wp_cli_path = self::find_wp_cli();
        return self::run_command( "$wp_cli_path $wp_cli_command" );
    }
    
    private function find_wp_cli() {
        return getcwd() . "/src/bin/wp";
    }
    
    private function run_command( $command ) {
        $output = array();
        $return_code = 0;
        $cwd = $this->cwd;
        exec( "sh -c 'cd $cwd; $command'", $output, $return_code );
        return new ExecutionResult( $return_code, implode( "\n", $output ) );
    }
}

class ExecutionResult {
    public function __construct( $return_code, $output ) {
        $this->return_code = $return_code;
        $this->output = $output;
    }
}
