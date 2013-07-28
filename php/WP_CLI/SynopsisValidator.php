<?php

namespace WP_CLI;

/**
 * Checks if the list of parameters matches the specification defined in the synopsis.
 */
class SynopsisValidator {

	private $params = array();

	public function __construct( $synopsis ) {
		$this->params = SynopsisParser::parse( $synopsis );
	}

	public function enough_positionals( $args ) {
		$positional = $this->query_params( array(
			'type' => 'positional',
			'flavour' => array('mandatory')
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

						$error_type = ( in_array( array('mandatory', 'value-mandatory'), $param['flavour']) ) ? 'fatal' : 'warning';
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

