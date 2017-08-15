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

		// Remove subcommands if in columns - will wordwrap separately.
		$subcommands = '';
		$column_subpattern = '[ \t]+[^\t]+\t+';
		if ( preg_match( '/(^## SUBCOMMANDS[^\n]*\n+' . $column_subpattern . '.+?)(?:^##|\z)/ms', $out, $matches, PREG_OFFSET_CAPTURE ) ) {
			$subcommands = $matches[1][0];
			$subcommands_header = "## SUBCOMMANDS\n";
			$out = substr_replace( $out, $subcommands_header, $matches[1][1], strlen( $subcommands ) );
		}

		$out .= $command->get_longdesc();

		// definition lists
		$out = preg_replace_callback( '/([^\n]+)\n: (.+?)(\n\n|$)/s', array( __CLASS__, 'rewrap_param_desc' ), $out );

		// Ensure all non-section headers are indented.
		$out = preg_replace( '#^([^\s^\#])#m', "\t$1", $out );

		$tab = str_repeat( ' ', 2 );

		// Need to de-tab for wordwrapping to work properly.
		$out = str_replace( "\t", $tab, $out );

		$wordwrap_width = \cli\Shell::columns();

		// Wordwrap with indent.
		$out = preg_replace_callback( '/^( *)([^\n]+)\n/m', function ( $matches ) use ( $wordwrap_width ) {
			return $matches[1] . str_replace( "\n", "\n{$matches[1]}", wordwrap( $matches[2], $wordwrap_width - strlen( $matches[1] ) ) ) . "\n";
		}, $out );

		if ( $subcommands ) {
			// Wordwrap with column indent.
			$subcommands = preg_replace_callback( '/^(' . $column_subpattern . ')([^\n]+)\n/m', function ( $matches ) use ( $wordwrap_width, $tab ) {
				// Need to de-tab for wordwrapping to work properly.
				$matches[1] = str_replace( "\t", $tab, $matches[1] );
				$matches[2] = str_replace( "\t", $tab, $matches[2] );
				$padding_len = strlen( $matches[1] );
				$padding = str_repeat( ' ', $padding_len );
				return $matches[1] . str_replace( "\n", "\n$padding", wordwrap( $matches[2], $wordwrap_width - $padding_len ) ) . "\n";
			}, $subcommands );

			// Put subcommands back.
			$out = str_replace( $subcommands_header, $subcommands, $out );
		}

		// section headers
		$out = preg_replace( '/^## ([A-Z ]+)/m', WP_CLI::colorize( '%9\1%n' ), $out );

		self::pass_through_pager( $out );
	}

	private static function rewrap_param_desc( $matches ) {
		$param = $matches[1];
		$desc = self::indent( "\t\t", $matches[2] );
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

		if ( ! Utils\check_proc_available( null /*context*/, true /*return*/ ) ) {
			WP_CLI::debug( 'Warning: check_proc_available() failed in pass_through_pager().', 'help' );
			return $out;
		}

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
			2 => STDERR,
		);

		return proc_close( proc_open( $pager, $descriptorspec, $pipes ) );
	}

	private static function get_initial_markdown( $command ) {
		$name = implode( ' ', Dispatcher\get_path( $command ) );

		$binding = array(
			'name' => $name,
			'shortdesc' => $command->get_shortdesc(),
		);

		$binding['synopsis'] = "$name " . $command->get_synopsis();

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

