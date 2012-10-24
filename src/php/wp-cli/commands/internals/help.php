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

		$command = \WP_CLI\Dispatcher\traverse( $args );

		if ( !$command ) {
			\WP_CLI::error( sprintf( "'%s' is not a registered wp command.", $args[0] ) );
		}

		$command->show_usage();
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
}

