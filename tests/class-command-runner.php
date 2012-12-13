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
        return getcwd() . "/src/bin/wp";
    }
    
    private function run_command( $command ) {
        $output = array();
        $return_code = 0;
        $cwd = $this->cwd;
        exec( "sh -c 'cd $cwd; $command'", $output, $return_code );
        return new Execution_Result( $return_code, implode( "\n", $output ) );
    }
}

class Execution_Result {
    public function __construct( $return_code, $output ) {
        $this->return_code = $return_code;
        $this->output = $output;
    }
}
