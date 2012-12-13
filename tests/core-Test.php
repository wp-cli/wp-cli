<?php
class CoreTest extends PHPUnit_Framework_TestCase {
    public function testIsInstalledExitsWith1IfWordPressNotInstalled() {
        $temp_dir = self::create_temporary_directory();
        $result = self::run_wp_cli("core is-installed --path=$temp_dir");
        $this->assertEquals(1, $result->return_code);
    }
    
    private static function create_temporary_directory() {
        return sys_get_temp_dir() . '/' . uniqid("wp-cli-test-", TRUE);
    }
    
    private static function run_wp_cli( $wp_cli_command ) {
        $wp_cli_path = self::find_wp_cli();
        return self::run_command("$wp_cli_path $wp_cli_command");
    }
    
    private static function find_wp_cli() {
        return "src/bin/wp";
    }
    
    private static function run_command( $command ) {
        $output = array();
        $return_code = 0;
        exec( $command, $output, $return_code );
        return new ExecutionResult( $return_code );
    }
}

class ExecutionResult {
    public function __construct( $return_code ) {
        $this->return_code = $return_code;
    }
}
