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
        $output_dummy = array();
        $output_file = tempnam( sys_get_temp_dir(), "wp-cli-test" );
        $output_binary_file = tempnam( sys_get_temp_dir(), "wp-cli-test" );
        $return_code = 0;
        $cwd = $this->cwd;
        $sh_command = "cd $cwd;" .
            "$command > $output_binary_file 2>&1;" .
            'RETURN_CODE=$?;' .
            "cat -v $output_binary_file > $output_file;" .
            'exit $RETURN_CODE';
        exec("sh -c '$sh_command'", $output_dummy, $return_code );
        $output = file_get_contents( $output_file);
        unlink( $output_file );
        unlink( $output_binary_file );
        return new Execution_Result( $return_code, $output );
    }
}

class Execution_Result {
    public function __construct( $return_code, $output ) {
        $this->return_code = $return_code;
        $this->output = $output;
    }
}
