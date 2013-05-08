<?php

namespace WP_CLI;

class REPL {

	private $promt;

	public function __construct( $prompt ) {
		$this->prompt = $prompt;

		$this->set_history_file();
	}

	public function start() {
		while ( true ) {
			$line = $this->prompt();

			if ( '' === $line ) continue;

			$line = rtrim( $line, ';' ) . ';';

			if ( self::starts_with( self::non_expressions(), $line ) ) {
				eval( $line );
			} else {
				if ( !self::starts_with( 'return', $line ) )
					$line = 'return ' . $line;

				var_dump( eval( $line ) );
			}
		}
	}

	private static function non_expressions() {
		return implode( '|', array(
			'echo', 'global', 'unset', 'function',
			'while', 'for', 'foreach', 'if', 'switch',
			'include', 'include\_once', 'require', 'require\_once'
		) );
	}

	private function prompt() {
		$full_line = false;

		$done = false;
		do {
			$prompt = ( !$done && $full_line !== false ) ? '--> ' : $this->prompt;

			$fp = popen( self::create_prompt_cmd( $prompt, $this->history_file ), 'r' );

			$line = fgets( $fp );

			if ( !$line ) {
				break;
			}

			$line = rtrim( $line, "\n" );

			if ( $line && '\\' == $line[ strlen( $line ) - 1 ] ) {
				$line = substr( $line, 0, -1 );
			} else {
				$done = true;
			}

			$full_line .= $line;

		} while ( !$done );

		if ( $full_line === false ) {
			return 'exit';
		}

		return $full_line;
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

	private function set_history_file() {
		$data = getcwd() . get_current_user();

		$this->history_file = sys_get_temp_dir() . '/wp-cli-history-' . md5( $data );
	}

	private static function starts_with( $tokens, $line ) {
		return preg_match( "/^($tokens)[\(\s]+/", $line );
	}
}

