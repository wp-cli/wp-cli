<?php

namespace WP_CLI\Iterators;

/**
 * Allows incrementally reading and parsing lines from a CSV file.
 */
class CSV implements \Iterator {

	const ROW_SIZE = 4096;

	private $filePointer;

	private $delimiter;
	private $columns;

	private $currentIndex;
	private $currentElement;

	public function __construct( $filename, $delimiter = ',' ) {
		$this->filePointer = fopen( $filename, 'r' );
		if ( !$this->filePointer ) {
			\WP_CLI::error( sprintf( 'Could not open file: %s', $filename ) );
		}

		$this->delimiter = $delimiter;
	}

	public function rewind() {
		rewind( $this->filePointer );

		$this->columns = fgetcsv( $this->filePointer, self::ROW_SIZE, $this->delimiter );

		$this->currentIndex = -1;
		$this->next();
	}

	public function current() {
		return $this->currentElement;
	}

	public function key() {
		return $this->currentIndex;
	}

	public function next() {
		$this->currentElement = false;

		while ( true ) {
			$str = fgets( $this->filePointer );

			if ( false === $str )
				break;

			$row = str_getcsv( $str, $this->delimiter );

			$element = array();
			foreach ( $this->columns as $i => $key ) {
				if ( isset( $row[ $i ] ) )
					$element[ $key ] = $row[ $i ];
			}

			if ( !empty( $element ) ) {
				$this->currentElement = $element;
				$this->currentIndex++;

				break;
			}
		}
	}

	public function valid() {
		return is_array( $this->currentElement );
	}
}

