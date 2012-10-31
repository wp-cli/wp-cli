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
		$this->hist_path = self::get_history_path();

		readline_read_history( $this->hist_path );

		register_shutdown_function( array( $this, 'save_history' ) );

		declare( ticks = 1 );
		pcntl_signal( SIGINT, array( $this, 'catch_signal' ) );
		pcntl_signal( SIGTERM, array( $this, 'catch_signal' ) );
		pcntl_signal( SIGSEGV, array( $this, 'catch_signal' ) );
		pcntl_signal( SIGQUIT, array( $this, 'catch_signal' ) );
	}

	function catch_signal( $signo ) {
		switch ( $signo ) {
		case SIGTERM:
		case SIGSEGV:
		case SIGQUIT:
		case SIGABRT:
		case SIGINT:
			exit; // ensures clean shutdown
		}
	}

	private static function get_history_path() {
		$data = getcwd() . get_current_user();

		return sys_get_temp_dir() . '/wp-cli-history-' . md5( $data );
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

