<?php

/**
 * Manage the object cache.
 *
 * Note: Persistent Object Caching is needed for these commands.
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
	 * If a value already exists for the key, the value isn't added.
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
		list( $key, $value ) = $args;

		$group = \WP_CLI\Utils\get_flag_value( $args, 2, '' );

		$expiration = \WP_CLI\Utils\get_flag_value( $args, 3, 0 );

		if ( ! wp_cache_add( $key, $value, $group, $expiration ) ) {
			WP_CLI::error( "Could not add object '$key' in group '$group'. Does it already exist?" );
		}

		WP_CLI::success( "Added object '$key' in group '$group'." );
	}

	/**
	 * Decrement a value in the object cache.
	 *
	 * ## OPTIONS
	 *
	 * <key>
	 * : Cache key.
	 *
	 * [<offset>]
	 * : The amount by which to decrement the item's value. Default is 1.
	 *
	 * [<group>]
	 * : Method for grouping data within the cache which allows the same key to be used across groups.
	 *
	 * ## EXAMPLES
	 *
	 *     # Decrease cache value.
	 *     $ wp cache decr my_key 2 my_group
	 *     48
	 */
	public function decr( $args, $assoc_args ) {
		$key = $args[0];

		$offset = \WP_CLI\Utils\get_flag_value( $args, 1, 1 );

		$group = \WP_CLI\Utils\get_flag_value( $args, 2, '' );

		$value = wp_cache_decr( $key, $offset, $group );

		if ( false === $value ) {
			WP_CLI::error( 'The value was not decremented.' );
		}

		WP_CLI::print_value( $value, $assoc_args );
	}

	/**
	 * Remove a value from the object cache.
	 *
	 * ## OPTIONS
	 *
	 * <key>
	 * : Cache key.
	 *
	 * [<group>]
	 * : Method for grouping data within the cache which allows the same key to be used across groups.
	 *
	 * ## EXAMPLES
	 *
	 *     # Delete cache.
	 *     $ wp cache delete my_key my_group
	 *     Success: Object deleted.
	 */
	public function delete( $args, $assoc_args ) {
		$key = $args[0];

		$group = \WP_CLI\Utils\get_flag_value( $args, 1, '' );

		$result = wp_cache_delete( $key, $group );

		if ( false === $result ) {
			WP_CLI::error( 'The object was not deleted.' );
		}

		WP_CLI::success( 'Object deleted.' );
	}

	/**
	 * Flush the object cache.
	 *
	 * For sites using a persistent object cache, because WordPress Multisite simply adds a blog id
	 * to the cache key, flushing cache is typically a global operation.
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
	 * ## OPTIONS
	 *
	 * <key>
	 * : Cache key.
	 *
	 * [<group>]
	 * : Method for grouping data within the cache which allows the same key to be used across groups.
	 *
	 * ## EXAMPLES
	 *
	 *     # Get cache.
	 *     $ wp cache get my_key my_group
	 *     my_value
	 */
	public function get( $args, $assoc_args ) {
		$key = $args[0];

		$group = \WP_CLI\Utils\get_flag_value( $args, 1, '' );

		$value = wp_cache_get( $key, $group );

		if ( false === $value ) {
			WP_CLI::error( "Object with key '$key' and group '$group' not found." );
		}

		WP_CLI::print_value( $value, $assoc_args );
	}

	/**
	 * Increment a value in the object cache.
	 *
	 * ## OPTIONS
	 *
	 * <key>
	 * : Cache key.
	 *
	 * [<offset>]
	 * : The amount by which to increment the item's value. Default is 1.
	 *
	 * [<group>]
	 * : Method for grouping data within the cache which allows the same key to be used across groups.
	 *
	 * ## EXAMPLES
	 *
	 *     # Increase cache value.
	 *     $ wp cache incr my_key 2 my_group
	 *     50
	 */
	public function incr( $args, $assoc_args ) {
		$key = $args[0];

		$offset = \WP_CLI\Utils\get_flag_value( $args, 1, 1 );

		$group = \WP_CLI\Utils\get_flag_value( $args, 2, '' );

		$value = wp_cache_incr( $key, $offset, $group );

		if ( false === $value ) {
			WP_CLI::error( 'The value was not incremented.' );
		}

		WP_CLI::print_value( $value, $assoc_args );
	}

	/**
	 * Replace a value in the object cache, if the value already exists.
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
		list( $key, $value ) = $args;

		$group = \WP_CLI\Utils\get_flag_value( $args, 2, '' );

		$expiration = \WP_CLI\Utils\get_flag_value( $args, 3, 0 );

		$result = wp_cache_replace( $key, $value, $group, $expiration );

		if ( false === $result ) {
			WP_CLI::error( "Could not replace object '$key' in group '$group'. Does it already exist?" );
		}

		WP_CLI::success( "Replaced object '$key' in group '$group'." );
	}

	/**
	 * Set a value to the object cache, regardless of whether it already exists.
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
		list( $key, $value ) = $args;

		$group = \WP_CLI\Utils\get_flag_value( $args, 2, '' );

		$expiration = \WP_CLI\Utils\get_flag_value( $args, 3, 0 );

		$result = wp_cache_set( $key, $value, $group, $expiration );

		if ( false === $result ) {
			WP_CLI::error( "Could not add object '$key' in group '$group'." );
		}

		WP_CLI::success( "Set object '$key' in group '$group'." );
	}

	/**
	 * Attempts to determine which object cache is being used.
	 *
	 * Note that the guesses made by this function are based on the WP_Object_Cache classes
	 * that define the 3rd party object cache extension. Changes to those classes could render
	 * problems with this function's ability to determine which object cache is being used.
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
