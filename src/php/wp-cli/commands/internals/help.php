<?php

WP_CLI::addCommand('help', 'HelpCommand');

/**
 * Implement help command
 *
 * @package wp-cli
 * @subpackage commands/internals
 */
class HelpCommand extends WP_CLI_Command {

	/**
	 * Overwrite the constructor to have a command without sub-commands.
     *
     * @param array $args
	 */
	public function __construct( $args ) {
		if ( empty( $args ) ) {
			$this->general_help();
		} else {
			$command = $args[0];
			if ( 'help' == $command || !isset( WP_CLI::$commands[$command] ) ) {
				$this->generalHelp();
			} else {
				$class = WP_CLI::$commands[$command];

				if ( method_exists( $class, 'help' ) ) {
					$class::help();
				} else {
					WP_CLI::line( 'Example usage:' );
					$this->single_command_help( $command, $class );
				}
			}
		}
	}

	private function general_help() {
		WP_CLI::line('Example usage:');
		foreach ( WP_CLI::$commands as $name => $command ) {
			if ( 'help' == $name )
				continue;

			$this->single_command_help( $name, $command );
		}
		WP_CLI::line('    wp --version');
		WP_CLI::line();
		WP_CLI::line( "See 'wp help <command>' for more information on a specific command." );
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
