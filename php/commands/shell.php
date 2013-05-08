<?php

class Shell_Command extends \WP_CLI_Command {

	/**
	 * Interactive PHP console.
	 */
	public function __invoke() {
		\WP_CLI::line( 'Type "exit" to close session.' );

		$repl = new \WP_CLI\REPL( 'wp> ' );
		$repl->start();
	}
}

\WP_CLI::add_command( 'shell', 'Shell_Command' );

