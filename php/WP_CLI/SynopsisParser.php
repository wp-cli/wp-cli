<?php

namespace WP_CLI;

class SynopsisParser {

	/**
	 * @param string
	 * @return array List of parameters
	 */
	static function parse( $synopsis ) {
		list( $patterns, $params ) = self::get_patterns();

		$tokens = array_filter( preg_split( '/[\s\t]+/', $synopsis ) );

		foreach ( $tokens as $token ) {
			$type = false;

			foreach ( $patterns as $regex => $desc ) {
				if ( preg_match( $regex, $token, $matches ) ) {
					$type = $desc['type'];
					$params[$type][] = array_merge( $matches, $desc );
					break;
				}
			}

			if ( !$type ) {
				$params['unknown'][] = $token;
			}
		}

		return $params;
	}

	private static function get_patterns() {
		$p_name = '(?P<name>[a-z-_]+)';
		$p_value = '(?P<value>[a-z-|]+)';

		$param_types = array(
			array( 'positional', "<$p_value>",           1, 1 ),
			array( 'generic',    "--<field>=<value>",    1, 1 ),
			array( 'assoc',      "--$p_name=<$p_value>", 1, 1 ),
			array( 'flag',       "--$p_name",            1, 0 ),
		);

		$patterns = array();
		$params = array();

		foreach ( $param_types as $pt ) {
			list( $type, $pattern, $optional, $mandatory ) = $pt;

			if ( $mandatory ) {
				$patterns[ "/^$pattern$/" ] = array(
					'type' => $type,
					'optional' => false
				);
			}

			if ( $optional ) {
				$patterns[ "/^\[$pattern\]$/" ] = array(
					'type' => $type,
					'optional' => true
				);
			}

			$params[ $type ] = array();
		}

		return array( $patterns, $params );
	}
}

