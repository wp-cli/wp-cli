<?php

/**
 * Base class for WP-CLI commands
 *
 * @package wp-cli
 * @author Andreas Creten
 */
abstract class WP_CLI_Command {

    /**
     * Construct for this class, transfers the cli arguments to the right class
     *
     * @param Array $args
     */
    function __construct( $command, $args, $assoc_args ) {
        // The first command is the sub command
        $sub_command = array_shift($args);

        // If the method exists, try to load it
        if(method_exists($this, $sub_command)) {
            $this->$sub_command($args, $assoc_args);
        }
        // If a dummy method exists, use it. This if for reserved keywords in php (like list, isset)
        elseif(method_exists($this, '_'.$sub_command)) {
            $sub_command = '_'.$sub_command;
            $this->$sub_command($args, $assoc_args);
        }
        // Otherwise, show the help for this command
        else {
            $this->help($command, $sub_command);
        }
    }

    /**
     * General help function for this command
     *
     * @param string $command
     * @param string $sub_command
     * @return void
     */
    public function help( $command, $sub_command = false ) {
        // Show the list of sub-commands for this command
        WP_CLI::line('Example usage:');
        WP_CLI::out('    wp '.$command);

        $methods = WP_CLI_Command::getMethods($this);
        if(!empty($methods)) {
            WP_CLI::out(' ['.implode('|', $methods).']');
        }
        WP_CLI::line(' ...');
        WP_CLI::line();

        WP_CLI::warning('The command has no dedicated help function; ask the creator to fix it.');
    }

    /**
     * Get the filtered list of methods for a class
     *
     * @param string $class
     * @return Array The list of methods
     */
    static function getMethods($class) {
        // Methods that don't need to be included in the method list
        $blacklist = array('__construct', 'getMethods');

        // Get all the methods of the class
        $methods = get_class_methods($class);

        // Remove the blacklisted methods
        foreach($blacklist as $method) {
            $in_array = array_search($method, $methods);
            if($in_array !== false) {
                unset($methods[$in_array]);
            }
        }

        // Fix dummy function names
        foreach($methods as $key => $method) {
            if(strpos($method, '_') === 0) {
                $methods[$key] = substr($method, 1, strlen($method));
            }
        }

        // Only return the values, to fill up the gaps
        return array_values($methods);
    }
}
