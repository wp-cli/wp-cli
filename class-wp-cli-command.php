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
	 * @author Andreas Creten
	 */
	function __construct($args = array()) {
		// The first command is the sub command
		$sub_command = array_shift($args);
		
		// If the method exists, try to load it
		if(method_exists($this, $sub_command)) {
			$this->$sub_command($args);
		}
		// Otherwise, show the help for this command
		else {
			$this->help($args);
		}
	}
	
	/**
	 * General help function for this command
	 *
	 * @param Array $args 
	 * @return void
	 * @author Andreas Creten
	 */
	public function help($args = array()) {
		// Get the cli arguments
		$arguments = $GLOBALS['argv'];
		
		// Remove the first entry
		array_shift($arguments);

		// Get the command
		$used_command = array_shift($arguments);
		
		// Show the list of sub-commands for this command
		WP_CLI::line('Example usage:');
		WP_CLI::out('    wp '.$used_command);
		$methods = WP_CLI_Command::getMethods($this);
		if(!empty($methods)) {
			WP_CLI::out(' ['.implode('|', $methods).']');
		}
		WP_CLI::line(' ...');
		WP_CLI::line();
		
		// Send a warning to the user because there is no custom help function defined in the command
		// Make usure there always is a help method in your command class
		WP_CLI::warning('The command has no dedicated help function, ask the creator to fix it.');
	}
	
	/**
	 * Get the filtered list of methods for a class
	 *
	 * @param string $class
	 * @return Array The list of methods
	 * @author Andreas Creten
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
		
		// Only return the values, to fill up the gaps
		return array_values($methods);
	}
}