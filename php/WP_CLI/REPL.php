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
				ob_start();
				eval( $line );
				$out = ob_get_clean();
				if ( 0 < strlen ( $out ) ) {
					$out = rtrim( $out, "\n" ) . "\n";
				}
				fwrite( STDOUT, $out );
			} else {
				if ( !self::starts_with( 'return', $line ) )
					$line = 'return ' . $line;

				// Write directly to STDOUT, to sidestep any output buffers created by plugins
				ob_start();
				$evl  = eval( $line );
				$out = ob_get_clean();
				if ( 0 < strlen ( $out ) ) {
					echo rtrim( $out, "\n" ) . "\n";
				}
				echo "=> ";
				var_dump( $evl );
				fwrite( STDOUT, ob_get_clean() );
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

		$cmd = "set -f; "
			. "history -r $history_path; "
			. "LINE=\"\"; "
			. "read -re -p $prompt LINE; "
			. "[ $? -eq 0 ] || exit; "
			. "history -s \"\$LINE\"; "
			. "history -w $history_path; "
			. "echo \$LINE; ";

		return '/bin/bash -c ' . escapeshellarg( $cmd );
	}

	private function set_history_file() {
		$data = getcwd() . get_current_user();

		$this->history_file = \WP_CLI\Utils\get_temp_dir() . 'wp-cli-history-' . md5( $data );
	}

	private static function starts_with( $tokens, $line ) {
		return preg_match( "/^($tokens)[\(\s]+/", $line );
	}
}

