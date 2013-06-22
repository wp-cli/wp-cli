<?php

use \WP_CLI\Utils;
use \WP_CLI\Dispatcher;

class Help_Command extends WP_CLI_Command {

	/**
	 * Get help on a certain topic.
	 *
	 * @synopsis [<command>]
	 */
	function __invoke( $args, $assoc_args ) {
		$command = self::find_subcommand( $args );

		if ( $command ) {
			self::add_initial_markdown( $command );

			$extra_markdown_path = self::find_extra_markdown( $command );
			if ( $extra_markdown_path ) {
				echo file_get_contents( $extra_markdown_path );
			}

			exit;
		}

		// WordPress is already loaded, so there's no chance we'll find the command
		if ( function_exists( 'add_filter' ) ) {
			\WP_CLI::error( sprintf( "'%s' is not a registered wp command.", $args[0] ) );
		}
	}

	private static function find_subcommand( $args ) {
		$command = \WP_CLI::$root;

		while ( !empty( $args ) && $command && $command->has_subcommands() ) {
			$command = $command->find_subcommand( $args );
		}

		return $command;
	}

	private static function find_extra_markdown( $command ) {
		$cmd_path = Dispatcher\get_path( $command );
		array_shift( $cmd_path ); // discard 'wp'
		$cmd_path = implode( '-', $cmd_path );

		foreach ( WP_CLI::get_man_dirs() as $src_dir ) {
			$src_path = "$src_dir/$cmd_path.txt";
			if ( is_readable( $src_path ) )
				return $src_path;
		}

		return false;
	}

	private static function add_initial_markdown( $command ) {
		$name = implode( ' ', Dispatcher\get_path( $command ) );

		$binding = array(
			'name' => $name,
			'shortdesc' => $command->get_shortdesc(),
		);

		$binding['synopsis'] = "$name " . $command->get_synopsis();

		if ( $command->has_subcommands() ) {
			foreach ( $command->get_subcommands() as $subcommand ) {
				$binding['has-subcommands']['subcommands'][] = array(
					'name' => $subcommand->get_name(),
					'desc' => $subcommand->get_shortdesc(),
				);
			}
		}

		echo Utils\mustache_render( 'man.mustache', $binding );
	}
}

WP_CLI::add_command( 'help', 'Help_Command' );

