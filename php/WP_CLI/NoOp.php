<?php

namespace WP_CLI;

/**
 * Escape route for not doing anything.
 */
final class NoOp {

	public function __set( $key, $value ) {
		// do nothing
	}

	public function __call( $method, $args ) {
		// do nothing
	}
}

