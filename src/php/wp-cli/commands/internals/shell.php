<?php

WP_CLI::add_command( 'shell', new Shell_Command );

class Shell_Command extends WP_CLI_Command {

	/**
	 * Open an interactive shell environment.
	 */
	public function __invoke() {
		if ( function_exists( 'readline' ) ) {
			$repl = 'repl_readline';
		} else {
			$repl = 'repl_basic';
		}

		while ( true ) {
			$line = call_user_func( array( __CLASS__, $repl ), 'wp> ' );

			if ( 'exit' == $line )
				return;

			if ( !preg_match( '/^\s*(global|echo|return)\s+/', $line ) )
				$line = 'return ' . $line;

			$line .= ';';

			$_ = eval( $line );

			if ( false === $_ )
				continue;

			\WP_CLI::line( var_export( $_, false ) );
		}
	}

	private static function repl_readline( $str ) {
		$line = readline( $str );
		readline_add_history( $line );
		return $line;
	}

	private static function repl_basic( $str ) {
		\WP_CLI::out( $str );
		return \cli\input();
	}
}

