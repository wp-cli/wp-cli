<?php

/**
 * Manage the object cache.
 *
 * Use a persistent object cache drop-in to persist cache values between requests.
 *
 * ## EXAMPLES
 *
 *     # Set cache.
 *     $ wp cache set my_key my_value my_group 300
 *     Success: Set object 'my_key' in group 'my_group'.
 *
 *     # Get cache.
 *     $ wp cache get my_key my_group
 *     my_value
 *
 * @package wp-cli
 */
class Cache_Command extends WP_CLI_Command {

	/**
	 * Add a value to the object cache.
	 *
	 * Errors if a value already exists for the key, which means the value can't
	 * be added.
	 *
	 * ## OPTIONS
	 *
	 * <key>
	 * : Cache key.
	 *
	 * <value>
	 * : Value to add to the key.
	 *
	 * [<group>]
	 * : Method for grouping data within the cache which allows the same key to be used across groups.
	 * ---
	 * default: default
	 * ---
	 *
	 * [<expiration>]
	 * : Define how long to keep the value, in seconds. `0` means as long as possible.
	 * ---
	 * default: 0
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     # Add cache.
	 *     $ wp cache add my_key my_group my_value 300
	 *     Success: Added object 'my_key' in group 'my_value'.
	 */
	public function add( $args, $assoc_args ) {
		list( $key, $value, $group, $expiration ) = $args;

		if ( ! wp_cache_add( $key, $value, $group, $expiration ) ) {
			WP_CLI::error( "Could not add object '$key' in group '$group'. Does it already exist?" );
		}

		WP_CLI::success( "Added object '$key' in group '$group'." );
	}

	/**
	 * Decrement a value in the object cache.
	 *
	 * Errors if the value can't be decremented.
	 *
	 * ## OPTIONS
	 *
	 * <key>
	 * : Cache key.
	 *
	 * [<offset>]
	 * : The amount by which to decrement the item's value.
	 * ---
	 * default: 1
	 * ---
	 *
	 * [<group>]
	 * : Method for grouping data within the cache which allows the same key to be used across groups.
	 * ---
	 * default: default
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     # Decrease cache value.
	 *     $ wp cache decr my_key 2 my_group
	 *     48
	 */
	public function decr( $args, $assoc_args ) {
		list( $key, $offset, $group ) = $args;
		$value = wp_cache_decr( $key, $offset, $group );

		if ( false === $value ) {
			WP_CLI::error( 'The value was not decremented.' );
		}

		WP_CLI::print_value( $value, $assoc_args );
	}

	/**
	 * Remove a value from the object cache.
	 *
	 * Errors if the value can't be deleted.
	 *
	 * ## OPTIONS
	 *
	 * <key>
	 * : Cache key.
	 *
	 * [<group>]
	 * : Method for grouping data within the cache which allows the same key to be used across groups.
	 * ---
	 * default: default
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     # Delete cache.
	 *     $ wp cache delete my_key my_group
	 *     Success: Object deleted.
	 */
	public function delete( $args, $assoc_args ) {
		list( $key, $group ) = $args;
		$result = wp_cache_delete( $key, $group );

		if ( false === $result ) {
			WP_CLI::error( 'The object was not deleted.' );
		}

		WP_CLI::success( 'Object deleted.' );
	}

	/**
	 * Flush the object cache.
	 *
	 * For WordPress multisite instances using a persistent object cache,
	 * flushing the object cache will typically flush the cache for all sites.
	 * Beware of the performance impact when flushing the object cache in
	 * production.
	 *
	 * Errors if the object cache can't be flushed.
	 *
	 * ## EXAMPLES
	 *
	 *     # Flush cache.
	 *     $ wp cache flush
	 *     Success: The cache was flushed.
	 */
	public function flush( $args, $assoc_args ) {
		$value = wp_cache_flush();

		if ( false === $value ) {
			WP_CLI::error( 'The object cache could not be flushed.' );
		}

		WP_CLI::success( 'The cache was flushed.' );
	}

	/**
	 * Get a value from the object cache.
	 *
	 * Errors if the value doesn't exist.
	 *
	 * ## OPTIONS
	 *
	 * <key>
	 * : Cache key.
	 *
	 * [<group>]
	 * : Method for grouping data within the cache which allows the same key to be used across groups.
	 * ---
	 * default: default
	 * ___
	 *
	 * ## EXAMPLES
	 *
	 *     # Get cache.
	 *     $ wp cache get my_key my_group
	 *     my_value
	 */
	public function get( $args, $assoc_args ) {
		list( $key, $group ) = $args;
		$value = wp_cache_get( $key, $group );

		if ( false === $value ) {
			WP_CLI::error( "Object with key '$key' and group '$group' not found." );
		}

		WP_CLI::print_value( $value, $assoc_args );
	}

	/**
	 * Increment a value in the object cache.
	 *
	 * Errors if the value can't be incremented.
	 *
	 * ## OPTIONS
	 *
	 * <key>
	 * : Cache key.
	 *
	 * [<offset>]
	 * : The amount by which to increment the item's value.
	 * ---
	 * default: 1
	 * ---
	 *
	 * [<group>]
	 * : Method for grouping data within the cache which allows the same key to be used across groups.
	 * ---
	 * default: default
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     # Increase cache value.
	 *     $ wp cache incr my_key 2 my_group
	 *     50
	 */
	public function incr( $args, $assoc_args ) {
		list( $key, $offset, $group ) = $args;
		$value = wp_cache_incr( $key, $offset, $group );

		if ( false === $value ) {
			WP_CLI::error( 'The value was not incremented.' );
		}

		WP_CLI::print_value( $value, $assoc_args );
	}

	/**
	 * Replace a value in the object cache, if the value already exists.
	 *
	 * Errors if the value can't be replaced.
	 *
	 * ## OPTIONS
	 *
	 * <key>
	 * : Cache key.
	 *
	 * <value>
	 * : Value to replace.
	 *
	 * [<group>]
	 * : Method for grouping data within the cache which allows the same key to be used across groups.
	 * ---
	 * default: default
	 * ---
	 *
	 * [<expiration>]
	 * : Define how long to keep the value, in seconds. `0` means as long as possible.
	 * ---
	 * default: 0
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     # Replace cache.
	 *     $ wp cache replace my_key new_value my_group
	 *     Success: Replaced object 'my_key' in group 'my_group'.
	 */
	public function replace( $args, $assoc_args ) {
		list( $key, $value, $group, $expiration ) = $args;
		$result = wp_cache_replace( $key, $value, $group, $expiration );

		if ( false === $result ) {
			WP_CLI::error( "Could not replace object '$key' in group '$group'. Does it not exist?" );
		}

		WP_CLI::success( "Replaced object '$key' in group '$group'." );
	}

	/**
	 * Set a value to the object cache, regardless of whether it already exists.
	 *
	 * Errors if the value can't be set.
	 *
	 * ## OPTIONS
	 *
	 * <key>
	 * : Cache key.
	 *
	 * <value>
	 * : Value to set on the key.
	 *
	 * [<group>]
	 * : Method for grouping data within the cache which allows the same key to be used across groups.
	 * ---
	 * default: default
	 * ---
	 *
	 * [<expiration>]
	 * : Define how long to keep the value, in seconds. `0` means as long as possible.
	 * ---
	 * default: 0
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     # Set cache.
	 *     $ wp cache set my_key my_value my_group 300
	 *     Success: Set object 'my_key' in group 'my_group'.
	 */
	public function set( $args, $assoc_args ) {
		list( $key, $value, $group, $expiration ) = $args;
		$result = wp_cache_set( $key, $value, $group, $expiration );

		if ( false === $result ) {
			WP_CLI::error( "Could not add object '$key' in group '$group'." );
		}

		WP_CLI::success( "Set object '$key' in group '$group'." );
	}

	/**
	 * Attempts to determine which object cache is being used.
	 *
	 * Note that the guesses made by this function are based on the
	 * WP_Object_Cache classes that define the 3rd party object cache extension.
	 * Changes to those classes could render problems with this function's
	 * ability to determine which object cache is being used.
	 *
	 * ## EXAMPLES
	 *
	 *     # Check cache type.
	 *     $ wp cache type
	 *     Default
	 */
	public function type( $args, $assoc_args ) {
		$message = WP_CLI\Utils\wp_get_cache_type();
		WP_CLI::line( $message );
	}

}

WP_CLI::add_command( 'cache', 'Cache_Command' );
