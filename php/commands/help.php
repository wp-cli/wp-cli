<?php

use \WP_CLI\Utils;
use \WP_CLI\Dispatcher;

class Help_Command extends WP_CLI_Command {

	/**
	 * Get help on WP-CLI, or on a specific command.
	 *
	 * ## OPTIONS
	 *
	 * [<command>...]
	 * : Get help on a specific command.
	 *
	 * ## EXAMPLES
	 *
	 *     # get help for `core` command
	 *     wp help core
	 *
	 *     # get help for `core download` subcommand
	 *     wp help core download
	 */
	function __invoke( $args, $assoc_args ) {
		$command = self::find_subcommand( $args );

		if ( $command ) {

			if ( WP_CLI::get_runner()->is_command_disabled( $command ) ) {
				$path = implode( ' ', array_slice( \WP_CLI\Dispatcher\get_path( $command ), 1 ) );
				WP_CLI::error( sprintf(
					"The '%s' command has been disabled from the config file.",
					$path
				) );
			}

			self::show_help( $command );
			exit;
		}

		// WordPress is already loaded, so there's no chance we'll find the command
		if ( function_exists( 'add_filter' ) ) {
			$command_string = implode( ' ', $args );
			\WP_CLI::error( sprintf( "'%s' is not a registered wp command.", $command_string ) );
		}
	}

	private static function find_subcommand( $args ) {
		$command = \WP_CLI::get_root_command();

		while ( !empty( $args ) && $command && $command->can_have_subcommands() ) {
			$command = $command->find_subcommand( $args );
		}

		return $command;
	}

	private static function show_help( $command ) {
		$out = self::get_initial_markdown( $command );

		$longdesc = $command->get_longdesc();
		if ( $longdesc ) {
			$out .= wordwrap( $longdesc, 90 ) . "\n";
		}

		// definition lists
		$out = preg_replace_callback( '/([^\n]+)\n: (.+?)(\n\n|$)/s', array( __CLASS__, 'rewrap_param_desc' ), $out );

		// Ensure all non-section headers are indented
		$out = preg_replace( '#^([^\s^\#])#m', "\t$1", $out );

		// section headers
		$out = preg_replace( '/^## ([A-Z ]+)/m', WP_CLI::colorize( '%9\1%n' ), $out );

		$out = str_replace( "\t", '  ', $out );

		self::pass_through_pager( $out );
	}

	private static function rewrap_param_desc( $matches ) {
		$param = $matches[1];
		$desc = self::indent( "\t\t", wordwrap( $matches[2] ) );
		return "\t$param\n$desc\n\n";
	}

	private static function indent( $whitespace, $text ) {
		$lines = explode( "\n", $text );
		foreach ( $lines as &$line ) {
			$line = $whitespace . $line;
		}
		return implode( $lines, "\n" );
	}

	private static function pass_through_pager( $out ) {

		if ( false === ( $pager = getenv( 'PAGER' ) ) ) {
			$pager = Utils\is_windows() ? 'more' : 'less -r';
		}

		// convert string to file handle
		$fd = fopen( "php://temp", "r+" );
		fputs( $fd, $out );
		rewind( $fd );

		$descriptorspec = array(
			0 => $fd,
			1 => STDOUT,
			2 => STDERR
		);

		return proc_close( proc_open( $pager, $descriptorspec, $pipes ) );
	}

	private static function get_initial_markdown( $command ) {
		$name = implode( ' ', Dispatcher\get_path( $command ) );

		$binding = array(
			'name' => $name,
			'shortdesc' => $command->get_shortdesc(),
		);

		$binding['synopsis'] = wordwrap( "$name " . $command->get_synopsis(), 79 );

		$alias = $command->get_alias();
		if ( $alias ) {
			$binding['alias'] = $alias;
		}

		if ( $command->can_have_subcommands() ) {
			$binding['has-subcommands']['subcommands'] = self::render_subcommands( $command );
		}

		return Utils\mustache_render( 'man.mustache', $binding );
	}

	private static function render_subcommands( $command ) {
		$subcommands = array();
		foreach ( $command->get_subcommands() as $subcommand ) {

			if ( WP_CLI::get_runner()->is_command_disabled( $subcommand ) ) {
				continue;
			}

			$subcommands[ $subcommand->get_name() ] = $subcommand->get_shortdesc();
		}

		$max_len = self::get_max_len( array_keys( $subcommands ) );

		$lines = array();
		foreach ( $subcommands as $name => $desc ) {
			$lines[] = str_pad( $name, $max_len ) . "\t\t\t" . $desc;
		}

		return $lines;
	}

	private static function get_max_len( $strings ) {
		$max_len = 0;
		foreach ( $strings as $str ) {
			$len = strlen( $str );
			if ( $len > $max_len )
				$max_len = $len;
		}

		return $max_len;
	}

}

WP_CLI::add_command( 'help', 'Help_Command' );

