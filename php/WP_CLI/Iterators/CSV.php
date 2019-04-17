<?php

namespace WP_CLI\Iterators;

/**
 * Allows incrementally reading and parsing lines from a CSV file.
 */
class CSV implements \Iterator {

	const ROW_SIZE = 4096;

	private $file_pointer;

	private $delimiter;
	private $columns;

	private $current_index;
	private $current_element;

	public function __construct( $filename, $delimiter = ',' ) {
		$this->file_pointer = fopen( $filename, 'rb' );
		if ( ! $this->file_pointer ) {
			\WP_CLI::error( sprintf( 'Could not open file: %s', $filename ) );
		}

		$this->delimiter = $delimiter;
	}

	public function rewind() {
		rewind( $this->file_pointer );

		$this->columns = fgetcsv( $this->file_pointer, self::ROW_SIZE, $this->delimiter );

		$this->current_index = -1;
		$this->next();
	}

	public function current() {
		return $this->current_element;
	}

	public function key() {
		return $this->current_index;
	}

	public function next() {
		$this->current_element = false;

		while ( true ) {
			$str = fgets( $this->file_pointer );

			if ( false === $str ) {
				break;
			}

			$row = str_getcsv( $str, $this->delimiter );

			$element = array();
			foreach ( $this->columns as $i => $key ) {
				if ( isset( $row[ $i ] ) ) {
					$element[ $key ] = $row[ $i ];
				}
			}

			if ( ! empty( $element ) ) {
				$this->current_element = $element;
				$this->current_index++;

				break;
			}
		}
	}

	public function valid() {
		return is_array( $this->current_element );
	}
}

