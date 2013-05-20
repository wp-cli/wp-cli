<?php

class Help_Command extends WP_CLI_Command {

	/**
	 * Get help on a certain topic.
	 *
	 * @synopsis [<command>] [--gen]
	 */
	function __invoke( $args, $assoc_args ) {
		if ( isset( $assoc_args['gen'] ) )
			$this->generate( $args );
		else
			$this->show( $args );
	}

	private function show( $args ) {
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

	private function generate( $args ) {
		if ( '' === exec( 'which ronn' ) ) {
			WP_CLI::error( '`ronn` executable not found.' );
		}

		$arg_copy = $args;

		$command = WP_CLI\Utils\find_subcommand( $args );

		if ( $command ) {
			foreach ( WP_CLI::get_man_dirs() as $dest_dir => $src_dir ) {
				WP_CLI\Man\generate( $src_dir, $dest_dir, $command );
			}
			exit;
		}

		// WordPress is already loaded, so there's no chance we'll find the command
		if ( function_exists( 'add_filter' ) ) {
			WP_CLI::error( sprintf( "'%s' command not found.", implode( ' ', $arg_copy ) ) );
		}
	}
}

WP_CLI::add_command( 'help', 'Help_Command' );

