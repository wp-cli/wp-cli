<?php

WP_CLI::add_command('help', 'Help_Command');

/**
 * Implement help command
 *
 * @package wp-cli
 * @subpackage commands/internals
 */
class Help_Command extends WP_CLI_Command {

	/**
	 * Overwrite the constructor to have a command without sub-commands.
	 *
	 * @param array $args
	 */
	public function __construct( $args ) {
		if ( empty( $args ) ) {
			$this->general_help();
			return;
		}

		$command = $args[0];

		if ( !isset( WP_CLI::$commands[$command] ) )
			WP_CLI::error( "'$command' is not a registered wp command." );

		if ( !$this->show_help( $args ) ) {
			$class = WP_CLI::$commands[$command];

			if ( method_exists( $class, 'help' ) ) {
				$class::help( $subcommand );
			} else {
				WP_CLI::line( 'Example usage:' );
				$this->single_command_help( $command, $class );
			}
		}
	}

	private function show_help( $args ) {
		$doc_file = WP_CLI::get_path( 'doc' ) . implode( '-', $args ) . '.md';

		if ( !is_readable( $doc_file ) )
			return false;

		echo file_get_contents( $doc_file );

		return true;
	}

	private function general_help() {
		WP_CLI::line( 'Available commands:' );
		foreach ( WP_CLI::$commands as $name => $command ) {
			if ( 'help' == $name )
				continue;

			$this->single_command_help( $name, $command );
		}

		$this->show_help( 'general' );
	}

	private function single_command_help( $name, $command ) {
		WP_CLI::out( '    wp ' . $name );

		$methods = WP_CLI_Command::get_subcommands( $command );

		if ( !empty( $methods ) ) {
			WP_CLI::out( ' [' . implode( '|', $methods ) . ']' );
		}
		WP_CLI::line(' ...');
	}
}
