<?php

use \WP_CLI\Dispatcher\CommandContainer;

class Help_Command extends WP_CLI_Command {

	/**
	 * Get help on a certain topic.
	 *
	 * @synopsis [<command>]
	 */
	function __invoke( $args ) {
		\WP_CLI\Man\maybe_show_manpage( $args );

		$command = WP_CLI\Utils\find_subcommand( $args );

		if ( !$command ) {
			\WP_CLI::error( sprintf( "'%s' is not a registered wp command.", $args[0] ) );
		}

		$command->show_usage();
	}
}

WP_CLI::add_command( 'help', 'Help_Command' );

