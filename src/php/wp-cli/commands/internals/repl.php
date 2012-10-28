<?php

WP_CLI::add_command( 'repl', new Repl_Command );

class Repl_Command extends WP_CLI_Command {

	/**
	 * Open an interactive shell environment.
	 */
	public function __invoke() {
		while ( true ) {
			WP_CLI::out( 'wp> ' );

			$in = \cli\input();

			echo eval( $in );
		}
	}
}

