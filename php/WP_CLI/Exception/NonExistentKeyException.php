<?php

namespace WP_CLI\Exception;

use OutOfBoundsException;
use WP_CLI\Traverser\RecursiveDataStructureTraverser;

/**
 * @template T
 */
class NonExistentKeyException extends OutOfBoundsException {
	/** @var RecursiveDataStructureTraverser<T> */
	protected $traverser;

	/**
	 * @param RecursiveDataStructureTraverser<T> $traverser
	 */
	public function set_traverser( $traverser ) {
		$this->traverser = $traverser;
	}

	/**
	 * @return RecursiveDataStructureTraverser<T>
	 */
	public function get_traverser() {
		return $this->traverser;
	}
}
