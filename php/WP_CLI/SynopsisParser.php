<?php

namespace WP_CLI;

class SynopsisParser {

	private static $patterns = array();

	/**
	 * @param string
	 * @return array List of parameters
	 */
	static function parse( $synopsis ) {
		$tokens = array_filter( preg_split( '/[\s\t]+/', $synopsis ) );

		$params = array();

		if ( empty( self::$patterns ) )
			self::init_patterns();

		foreach ( $tokens as $token ) {
			$type = false;

			// Check each token against all the types of patterns
			foreach ( self::$patterns as $regex => $desc ) {
				// Cleanup for the regex that expect no brackets
				$_token = str_replace( array('[', ']'), '', $token );

				if ( preg_match( $regex, $_token, $matches ) ) {
					$type = $desc['type']; // Add type of param
					$desc['flavour'] = self::get_flavour( $token, $type ); // Add flavour of param

					if ( 'flag' == $type && in_array('invalid', $desc['flavour']) ) {
						$type = false;
					} else {
						$params[] = array_merge( $matches, $desc );
					}
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

	/**
	 * @todo check if token is correct
	 **/
	private static function get_flavour( $token, $type ){
		$flavour = false;

		//checking for full optionals [--a], [--a=<a>], [--a[=<a>]]
		if( ( substr($token, 0, 1) === '[' && substr($token, -1) === ']' ) ) {
			$flavour[] = 'optional';
			// Alright we know you are fully optional, remove the brackets and check for partial value-optionals.
			$token = substr($token, 1, -1);
		} elseif( 'flag' != $type ) {
			$flavour[] = 'mandatory';
		} else {
			$flavour[] = 'invalid';
		}

		// Matches for the remaining --a[=a], [--a[=<a>].
		// The later matches because we already removed the outer brackets
		if( preg_match('/[^[\]]+(?=])/', $token, $matches) ) {
			$flavour[] = 'value-optional'; // This one enable us to prompt for a value that was left out on purpose
		} elseif( strpos($token, '=') !== false ) {
			$flavour[] = 'value-mandatory'; //these are generics or assocs type
		}

		if ( strpos($token, '...') !== false && !in_array($type, array('assoc', 'flag')) ) {
			$flavour[] = 'repeating';
		}

		return $flavour;
	}

	private static function init_patterns() {
		$p_name = '(?P<name>[a-z-_]+)';
		$p_value = '(?P<value>[a-zA-Z-|]+)';

		self::gen_patterns( 'positional', "<$p_value>",           array( 'single', 'repeating' ) );
		self::gen_patterns( 'generic',    "--<field>=<value>",    array( 'single', 'repeating' ) );
		self::gen_patterns( 'assoc',      "--$p_name=<$p_value>", array( 'single' ) );
		self::gen_patterns( 'flag',       "--$p_name",            array( 'single' ) );
	}

	private static function gen_patterns( $type, $pattern, $flavour_types ) {
		static $flavours = array(
			'single' => ':pattern:',
			'repeating' => ':pattern:...'
		);

		foreach ( $flavour_types as $flavour_type ) {
			foreach ( (array) $flavours[ $flavour_type ] as $flavour ) {
				$final_pattern = str_replace( ':pattern:', $pattern, $flavour );

				self::$patterns[ '/^' . $final_pattern . '$/' ] = array(
					'type' => $type
				);
			}
		}
	}
}

