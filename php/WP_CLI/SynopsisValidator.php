<?php

namespace WP_CLI;

/**
 * Checks if the list of parameters matches the specification defined in the synopsis.
 */
class SynopsisValidator {

	private $spec = array();

	public function __construct( $synopsis ) {
		$this->spec = SynopsisParser::parse( $synopsis );
	}

	public function get_unknown() {
		return array_column( $this->query_spec( array(
			'type' => 'unknown',
		) ), 'token' );
	}

	public function enough_positionals( $args ) {
		$positional = $this->query_spec( array(
			'type' => 'positional',
			'optional' => false,
		) );

		return count( $args ) >= count( $positional );
	}

	public function unknown_positionals( $args ) {
		$positional_repeating = $this->query_spec( array(
			'type' => 'positional',
			'repeating' => true,
		) );

		if ( !empty( $positional_repeating ) )
			return array();

		$positional = $this->query_spec( array(
			'type' => 'positional',
			'repeating' => false,
		) );

		return array_slice( $args, count( $positional ) );
	}

	// Checks that all required keys are present and that they have values.
	public function validate_assoc( $assoc_args ) {
		$assoc_spec = $this->query_spec( array(
			'type' => 'assoc',
		) );

		$errors = array(
			'fatal' => array(),
			'warning' => array()
		);

		$to_unset = array();

		foreach ( $assoc_spec as $param ) {
			$key = $param['name'];

			if ( !isset( $assoc_args[ $key ] ) ) {
				if ( !$param['optional'] ) {
					$errors['fatal'][] = "missing --$key parameter";
				}
			} else {
				if ( true === $assoc_args[ $key ] && !$param['value']['optional'] ) {
					$error_type = ( !$param['optional'] ) ? 'fatal' : 'warning';
					$errors[ $error_type ][] = "--$key parameter needs a value";

					$to_unset[] = $key;
				}
			}
		}

		return array( $errors, $to_unset );
	}

	public function unknown_assoc( $assoc_args ) {
		$generic = $this->query_spec( array(
			'type' => 'generic',
		) );

		if ( count( $generic ) )
			return array();

		$known_assoc = array();

		foreach ( $this->spec as $param ) {
			if ( in_array( $param['type'], array( 'assoc', 'flag' ) ) )
				$known_assoc[] = $param['name'];
		}

		return array_diff( array_keys( $assoc_args ), $known_assoc );
	}

	/**
	 * Filters a list of associative arrays, based on a set of key => value arguments.
	 *
	 * @param array $args An array of key => value arguments to match against
	 * @param string $operator
	 * @return array
	 */
	private function query_spec( $args, $operator = 'AND' ) {
		$operator = strtoupper( $operator );
		$count = count( $args );
		$filtered = array();

		foreach ( $this->spec as $key => $to_match ) {
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

