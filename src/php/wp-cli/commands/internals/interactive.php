<?php

namespace WP_CLI\Commands;

\WP_CLI::add_command( 'interactive', new Interactive_Command );

class Interactive_Command extends \WP_CLI_Command {

	/**
	 * Open an interactive shell environment.
	 */
	public function __invoke() {
		if ( function_exists( 'readline' ) ) {
			$repl = new REPL_Readline;
		} else {
			$repl = new REPL_Basic;
		}

		while ( true ) {
			$in = $repl->prompt( 'wp> ' );

			if ( 'exit' == $in )
				return;

			$r = eval( $in );

			if ( false === $r )
				continue;

			if ( null === $r )
				\WP_CLI::line();
			else
				var_export( $r );
		}
	}
}


class REPL_Readline {

	function prompt( $str ) {
		$line = readline( $str );
		readline_add_history( $line );
		return $line;
	}
}


class REPL_Basic {

	function prompt( $str ) {
		\WP_CLI::out( $str );
		return \cli\input();
	}
}

