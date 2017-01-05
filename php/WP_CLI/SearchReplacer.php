<?php

namespace WP_CLI;

class SearchReplacer {

	private $from, $to;
	private $recurse_objects;
	private $max_recursion;

	/**
	 * @param string  $from            String we're looking to replace.
	 * @param string  $to              What we want it to be replaced with.
	 * @param bool    $recurse_objects Should objects be recursively replaced?
	 */
	function __construct( $from, $to, $recurse_objects = false, $regex = false, $regex_flags = '' ) {
		$this->from = $from;
		$this->to = $to;
		$this->recurse_objects = $recurse_objects;
		$this->regex = $regex;
		$this->regex_flags = $regex_flags;

		// Get the XDebug nesting level. Will be zero (no limit) if no value is set
		$this->max_recursion = intval( ini_get( 'xdebug.max_nesting_level' ) );
	}

	/**
	 * Take a serialised array and unserialise it replacing elements as needed and
	 * unserialising any subordinate arrays and performing the replace on those too.
	 * Ignores any serialized objects unless $recurse_objects is set to true.
	 *
	 * @param array|string $data            The data to operate on.
	 * @param bool         $serialised      Does the value of $data need to be unserialized?
	 *
	 * @return array       The original array with all elements replaced as needed.
	 */
	function run( $data, $serialised = false ) {
		return $this->_run( $data, $serialised );
	}

	/**
	 * @param int          $recursion_level Current recursion depth within the original data.
	 * @param array        $visited_data    Data that has been seen in previous recursion iterations.
	 */
	private function _run( $data, $serialised, $recursion_level = 0, &$visited_data = array() ) {

		// some unseriliased data cannot be re-serialised eg. SimpleXMLElements
		try {

			if ( $this->recurse_objects ) {

				// If we've reached the maximum recursion level, short circuit
				if ( $this->max_recursion != 0 && $recursion_level >= $this->max_recursion ) {
					return $data;
				}

				if ( is_array( $data ) || is_object( $data ) ) {
					// If we've seen this exact object or array before, short circuit
					// Avoid infinite loops when there's a cycle
					if ( in_array( $data, $visited_data, true ) ) {
						if ( is_object( $data ) ) {
							return $data;
						}
						// Only short-circuit when the array is passed by reference
						if ( is_array( $data ) ) {
							$k = array_search( $data, $visited_data );
							$existing_data = $visited_data[ $k ];
							if ( self::check_arrays_referenced( $data, $existing_data ) ) {
								return $data;
							}
							// Check to see if any child values are going to cause
							// recursion. If so, assume $data is storing a reference
							// to itself
							foreach( $data as $k => $v ) {
								if ( $v === $data ) {
									ob_start();
									var_dump( $v );
									$export = ob_get_clean();
									if ( stripos( $export, '*RECURSION*' ) ) {
										return $data;
									}
								}
							}
						}
					}
					// Add this data to the list of
					$visited_data[] = $data;
				}
			}

			if ( is_string( $data ) && ( $unserialized = @unserialize( $data ) ) !== false ) {
				$data = $this->_run( $unserialized, true, $recursion_level + 1 );
			}

			elseif ( is_array( $data ) ) {
				$keys = array_keys( $data );
				foreach ( $keys as $key ) {
					$data[ $key ]= $this->_run( $data[$key], false, $recursion_level + 1, $visited_data );
				}
			}

			elseif ( $this->recurse_objects && is_object( $data ) ) {
				foreach ( $data as $key => $value ) {
					$data->$key = $this->_run( $value, false, $recursion_level + 1, $visited_data );
				}
			}

			else if ( is_string( $data ) ) {
				if ( $this->regex ) {
					$data = preg_replace( "/$this->from/$this->regex_flags", $this->to, $data );
				} else {
					$data = str_replace( $this->from, $this->to, $data );
				}
			}

			if ( $serialised )
				return serialize( $data );

		} catch( Exception $error ) {

		}

		return $data;
	}

	/**
	 * Check if two arrays are a reference to one another
	 *
	 * @param $var1 array
	 * @param $var2 array
	 * @return boolean
	 */
	private static function check_arrays_referenced( $var1, $var2 ) {
		$same = false;
		if ( ! is_array( $var1 )
			|| ! is_array( $var2 )
			|| $var1 !== $var2 ) {
			return $same;
		}
		// Detect when an array is a reference
		// by assigning a value to a key that doesn't yet
		// exist and seeing if the original array is modified
		// Unfortunately, there isn't a better way to detect
		// references in PHP.
		do {
			$k = uniqid( 'is_ref_', true );
		} while( array_key_exists( $k, $var1 ) );
		$test_data = uniqid( 'is_ref_data_', true );
		$var1[ $k ] = &$test_data;
		// This looks like a reference, so short circuit early
		if ( array_key_exists( $k, $var2 )
			&& $var2[ $k ] === $var1[ $k ] ) {
			$same = true;
		}
		// Wasn't a reference, so let's clean the original data
		unset( $var1[ $k ] );
		return $same;
	}

}

