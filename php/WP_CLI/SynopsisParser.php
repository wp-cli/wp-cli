<?php

namespace WP_CLI;

/**
 * Generate a synopsis from a command's PHPdoc arguments.
 * Turns something like "<object-id>..."
 * into [ optional=>false, type=>positional, repeating=>true, name=>object-id ]
 */
class SynopsisParser {

	/**
	 * @param string A synopsis
	 * @return array List of parameters
	 */
	public static function parse( $synopsis ) {
		$tokens = array_filter( preg_split( '/[\s\t]+/', $synopsis ) );

		$params = array();
		foreach ( $tokens as $token ) {
			$param = self::classify_token( $token );

			// Some types of parameters shouldn't be mandatory
			if ( isset( $param['optional'] ) && !$param['optional'] ) {
				if ( 'flag' === $param['type'] || ( 'assoc' === $param['type'] && $param['value']['optional'] ) ) {
					$param['type'] = 'unknown';
				}
			}

			$param['token'] = $token;
			$params[] = $param;
		}

		return $params;
	}

	/**
	 * Classify argument attributes based on its syntax.
	 *
	 * @param string $token
	 * @return array $param
	 */
	private static function classify_token( $token ) {
		$param = array();

		list( $param['optional'], $token ) = self::is_optional( $token );
		list( $param['repeating'], $token ) = self::is_repeating( $token );

		$p_name = '([a-z-_]+)';
		$p_value = '([a-zA-Z-_|,]+)';

		if ( '--<field>=<value>' === $token ) {
			$param['type'] = 'generic';
		} elseif ( preg_match( "/^<($p_value)>$/", $token, $matches ) ) {
			$param['type'] = 'positional';
			$param['name'] = $matches[1];
		} elseif ( preg_match( "/^--(?:\\[no-\\])?$p_name/", $token, $matches ) ) {
			$param['name'] = $matches[1];

			$value = substr( $token, strlen( $matches[0] ) );

			if ( false === $value ) {
				$param['type'] = 'flag';
			} else {
				$param['type'] = 'assoc';

				list( $param['value']['optional'], $value ) = self::is_optional( $value );

				if ( preg_match( "/^=<$p_value>$/", $value, $matches ) ) {
					$param['value']['name'] = $matches[1];
				} else {
					$param = array( 'type' => 'unknown' );
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
		if ( '[' == substr( $token, 0, 1 ) && ']' == substr( $token, -1 ) ) {
			return array( true, substr( $token, 1, -1 ) );
		} else {
			return array( false, $token );
		}
	}

	/**
	 * A repeating parameter is followed by an ellipsis.
	 *
	 * @param string $token
	 * @return array
	 */
	private static function is_repeating( $token ) {
		if ( '...' === substr( $token, -3 ) ) {
			return array( true, substr( $token, 0, -3 ) );
		} else {
			return array( false, $token );
		}
	}
}

