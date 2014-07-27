<?php

namespace WP_CLI;

/**
 * Escape route for not doing anything.
 */
final class NoOp {

	function __set( $key, $value ) {
		// do nothing
	}

	function __call( $method, $args ) {
		// do nothing
	}
}

