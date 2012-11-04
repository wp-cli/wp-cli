<?php

namespace WP_CLI\Commands;

\WP_CLI::add_command( 'shell', new Shell_Command );

class Shell_Command extends \WP_CLI_Command {

	/**
	 * Open an interactive shell environment.
	 */
	public function __invoke() {
		\WP_CLI::line( 'Type "exit" to close session.' );

		$non_expressions = array(
			'echo', 'global', 'unset',
			'while', 'for', 'foreach', 'if', 'switch',
			'include', 'include\_once', 'require', 'require\_once'
		);
		$non_expressions = implode( '|', $non_expressions );

		while ( true ) {
			$line = self::prompt( 'wp> ', self::get_history_path() );

			if ( '' === $line )
				continue;

			$line = rtrim( $line, ';' ) . ';';

			if ( self::starts_with( $non_expressions, $line ) ) {
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

	private static function prompt( $prompt, $history_path ) {
		$cmds = array(
			'set -f',
			sprintf( 'history -r %s', escapeshellarg( $history_path ) ),
			'LINE=""',
			sprintf( 'read -re -p %s LINE', escapeshellarg( $prompt ) ),
			'history -s "$LINE"',
			sprintf( 'history -w %s', escapeshellarg( $history_path ) ),
			'echo $LINE'
		);

		$cmd = implode( '; ', $cmds );

		$fp = popen( '/bin/bash -c ' . escapeshellarg( $cmd ), 'r' );

		$line = fgets( $fp );

		return trim( $line );
	}

	private static function get_history_path() {
		$data = getcwd() . get_current_user();

		return sys_get_temp_dir() . '/wp-cli-history-' . md5( $data );
	}

	private static function starts_with( $tokens, $line ) {
		return preg_match( "/^($tokens)[\(\s]+/", $line );
	}
}

