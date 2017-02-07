<?php

namespace WP_CLI\Iterators;

/**
 * Aplies one or more callbacks to an item before returning it.
 */
class Transform extends \IteratorIterator {

	private $transformers = array();

	public function add_transform( $fn ) {
		$this->transformers[] = $fn;
	}

	public function current() {
		$value = parent::current();

		foreach ( $this->transformers as $fn ) {
			$value = call_user_func( $fn, $value );
		}

		return $value;
	}
}

