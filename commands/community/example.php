<?php

// Add the command to the wp-cli
WP_CLI::addCommand('example', 'ExampleCommand');

/**
 * Implement example command
 *
 * @package wp-cli
 * @subpackage commands/cummunity
 * @author Andreas Creten
 */
class ExampleCommand extends WP_CLI_Command {
	/**
	 * Example method
	 *
	 * @param string $args 
	 * @return void
	 * @author Andreas Creten
	 */
	function example($args) {
		// Print a string
		WP_CLI::line('Prints a string -- ');
		
		// Print a single line
		WP_CLI::line('Prints out a line');
		
		// Run through the commands
		foreach ($args as $arg) {
			WP_CLI::line($arg);
		}
		
		// Print an error message
		WP_CLI::error('Error message');
		// Result: Error: Error message
		
		// Print a warning message
		WP_CLI::warning('Warning message');
		// Result: Warning: Warning message
		
		// Print a success message
		WP_CLI::success('Success message');
		// Result: Success: Success message
	}
	
	/**
	 * Help function for this command
	 *
	 * @param string $args 
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
		
		$methods = WP_CLI_Command::getMethods($this);
		foreach ($methods as $method) {
			if($method != 'help') {
				WP_CLI::line('    wp '.$used_command.' '.$method.' <plugin-name>');
			}
			else {
				WP_CLI::line('    wp '.$used_command.' '.$method);
			}
		}
	}
}