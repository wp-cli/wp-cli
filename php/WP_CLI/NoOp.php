<?php

namespace WP_CLI;

/**
 * Escape route for not doing anything.
 *
 * @method void display(bool $finish = false)
 * @method void tick(int $increment = 1, ?string $msg = null)
 * @method void finish()
 */
final class NoOp {

	/**
	 * @param string $key
	 * @param mixed $value
	 * @return void
	 */
	public function __set( $key, $value ) {
		// do nothing
	}

	/**
	 * @param string $method
	 * @param array<mixed> $args
	 * @return void
	 */
	public function __call( $method, $args ) {
		// do nothing
	}
}
