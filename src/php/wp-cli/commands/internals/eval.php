<?php

WP_CLI::add_command('eval', 'Eval_Command');

/**
 * Implement eval command
 *
 * @package wp-cli
 * @subpackage commands/internals
 */
class Eval_Command extends WP_CLI_Command {

	/**
	 * Overwrite the constructor to have a command without sub-commands.
	 *
	 * @param array $args
	 * @param array $assoc_args
	 */
	public function __construct( $args, $assoc_args ) {
		if ( empty( $args ) ) {
			WP_CLI::line( "usage: wp eval <php-code>" );
			exit;
		}

		eval( $args[0] );
	}
}
