<?php

/**
 * This file contains fallback functions that might have been disabled but are required nevertheless.
 */

if ( PHP_MAJOR_VERSION >= 8 && ! function_exists( 'ini_set' ) ) {
	/**
	 * @param string $option
	 * @param string|int|float|bool|null $value
	 * @return false
	 */
	function ini_set( $option, $value ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- This is a stub only.
		return false;
	}
}
