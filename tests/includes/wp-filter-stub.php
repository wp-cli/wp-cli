<?php
/**
 * Minimal stand-in for WordPress's add_filter(), for classes that register
 * hooks on construction and are unit-tested without WordPress loaded.
 */

if ( ! function_exists( 'add_filter' ) ) {
	/**
	 * @param string   $hook_name
	 * @param callable $callback
	 * @param int      $priority
	 * @param int      $accepted_args
	 * @return true
	 */
	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
	function add_filter( $hook_name, $callback, $priority = 10, $accepted_args = 1 ) {
		static $registered = [];
		$registered[]      = [ $hook_name, $callback, $priority, $accepted_args ];
		return true;
	}
}
