<?php

namespace WP_CLI;

class SynopsisParser {

	private static $patterns = array();

	private $params = array();

	public function __construct( $synopsis ) {
		$this->params = $this->parse( $synopsis );
	}

	public function enough_positionals( $args ) {
		$positional = $this->query_params( array(
			'type' => 'positional',
			'flavour' => 'mandatory'
		) );

		return count( $args ) >= count( $positional );
	}

	public function validate_assoc( &$assoc_args, $ignored_keys = array() ) {
		$assoc = $this->query_params( array(
			'type' => 'assoc',
		) );

		$errors = array(
			'fatal' => array(),
			'warning' => array()
		);

		foreach ( $assoc as $param ) {
			$key = $param['name'];
			
			if ( in_array( $key, $ignored_keys ) )
				continue;

			if ( !isset( $assoc_args[ $key ] ) ) {
				if ( in_array('mandatory', $param['flavour']) ) {
					$errors['fatal'][] = "missing --$key parameter";
				}
			} else {
				
				// If the key is passed like --foo
				if ( true === $assoc_args[ $key ] ) {
					
					/**
					 * To make it a bit less scary:
					 * If just passing the --key is enough because the value is optional move along. Else trigger an error because the 
					 * value is also mandatory.
					 * Even if the assoc_arg is optional, if you pass just --foo without a value it still has to trigger an error.
					 * Because IF you gonna pass it, it has to be complete.
					 */
					if( ! in_array('value-optional', $param['flavour']) ) {

						$error_type = ( in_array('mandatory', $param['flavour']) ) ? 'fatal' : 'warning';
						$errors[ $error_type ][] = "--$key parameter needs a value";

						unset( $assoc_args[ $key ] );
					}
				}
			}
		}

		return $errors;
	}

	public function unknown_assoc( $assoc_args ) {
		$generic = $this->query_params( array(
			'type' => 'generic',
		) );

		if ( count( $generic ) )
			return array();

		$known_assoc = array();

		foreach ( $this->params as $param ) {
			if ( in_array( $param['type'], array( 'assoc', 'flag' ) ) )
				$known_assoc[] = $param['name'];
		}

		return array_diff( array_keys( $assoc_args ), $known_assoc );
	}

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

			// Check the token against the type patterns
			foreach ( self::$patterns as $regex => $desc ) {
				
				// Cleanup for the regex that expect no brackets
				$_token = str_replace( array('[', ']'), '', $token );
				if ( preg_match( $regex, $_token, $matches ) ) {
					$type = $desc['type']; // Add type of param
					$desc['flavour'] = self::get_flavour( $token ); // Add flavour of param
					$params[] = array_merge( $matches, $desc );

					break;
				}
			}

			if ( !$type ) {
				$params[] = array(
					'type' => 'unknown',
					'token' => $tmp
				);
			}
		}

		return $params;
	}

	/**
	 * @todo check if token is correct
	 **/
	private static function get_flavour( $token ){
		$flavour = false;

		//checking for full optionals [--a], [--a=<a>], [--a[=<a>]].
		if( substr($token, 0, 1) === '[' && substr($token, -1) === ']' ) {
			$flavour[] = 'optional';
			// Alright we know you are fully optional, remove the brackets and check for partial value-optionals.
			$token = substr($token, 1, -1); 
		} else {
			$flavour[] = 'mandatory';
		}

		// Matches for the remaining --a[=a], [--a[=<a>].
		// The later matches because we already removed the outer brackets
		if( preg_match('/[^[\]]+(?=])/', $token, $matches) ) {
			$flavour[] = 'value-optional';
		}

		if ( strpos($token, '...') !== false ) {
			$flavour[] = 'repeating';
		}

		return $flavour;
	}

	private static function init_patterns() {
		$p_name = '(?P<name>[a-z-_]+)';
		$p_value = '(?P<value>[a-zA-Z-|]+)';

		self::gen_patterns( 'positional', "<$p_value>");
		self::gen_patterns( 'generic',    "--<field>=<value>");
		self::gen_patterns( 'assoc',      "--$p_name=<$p_value>");
		self::gen_patterns( 'flag',       "--$p_name");
	}

	private static function gen_patterns( $type, $pattern ) {
		self::$patterns[ '/^' . $pattern . '$/' ] = array(
			'type' => $type,
		);
	}

	/**
	 * Filters a list of associatve arrays, based on a set of key => value arguments.
	 *
	 * @param array $args An array of key => value arguments to match against
	 * @param string $operator
	 * @return array
	 */
	private function query_params( $args, $operator = 'AND' ) {
		$operator = strtoupper( $operator );
		$count = count( $args );
		$filtered = array();

		foreach ( $this->params as $key => $to_match ) {
			$matched = 0;
			foreach ( $args as $m_key => $m_value ) {
				if ( array_key_exists( $m_key, $to_match ) && $m_value == $to_match[ $m_key ] )
					$matched++;
			}

			if (   ( 'AND' == $operator && $matched == $count )
				|| ( 'OR' == $operator && $matched > 0 )
				|| ( 'NOT' == $operator && 0 == $matched ) ) {
					$filtered[$key] = $to_match;
				}
		}

		return $filtered;
	}
}