<?php

/**
 * Manages the internal WP-CLI cache,.
 *
 * ## EXAMPLES
 *
 *     # Remove all cached files.
 *     $ wp cli cache clear
 *     Success: Cache cleared.
 *
 *     # Remove all cached files except for the newest version of each one.
 *     $ wp cli cache prune
 *     Success: Cache pruned.
 *
 * @when before_wp_load
 */
class CLI_Cache_Command extends WP_CLI_Command {

	/**
	 * Clears the internal cache.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp cli cache clear
	 *     Success: Cache cleared.
	 *
	 * @subcommand clear
	 */
	public function cache_clear() {
		$cache = WP_CLI::get_cache();

		if ( ! $cache->is_enabled() ) {
			WP_CLI::error( 'Cache directory does not exist.' );
		}

		$cache->clear();

		WP_CLI::success( 'Cache cleared.' );
	}

	/**
	 * Prunes the internal cache.
	 *
	 * Removes all cached files except for the newest version of each one.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp cli cache prune
	 *     Success: Cache pruned.
	 *
	 * @subcommand prune
	 */
	public function cache_prune() {
		$cache = WP_CLI::get_cache();

		if ( ! $cache->is_enabled() ) {
			WP_CLI::error( 'Cache directory does not exist.' );
		}

		$cache->prune();

		WP_CLI::success( 'Cache pruned.' );
	}
}
