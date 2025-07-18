<?php

namespace WP_CLI\Iterators;

use IteratorIterator;

/**
 * Applies one or more callbacks to an item before returning it.
 *
 * @phpstan-extends IteratorIterator<int, mixed, \Iterator>
 */
class Transform extends IteratorIterator {

	private $transformers = [];

	public function add_transform( $fn ) {
		$this->transformers[] = $fn;
	}

	#[\ReturnTypeWillChange]
	public function current() {
		$value = parent::current();

		foreach ( $this->transformers as $fn ) {
			$value = call_user_func( $fn, $value );
		}

		return $value;
	}
}
