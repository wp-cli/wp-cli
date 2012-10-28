<?php

WP_CLI::add_command( 'interactive', new Interactive_Command );

class Interactive_Command extends WP_CLI_Command {

	/**
	 * Open an interactive shell environment.
	 */
	public function __invoke() {
		while ( true ) {
			$in = $this->read_input();

			if ( 'exit' == $in )
				return;

			$r = eval( $in );

			if ( false === $r )
				continue;

			if ( null === $r )
				WP_CLI::line();
			else
				var_export( $r );
		}
	}

	private function read_input() {
		if ( function_exists( 'readline' ) ) {
			$line = readline( 'wp> ' );
			readline_add_history( $line );
		} else {
			WP_CLI::out( 'wp> ' );
			$line = \cli\input();
		}

		return $line;
	}
}

