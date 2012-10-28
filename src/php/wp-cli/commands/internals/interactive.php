<?php

WP_CLI::add_command( 'interactive', new Interactive_Command );

class Interactive_Command extends WP_CLI_Command {

	/**
	 * Open an interactive shell environment.
	 */
	public function __invoke() {
		while ( true ) {
			WP_CLI::out( 'wp> ' );

			$in = \cli\input();

			if ( 'exit' == $in )
				return;

			$r = eval( $in );

			if ( null === $r )
				WP_CLI::line();
			else
				var_export( $r );
		}
	}
}

