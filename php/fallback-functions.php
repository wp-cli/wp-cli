<?php

/**
 * This file contains fallback functions that might have been disabled but are required nevertheless.
 */

if ( ! function_exists( 'ini_set' ) ) {
	function ini_set() {}
}
