<?php

namespace WP_CLI;

final class NoOp {

	function __set( $key, $value ) {
		// do nothing
	}

	function __call( $method, $args ) {
		// do nothing
	}
}

