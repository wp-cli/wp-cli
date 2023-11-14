<?php

namespace WP_CLI\Exception;

use OutOfBoundsException;

class NonExistentKeyException extends OutOfBoundsException {
	/** @var RecursiveDataStructureTraverser */
	protected $traverser;

	/**
	 * @param RecursiveDataStructureTraverser $traverser
	 */
	public function set_traverser( $traverser ) {
		$this->traverser = $traverser;
	}

	/**
	 * @return RecursiveDataStructureTraverser
	 */
	public function get_traverser() {
		return $this->traverser;
	}
}
