<?php

namespace WP_CLI;

class CSVIterator implements \Iterator {

	const ROW_SIZE = 4096;

	private $filePointer;

	private $delimiter;
	private $columns;

	private $currentIndex;
	private $currentElement;

	public function __construct( $file, $delimiter = ',' ) {
		$this->filePointer = fopen( $file, 'r' );
		$this->delimiter = $delimiter;
	}

	private function read_line() {
		return fgetcsv( $this->filePointer, self::ROW_SIZE, $this->delimiter );
	}

	public function rewind() {
		rewind( $this->filePointer );

		$this->columns = $this->read_line();

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
		$row = $this->read_line();

		if ( !is_array( $row ) ) {
			$this->currentElement = false;
		} else {
			$this->currentElement = array_combine( $this->columns, $row );
			$this->currentIndex++;
		}
	}

	public function valid() {
		return is_array( $this->currentElement );
	}
}

