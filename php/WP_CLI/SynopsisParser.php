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

	private static $patterns = array();
	private static $params = array();

	private static function get_patterns() {
		$p_name = '(?P<name>[a-z-_]+)';
		$p_value = '(?P<value>[a-z-|]+)';

		self::gen_patterns( 'positional', "<$p_value>",           array( 'mandatory', 'optional' ) );
		self::gen_patterns( 'generic',    "--<field>=<value>",    array( 'mandatory', 'optional' ) );
		self::gen_patterns( 'assoc',      "--$p_name=<$p_value>", array( 'mandatory', 'optional' ) );
		self::gen_patterns( 'flag',       "--$p_name",            array( 'optional' ) );

		return array( self::$patterns, self::$params );
	}

	private function gen_patterns( $type, $pattern, $flavour_types ) {
		static $flavours = array(
			'mandatory' => "/^:pattern:$/",
			'optional' => "/^\[:pattern:\]$/",
		);

		foreach ( $flavour_types as $flavour_type ) {
			$flavour = $flavours[ $flavour_type ];

			self::$patterns[ str_replace( ':pattern:', $pattern, $flavour ) ] = array(
				'type' => $type,
				$flavour_type => true
			);
		}

		self::$params[ $type ] = array();
	}
}

