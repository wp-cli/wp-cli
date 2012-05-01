<?php

WP_CLI::add_command('home', 'Home_Command');

/**
 * Implement home command
 *
 * @package wp-cli
 * @subpackage commands/internals
 */
class Home_Command extends WP_CLI_Command {

	/**
	 * Overwrite the constructor to have a command without sub-commands.
	 *
	 * @param array $args
	 * @param array $assoc_args
	 */
	public function __construct( $args, $assoc_args ) {
		// The url for the wp-cli repository
		$repository_url = 'http://github.com/wp-cli/wp-cli';

		// Open the wp-cli page in the browser
		if(exec('which x-www-browser')) {
			system('x-www-browser '.$repository_url);
		}
		elseif(exec('which open')) {
			system('open '.$repository_url);
		}
		else {
			WP_CLI::error('No command found to open the homepage in the browser. Please open it manually: '.$repository_url);
			return;
		}

		WP_CLI::success('The wp-cli homepage should be opening in your browser.');
	}
}
