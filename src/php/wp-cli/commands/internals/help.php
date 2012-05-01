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

		$this->maybe_load_man_page( $args );

		$this->show_available_subcommands( $args[0] );
	}

	private function maybe_load_man_page( $args ) {
		$man_dir = WP_CLI_ROOT . "../../../man/";

		if ( !is_dir( $man_dir ) ) {
			WP_CLI::warning( "man pages do not seem to be installed." );
		} else {
			$man_file = $man_dir . implode( '-', $args ) . '.1';

			if ( is_readable( $man_file ) ) {
				WP_CLI::launch( "man $man_file" );
			}
		}
	}

	private function show_available_subcommands( $command ) {
		$class = WP_CLI::load_command( $command );

		if ( method_exists( $class, 'help' ) ) {
			$class::help();
		} else {
			WP_CLI::line( 'Example usage:' );
			$this->single_command_help( $command, $class );
			WP_CLI::line();
			WP_CLI::line( "See 'wp help blog <subcommand>' for more information on a specific subcommand." );
		}
	}

	private function general_help() {
		WP_CLI::line( 'Available commands:' );
		foreach ( WP_CLI::load_all_commands() as $name => $class ) {
			if ( 'help' == $name )
				continue;

			$this->single_command_help( $name, $class );
		}

		WP_CLI::line(<<<EOB

See 'wp help <command>' for more information on a specific command.

Global parameters:
    --user=<id|login>   set the current user
    --url=<url>         set the current URL
    --path=<path>       set the current path to the WP install
    --require=<path>    load a certain file before running the command
    --version           print wp-cli version
EOB
		);
	}

	private function single_command_help( $name, $class ) {
		$out = "    wp $name";

		$methods = WP_CLI_Command::get_subcommands( $class );

		if ( !empty( $methods ) ) {
			$out .= ' [' . implode( '|', $methods ) . ']';
		}

		WP_CLI::line( $out );
	}
}
