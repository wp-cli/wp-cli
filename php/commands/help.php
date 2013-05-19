<?php

use \WP_CLI\Dispatcher\CommandContainer;

class Help_Command extends WP_CLI_Command {

	/**
	 * Get help on a certain topic.
	 *
	 * @synopsis [<command>]
	 */
	function __invoke( $args ) {
		if ( \WP_CLI\Man\maybe_show_manpage( $args ) ) {
			exit;
		}

		$command = WP_CLI\Utils\find_subcommand( $args );

		if ( $command ) {
			$command->show_usage();
			exit;
		}

		// WordPress is already loaded, so there's no chance we'll find the command
		if ( function_exists( 'add_filter' ) ) {
			\WP_CLI::error( sprintf( "'%s' is not a registered wp command.", $args[0] ) );
		}
	}
}

WP_CLI::add_command( 'help', 'Help_Command' );

