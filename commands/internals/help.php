<?php

// Add the command to the wp-cli
WP_CLI::addCommand('help', 'HelpCommand');

/**
 * Implement help command
 *
 * @package wp-cli
 * @subpackage commands/internals
 */
class HelpCommand extends WP_CLI_Command {

	/**
	 * Overwrite the dispatch method to have a command without sub-commands.
     *
     * @param array $args
	 */
	protected function dispatch( $args ) {
		if ( empty( $args ) ) {
			$this->generalHelp();
		} else {
			$command = $args[0];
			if ( 'help' == $command || !isset( WP_CLI::$commands[$command] ) ) {
				$this->generalHelp();
			} else {
				new WP_CLI::$commands[$command]( $command, array( 'help' ), array() );
			}
		}
	}

	private function generalHelp() {
		WP_CLI::line('Example usage:');
		foreach ( WP_CLI::$commands as $name => $command ) {
			if ( 'help' == $name )
				continue;

			WP_CLI::out( '    wp ' . $name );
			$methods = WP_CLI_Command::get_subcommands( $command );
			if( !empty( $methods ) ) {
				WP_CLI::out( ' [' . implode( '|', $methods ) . ']' );
			}
			WP_CLI::line(' ...');
		}
		WP_CLI::line('    wp --version');
		WP_CLI::line();
		WP_CLI::line( "See 'wp help <command>' for more information on a specific command." );
	}
}
