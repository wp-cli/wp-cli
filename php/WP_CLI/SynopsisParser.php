<?php

namespace WP_CLI;

/**
 * Generate a synopsis from a command's PHPdoc arguments.
 * Turns something like "<object-id>..."
 * into [ optional=>false, type=>positional, repeating=>true, name=>object-id ]
 */
class SynopsisParser {

	/**
	 * @param string $synopsis A synopsis
	 * @return array List of parameters
	 */
	public static function parse( $synopsis ) {
		$tokens = array_filter( preg_split( '/[\s\t]+/', $synopsis ) );

		$params = [];
		foreach ( $tokens as $token ) {
			$param = self::classify_token( $token );

			// Some types of parameters shouldn't be mandatory
			if ( isset( $param['optional'] ) && ! $param['optional'] ) {
				if ( 'flag' === $param['type'] || ( 'assoc' === $param['type'] && $param['value']['optional'] ) ) {
					$param['type'] = 'unknown';
				}
			}

			$param['token'] = $token;
			$params[]       = $param;
		}

		return $params;
	}

	/**
	 * Render the Synopsis into a format string.
	 *
	 * @param array $synopsis A structured synopsis. This might get reordered
	 *                        to match the parsed output.
	 * @return string Rendered synopsis.
	 */
	public static function render( &$synopsis ) {
		if ( ! is_array( $synopsis ) ) {
			return '';
		}
		$bits               = [
			'positional' => '',
			'assoc'      => '',
			'generic'    => '',
			'flag'       => '',
		];
		$reordered_synopsis = [
			'positional' => [],
			'assoc'      => [],
			'generic'    => [],
			'flag'       => [],
		];
		foreach ( $bits as $key => &$value ) {
			foreach ( $synopsis as $arg ) {
				if ( empty( $arg['type'] )
					|| $key !== $arg['type'] ) {
					continue;
				}

				if ( empty( $arg['name'] ) && 'generic' !== $arg['type'] ) {
					continue;
				}

				if ( 'positional' === $key ) {
					$rendered_arg = "<{$arg['name']}>";

					$reordered_synopsis['positional'] [] = $arg;
				} elseif ( 'assoc' === $key ) {
					$arg_value    = isset( $arg['value']['name'] ) ? $arg['value']['name'] : $arg['name'];
					$rendered_arg = "--{$arg['name']}=<{$arg_value}>";

					$reordered_synopsis['assoc'] [] = $arg;
				} elseif ( 'generic' === $key ) {
					$rendered_arg = '--<field>=<value>';

					$reordered_synopsis['generic'] [] = $arg;
				} elseif ( 'flag' === $key ) {
					$rendered_arg = "--{$arg['name']}";

					$reordered_synopsis['flag'] [] = $arg;
				}
				if ( ! empty( $arg['repeating'] ) ) {
					$rendered_arg = "{$rendered_arg}...";
				}
				if ( ! empty( $arg['optional'] ) ) {
					$rendered_arg = "[{$rendered_arg}]";
				}
				$value .= "{$rendered_arg} ";
			}
		}
		$rendered = '';
		foreach ( $bits as $v ) {
			if ( ! empty( $v ) ) {
				$rendered .= $v;
			}
		}

		$synopsis = array_merge(
			$reordered_synopsis['positional'],
			$reordered_synopsis['assoc'],
			$reordered_synopsis['generic'],
			$reordered_synopsis['flag']
		);

		return rtrim( $rendered, ' ' );
	}

	/**
	 * Classify argument attributes based on its syntax.
	 *
	 * @param string $token
	 * @return array
	 */
	private static function classify_token( $token ) {
		$param = [];

		list( $param['optional'], $token )  = self::is_optional( $token );
		list( $param['repeating'], $token ) = self::is_repeating( $token );

		$p_name  = '([a-z-_0-9]+)';
		$p_value = '([a-zA-Z-_|,0-9]+)';

		if ( '--<field>=<value>' === $token ) {
			$param['type'] = 'generic';
		} elseif ( preg_match( "/^<($p_value)>$/", $token, $matches ) ) {
			$param['type'] = 'positional';
			$param['name'] = $matches[1];
		} elseif ( preg_match( "/^--(?:\\[no-\\])?$p_name/", $token, $matches ) ) {
			$param['name'] = $matches[1];

			$value = substr( $token, strlen( $matches[0] ) );

			// substr returns false <= PHP 5.6, and '' PHP 7+
			if ( false === $value || '' === $value ) {
				$param['type'] = 'flag';
			} else {
				$param['type'] = 'assoc';

				list( $param['value']['optional'], $value ) = self::is_optional( $value );

				if ( preg_match( "/^=<$p_value>$/", $value, $matches ) ) {
					$param['value']['name'] = $matches[1];
				} else {
					$param = [
						'type' => 'unknown',
					];
				}
			}
		} else {
			$param['type'] = 'unknown';
		}

		return $param;
	}

	/**
	 * An optional parameter is surrounded by square brackets.
	 *
	 * @param string $token
	 * @return array
	 */
	private static function is_optional( $token ) {
		if ( '[' === substr( $token, 0, 1 ) && ']' === substr( $token, -1 ) ) {
			return [ true, substr( $token, 1, -1 ) ];
		}

		return [ false, $token ];
	}

	/**
	 * A repeating parameter is followed by an ellipsis.
	 *
	 * @param string $token
	 * @return array
	 */
	private static function is_repeating( $token ) {
		if ( '...' === substr( $token, -3 ) ) {
			return [ true, substr( $token, 0, -3 ) ];
		}

		return [ false, $token ];
	}
}
