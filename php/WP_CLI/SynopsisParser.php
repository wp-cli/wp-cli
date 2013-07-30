<?php

namespace WP_CLI;

class SynopsisParser {

	private static $patterns = array();

	/**
	 * @param string
	 * @return array List of parameters
	 */
	static function parse( $synopsis ) {
		if ( empty( self::$patterns ) )
			self::init_patterns();

		$tokens = array_filter( preg_split( '/[\s\t]+/', $synopsis ) );

		$params = array();

		foreach ( $tokens as $token ) {
			$type = false;

			foreach ( self::$patterns as $regex => $desc ) {
				if ( preg_match( $regex, $token, $matches ) ) {
					$type = $desc['type'];
					$params[] = array_merge( $matches, $desc );
					break;
				}
			}

			if ( !$type ) {
				$params[] = array(
					'type' => 'unknown',
					'token' => $token
				);
			}
		}

		return $params;
	}

	private static function init_patterns() {
		$p_name = '(?P<name>[a-z-_]+)';
		$p_value = '(?P<value>[a-zA-Z-|]+)';

		self::gen_patterns( 'positional', "<$p_value>",           array( 'mandatory', 'optional', 'repeating' ) );
		self::gen_patterns( 'generic',    "--<field>=<value>",    array( 'mandatory', 'optional', 'repeating' ) );
		self::gen_patterns( 'assoc',      "--$p_name=<$p_value>", array( 'mandatory', 'optional' ) );
		self::gen_patterns( 'flag',       "--$p_name",            array( 'optional' ) );
	}

	private static function gen_patterns( $type, $pattern, $flavour_types ) {
		static $flavours = array(
			'mandatory' => ':pattern:',
			'optional' => '\[:pattern:\]',
			'repeating' => array( ':pattern:...', '\[:pattern:...\]' )
		);

		foreach ( $flavour_types as $flavour_type ) {
			foreach ( (array) $flavours[ $flavour_type ] as $flavour ) {
				$final_pattern = str_replace( ':pattern:', $pattern, $flavour );

				self::$patterns[ '/^' . $final_pattern . '$/' ] = array(
					'type' => $type,
					'flavour' => $flavour_type
				);
			}
		}
	}
}

