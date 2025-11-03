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

	public function __set( $key, $value ) {
		// do nothing
	}

	public function __call( $method, $args ) {
		// do nothing
	}
}
