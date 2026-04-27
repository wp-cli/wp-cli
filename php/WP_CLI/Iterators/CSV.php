<?php

namespace WP_CLI\Iterators;

use Countable;
use Iterator;
use ReturnTypeWillChange;
use SplFileObject;
use WP_CLI;

/**
 * Allows incrementally reading and parsing lines from a CSV file.
 *
 * @implements \Iterator<int, string[]|false>
 */
class CSV implements Countable, Iterator {

	const ROW_SIZE = 4096;

	/**
	 * The name of the CSV file.
	 *
	 * @var string
	 */
	private $filename;

	/**
	 * The file pointer resource.
	 *
	 * @var resource
	 */
	private $file_pointer;

	/**
	 * The CSV delimiter.
	 *
	 * @var string
	 */
	private $delimiter;

	/**
	 * The column names.
	 *
	 * @var array
	 */
	private $columns;

	/**
	 * The current index in the iterator.
	 *
	 * @var int
	 */
	private $current_index;

	/**
	 * The current element (row) or false.
	 *
	 * @var string[]|false
	 */
	private $current_element;

	/**
	 * Instantiate a new CSV iterator.
	 *
	 * @param string $filename  The name of the CSV file.
	 * @param string $delimiter The CSV delimiter.
	 */
	public function __construct( $filename, $delimiter = ',' ) {
		$this->filename = $filename;
		$file_pointer   = fopen( $filename, 'rb' );

		if ( ! $file_pointer ) {
			WP_CLI::error( sprintf( 'Could not open file: %s', $filename ) );
		}

		$this->file_pointer = $file_pointer;
		$this->delimiter    = $delimiter;
	}

	#[ReturnTypeWillChange]
	public function rewind() {
		rewind( $this->file_pointer );

		$this->columns = fgetcsv( $this->file_pointer, self::ROW_SIZE, $this->delimiter, '"', '\\' ) ?: [];

		$this->current_index = -1;
		$this->next();
	}

	#[ReturnTypeWillChange]
	public function current() {
		return $this->current_element;
	}

	#[ReturnTypeWillChange]
	public function key() {
		return $this->current_index;
	}

	#[ReturnTypeWillChange]
	public function next() {
		$this->current_element = false;

		while ( true ) {
			$row = fgetcsv( $this->file_pointer, self::ROW_SIZE, $this->delimiter, '"', '\\' );

			if ( false === $row ) {
				break;
			}

			$element = [];
			foreach ( $this->columns as $i => $key ) {
				if ( isset( $row[ $i ] ) ) {
					$element[ $key ] = $row[ $i ];
				}
			}

			if ( ! empty( $element ) ) {
				$this->current_element = $element;
				++$this->current_index;

				break;
			}
		}
	}

	/**
	 * @return int<0, max>
	 */
	#[ReturnTypeWillChange]
	public function count() {
		$file = new SplFileObject( $this->filename, 'r' );
		$file->seek( PHP_INT_MAX );
		return max( 0, $file->key() + 1 );
	}

	#[ReturnTypeWillChange]
	public function valid() {
		return is_array( $this->current_element );
	}
}
