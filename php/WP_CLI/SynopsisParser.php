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
				if ( 'mandatory' == $param['flavour'] ) {
					$errors['fatal'][] = "missing --$key parameter";
				}
			} else {
				if ( true === $assoc_args[ $key ] ) {
					$error_type = ( 'mandatory' == $param['flavour'] ) ? 'fatal' : 'warning';
					$errors[ $error_type ][] = "--$key parameter needs a value";

					unset( $assoc_args[ $key ] );
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
		$p_value = '(?P<value>[a-z-|]+)';

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

