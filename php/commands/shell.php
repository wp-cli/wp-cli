<?php

namespace WP_CLI\Commands;

class Shell_Command extends \WP_CLI_Command {

	/**
	 * Interactive PHP console.
	 */
	public function __invoke() {
		\WP_CLI::line( 'Type "exit" to close session.' );

		$this->set_history_file();

		while ( true ) {
			$line = $this->prompt();

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

	private function prompt() {
		static $cmd;

		if ( !$cmd ) {
			$cmd = self::create_prompt_cmd( 'wp> ', $this->history_file );
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

	private function print_history() {
		if ( !is_readable( $this->history_file ) )
			return;

		$lines = array_filter( explode( "\n", file_get_contents( $this->history_file ) ) );

		foreach ( $lines as $line ) {
			if ( 'history' == $line )
				continue;

			$line = rtrim( $line, ';' ) . ';';

			echo "$line\n";
		}
	}

	private function set_history_file() {
		$data = getcwd() . get_current_user();

		$this->history_file = sys_get_temp_dir() . '/wp-cli-history-' . md5( $data );
	}

	private static function starts_with( $tokens, $line ) {
		return preg_match( "/^($tokens)[\(\s]+/", $line );
	}
}

\WP_CLI::add_command( 'shell', 'Shell_Command' );

