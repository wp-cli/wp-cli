<?php

namespace WP_CLI\Commands;

\WP_CLI::add_command( 'shell', new Shell_Command );

class Shell_Command extends \WP_CLI_Command {

	/**
	 * Open an interactive shell environment.
	 */
	public function __invoke() {
		if ( function_exists( 'readline' ) ) {
			$repl = new REPL_Readline;
		} else {
			$repl = new REPL_Basic;
		}

		$non_expressions = array(
			'echo', 'return', 'global',
			'while', 'for', 'foreach', 'if', 'switch',
			'include', 'include\_once', 'require', 'require\_once'
		);
		$non_expressions = implode( '|', $non_expressions );

		$pattern = "/^($non_expressions)[\(\s]+/";

		while ( true ) {
			$line = $repl->read( 'wp> ' );

			if ( !preg_match( $pattern, $line ) )
				$line = 'return ' . $line;

			$line .= ';';

			$_ = eval( $line );

			if ( false === $_ )
				continue;

			\WP_CLI::line( var_export( $_, false ) );
		}
	}
}


class REPL_Readline {

	function __construct() {
		$this->hist_path = getcwd() . '/.wp-cli-history';

		readline_read_history( $this->hist_path );

		register_shutdown_function( array( $this, 'save_history' ) );
	}

	function read( $prompt ) {
		$line = trim( readline( $prompt ) );
		if ( !empty( $line ) )
			readline_add_history( $line );

		return $line;
	}

	function save_history() {
		readline_write_history( $this->hist_path );
	}
}


class REPL_Basic {

	function read( $prompt ) {
		\WP_CLI::out( $prompt );
		return \cli\input();
	}
}

