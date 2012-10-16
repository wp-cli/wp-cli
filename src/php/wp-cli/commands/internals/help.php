<?php

WP_CLI::add_command( 'help', new Help_Command );

/**
 * Implement help command
 *
 * @package wp-cli
 * @subpackage commands/internals
 */
class Help_Command extends WP_CLI_Command {

	function __invoke( $args ) {
		self::maybe_load_man_page( $args );

		self::show_available_subcommands( $args[0] );
	}

	private static function maybe_load_man_page( $args ) {
		$man_dir = WP_CLI_ROOT . "../../../man/";

		if ( !is_dir( $man_dir ) ) {
			WP_CLI::warning( "man pages do not seem to be installed." );
		} else {
			$man_file = $man_dir . implode( '-', $args ) . '.1';

			if ( is_readable( $man_file ) ) {
				exit( WP_CLI::launch( "man $man_file" ) );
			}
		}
	}

	private static function show_available_subcommands( $command ) {
		$command = WP_CLI::load_command( $command );
		$command->show_usage();
	}
}

