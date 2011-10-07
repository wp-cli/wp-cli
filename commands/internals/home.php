<?php

// Add the command to the wp-cli
WP_CLI::addCommand('home', 'HomeCommand');

/**
 * Implement home command
 *
 * @package wp-cli
 * @subpackage commands/internals
 * @author Andreas Creten
 */
class HomeCommand extends WP_CLI_Command {

	/**
	 * Overwrite the constructor to have a command without sub-commands.
     *
     * @param array $args
	 * @param array $assoc_args
	 */
	public function __construct( $args, $assoc_args ) {
		if(empty($args)) {
			// The url for the wp-cli repository
			$repository_url = 'http://github.com/andreascreten/wp-cli';

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
		else {
			// Call the parent constructor
			parent::__construct( $args, $assoc_args );
		}
	}

	/**
	 * Help function for this command
	 */
	public static function help() {
		WP_CLI::line( <<<EOB
usage: wp home

Opens the wp-cli homepage in your browser.
EOB
		);
	}
}
