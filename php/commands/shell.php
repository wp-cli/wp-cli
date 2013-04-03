<?php

namespace WP_CLI\Commands;

class Shell_Command extends \WP_CLI_Command {

	/**
	 * Interactive PHP console.
	 */
	public function __invoke() {
		\WP_CLI::line( 'Type "exit" to close session.' );

		while ( true ) {
			$line = self::prompt();

			switch ( $line ) {
				case '': {
					continue 2;
				}

				case 'history': {
					self::print_history();
					continue 2;
				}
			}

			$line = rtrim( $line, ';' ) . ';';

			if ( self::starts_with( self::non_expressions(), $line ) ) {
				eval( $line );
			} else {
				if ( self::starts_with( 'return', $line ) )
					$line = substr( $line, strlen( 'return' ) );

				$line = '$_ = ' . $line;

				eval( $line );

				\WP_CLI::line( var_export( $_, false ) );
			}
		}
	}

	private static function non_expressions() {
		return implode( '|', array(
			'echo', 'global', 'unset',
			'while', 'for', 'foreach', 'if', 'switch',
			'include', 'include\_once', 'require', 'require\_once'
		) );
	}

	private static function prompt() {
		static $cmd;

		if ( !$cmd ) {
			$cmd = self::create_prompt_cmd( 'wp> ', self::get_history_path() );
		}

		$fp = popen( $cmd, 'r' );

		$line = fgets( $fp );

		if ( !$line ) {
			$line = 'exit';
		}

		return trim( $line );
	}

	private static function create_prompt_cmd( $prompt, $history_path ) {
		$prompt = escapeshellarg( $prompt );
		$history_path = escapeshellarg( $history_path );

		$cmd = <<<BASH
set -f
history -r $history_path
LINE=""
read -re -p $prompt LINE
[ $? -eq 0 ] || exit
history -s "\$LINE"
history -w $history_path
echo \$LINE
BASH;

		$cmd = str_replace( "\n", '; ', $cmd );

		return '/bin/bash -c ' . escapeshellarg( $cmd );
	}

	private static function print_history() {
		$history_file = self::get_history_path();

		if ( !is_readable( $history_file ) )
			return;

		$lines = array_filter( explode( "\n", file_get_contents( $history_file ) ) );

		foreach ( $lines as $line ) {
			if ( 'history' == $line )
				continue;

			$line = rtrim( $line, ';' ) . ';';

			echo "$line\n";
		}
	}

	private static function get_history_path() {
		$data = getcwd() . get_current_user();

		return sys_get_temp_dir() . '/wp-cli-history-' . md5( $data );
	}

	private static function starts_with( $tokens, $line ) {
		return preg_match( "/^($tokens)[\(\s]+/", $line );
	}
}

\WP_CLI::add_command( 'shell', new Shell_Command );

