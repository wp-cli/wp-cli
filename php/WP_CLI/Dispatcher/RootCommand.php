<?php

namespace WP_CLI\Dispatcher;

use \WP_CLI\Utils;

/**
 * The root node in the command tree.
 */
class RootCommand extends CompositeCommand {

	function __construct() {
		parent::__construct( false, 'wp', '' );
	}

	function show_usage() {
		\WP_CLI::line( 'Available commands:' );

		foreach ( $this->get_subcommands() as $command ) {
			if ( '_sys' == $command->get_name() )
				continue;

			\WP_CLI::line( sprintf( "    %s %s",
				implode( ' ', get_path( $command ) ),
				implode( '|', array_keys( $command->get_subcommands() ) )
			) );
		}

		\WP_CLI::line(<<<EOB

See 'wp help <command>' for more information on a specific command.

Global parameters:
EOB
	);

		\WP_CLI::line( self::generate_synopsis() );
	}

	private static function generate_synopsis() {
		$max_len = 0;

		$lines = array();

		foreach ( \WP_CLI\Utils\get_config_spec() as $key => $details ) {
			if ( false === $details['runtime'] )
				continue;

			if ( isset( $details['deprecated'] ) )
				continue;

			$synopsis = ( true === $details['runtime'] )
				? "--[no-]$key"
				: "--$key" . $details['runtime'];

			$cur_len = strlen( $synopsis );

			if ( $max_len < $cur_len )
				$max_len = $cur_len;

			$lines[] = array( $synopsis, $details['desc'] );
		}

		foreach ( $lines as $line ) {
			list( $synopsis, $desc ) = $line;

			\WP_CLI::line( '    ' . str_pad( $synopsis, $max_len ) . '  ' . $desc );
		}
	}

	function find_subcommand( &$args ) {
		$command = array_shift( $args );

		Utils\load_command( $command );

		if ( !isset( $this->subcommands[ $command ] ) ) {
			return false;
		}

		return $this->subcommands[ $command ];
	}

	function get_subcommands() {
		Utils\load_all_commands();

		return parent::get_subcommands();
	}

	function has_subcommands() {
		// Commands are lazy-loaded, so we need to assume there will be some
		return true;
	}
}

