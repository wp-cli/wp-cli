<?php

// Add the command to the wp-cli
WP_CLI::addCommand('option', 'OptionCommand');

/**
 * Implement option command
 *
 * @package wp-cli
 * @subpackage commands/internals
 * @author Andreas Creten
 */
class OptionCommand extends WP_CLI_Command {

	/**
	 * Add an option
	 *
	 * @param array $args
	 * @return void
	 **/
	public function add($args = array()) {
		// Check if the required arguments are there
		if(count($args) == 2) {
			// Try to add the option
			if(add_option($args[0], $args[1])) {
				WP_CLI::success('Added option %9'.$args[0].'%n to \'%9'.$args[1].'%n\'.');
			}
			else {
				WP_CLI::error('Option %9'.$args[0].'%n could not be added. Does it already exist?');
			}
		}
		else {
			WP_CLI::error('This command needs exactly two arguments.');
		}
	}

	/**
	 * Update an option
	 *
	 * @param array $args
	 * @return void
	 **/
	public function update($args = array()) {
		// Check if the required arguments are there
		if(count($args) == 2) {
			// Try to update the option
			if(update_option($args[0], $args[1])) {
				WP_CLI::success('Updated option %9'.$args[0].'%n to \'%9'.$args[1].'%n\'.');
			}
			else {
				WP_CLI::error('Option %9'.$args[0].'%n could not be updated. Does it exist? Is the value already \'%9'.$args[1].'%n\'?');
			}
		}
		else {
			WP_CLI::error('This command needs exactly two arguments.');
		}
	}

	/**
	 * Delete an option
	 *
	 * @param array $args
	 * @return void
	 **/
	public function delete($args = array()) {
		// Check if the required arguments are there
		if(count($args) == 1) {
			// Try to delete the option
			if(delete_option($args[0])) {
				WP_CLI::success('Deleted option %9'.$args[0].'%n.');
			}
			else {
				WP_CLI::error('Option %9'.$args[0].'%n could not be deleted. Does it exist?');
			}
		}
		else {
			WP_CLI::error('This command needs exactly one argument.');
		}
	}

	/**
	 * Get an option
	 *
	 * @param array $args
	 * @return void
	 **/
	public function get($args = array()) {
		// Check if the required arguments are there
		if(count($args) == 1) {
			// Try to get the option
			$option = get_option($args[0]);
			if($option) {
				WP_CLI::success('The value of option %9'.$args[0].'%n is \'%9'.$option.'%n\'.');
			}
			else {
				WP_CLI::error('Option %9'.$args[0].'%n could not be found. Does it exist?');
			}
		}
		else {
			WP_CLI::error('This command needs exactly one argument.');
		}
	}

	/**
	 * Help function for this command
	 */
	public static function help() {
		WP_CLI::line( <<<EOB
usage: wp option get <option-name>
   or: wp option add <option-name> <option-value>
   or: wp option update <option-name> <option-value>
   or: wp option delete <option-name>
EOB
		);
	}
}
