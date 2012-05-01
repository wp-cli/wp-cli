<?php

WP_CLI::add_command('eval-file', 'Eval_File_Command');

/**
 * Implement eval-file command
 *
 * @package wp-cli
 * @subpackage commands/internals
 */
class Eval_File_Command extends WP_CLI_Command {

	/**
	 * Overwrite the constructor to have a command without sub-commands.
	 *
	 * @param array $args
	 * @param array $assoc_args
	 */
	public function __construct( $args, $assoc_args ) {
		if ( empty( $args ) ) {
			WP_CLI::line( "usage: wp eval-file <path>" );
			exit;
		}

		foreach ( $args as $file ) {
			if ( !file_exists( $file ) ) {
				WP_CLI::error( "'$file' does not exist." );
			} else {
				include( $file );
			}
		}
	}
}
