<?php

namespace WP_CLI\Iterators;

use IteratorIterator;

/**
 * Applies one or more callbacks to an item before returning it.
 *
 * @template TKey of int|string
 * @template TValue
 * @template TRet
 * @extends IteratorIterator<TKey, TRet, \Iterator<TKey, TValue>>
 */
class Transform extends IteratorIterator {

	/**
	 * List of transformer callbacks.
	 *
	 * @var array<callable(mixed): mixed>
	 */
	private $transformers = [];

	/**
	 * Add a transformer callback.
	 *
	 * @param callable(mixed): mixed $fn
	 * @return void
	 */
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
