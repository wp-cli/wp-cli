<?php

use \WP_CLI\Dispatcher\CommandContainer;

class Help_Command extends WP_CLI_Command {

	/**
	 * Get help on a certain topic.
	 *
	 * @synopsis [<command>]
	 */
	function __invoke( $args ) {
		self::maybe_load_man_page( $args );

		$arg_copy = $args;

		$command = \WP_CLI::$root;

		while ( !empty( $args ) && $command && $command instanceof CommandContainer ) {
			$command = $command->find_subcommand( $args );
		}

		if ( !$command ) {
			\WP_CLI::error( sprintf( "'%s' is not a registered wp command.", $arg_copy[0] ) );
		}

		$command->show_usage();
	}

	private static function maybe_load_man_page( $args ) {
		$man_file = \WP_CLI\Man\get_file_name( $args );

		foreach ( \WP_CLI::get_man_dirs() as $dest_dir => $_ ) {
			$man_path = $dest_dir . $man_file;

			if ( is_readable( $man_path ) ) {
				self::show_manpage( $man_path );
			}
		}
	}

	private static function show_manpage( $path ) {
		// man can't read phar://, so need to copy to a temporary file
		$tmp_path = tempnam( sys_get_temp_dir(), 'wp-cli-man-' );

		copy( $path, $tmp_path );

		WP_CLI::launch( "man $tmp_path" );

		unlink( $tmp_path );
	}
}

WP_CLI::add_command( 'help', new Help_Command );

