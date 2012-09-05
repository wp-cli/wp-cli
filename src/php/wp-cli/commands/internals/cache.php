<?php

WP_CLI::add_command( 'cache', 'Cache_Command' );

/**
 * Implement cache command
 *
 * @package wp-cli
 * @subpackage commands/internals
 */
class Cache_Command extends WP_CLI_Command {

	/**
	 * Add a value to the object cache.
	 *
	 * @uses	wp_cache_add
	 *
	 * @param 	array 			$args				Function arguments.
	 * @param 	array 			$assoc_args			Function arguments with parameter key.
	 * @return	void
	 */
	public function add( $args, $assoc_args ) {
		if ( count( $assoc_args ) + count( $args ) < 2 ) {
			WP_CLI::line( 'usage: wp cache add <key> <value> [group] [expiration]' );
			exit;
		}

		list( $key, $value ) = $args;

		$group = ( isset( $args[2] ) ) ? $args[2] : '';

		$expiration = ( isset( $args[3] ) ) ? $args[3] : 0;

		if ( ! wp_cache_add( $key, $value, $group, $expiration ) ) {
			WP_CLI::error( "Could not add object '$key' in group '$group'. Does it already exist?" );
			exit;
		}

		WP_CLI::success( "Added object '$key' in group '$group'." );
	}

	/**
	 * Add global cache groups.
	 *
	 * @uses	wp_cache_add_global_groups
	 *
	 * @param 	array 			$args				Function arguments.
	 * @param 	array 			$assoc_args			Function arguments with parameter key.
	 * @return	void
	 */
	public function add_global_groups( $args, $assoc_args ) {
		if ( empty( $args ) ) {
			WP_CLI::line( 'usage: wp cache add_global_groups <group>' );
			exit;
		}

		$group = $args[0];

		wp_cache_add_global_groups( $group );
	}

	/**
	 * Adds a non-persistent group to the list.
	 *
	 * @uses	wp_cache_add_non_persistent_groups
	 *
	 * @param 	array 			$args				Function arguments.
	 * @param 	array 			$assoc_args			Function arguments with parameter key.
	 * @return	void
	 */
	public function add_non_persistent_groups( $args, $assoc_args ) {
		if ( empty( $args ) ) {
			WP_CLI::line( 'usage: wp cache add_non_persistent_groups <group>' );
			exit;
		}

		$group = $args[0];

		wp_cache_add_non_persistent_groups( $group );
	}

	/**
	 * Decrement a value in the object cache.
	 *
	 * @uses	wp_cache_decr
	 *
	 * @param 	array 			$args				Function arguments.
	 * @param 	array 			$assoc_args			Function arguments with parameter key.
	 * @return	void
	 */
	public function decr( $args, $assoc_args ) {
		if ( empty( $args ) ) {
			WP_CLI::line( 'usage: wp cache decr <key> [offset] [group]' );
			exit;
		}

		$key = $args[0];

		$offset = ( isset( $args[1] ) ) ? $args[1] : 1;

		$group = ( isset( $args[2] ) ) ? $args[2] : '';

		$value = wp_cache_decr( $key, $offset, $group );

		if ( false === $value ) {
			WP_CLI::error( 'The value was not decremented.' );
			exit;
		}

		WP_CLI::print_value( $value, $assoc_args );
	}

	/**
	 * Remove a value from the object cache.
	 *
	 * @uses	wp_cache_delete
	 *
	 * @param 	array 			$args				Function arguments.
	 * @param 	array 			$assoc_args			Function arguments with parameter key.
	 * @return	void
	 */
	public function delete( $args, $assoc_args ) {
		if ( empty( $args ) ) {
			WP_CLI::line( 'usage: wp cache delete <key> [group]' );
			exit;
		}

		$key = $args[0];

		$group = ( isset( $args[1] ) ) ? $args[1] : '';

		$result = wp_cache_delete( $key, $group );

		if ( false === $result ) {
			WP_CLI::error( 'The object was not deleted.' );
			exit;
		}

		WP_CLI::success( 'Object deleted.' );
	}

	/**
	 * Flush the object cache.
	 *
	 * @uses	wp_cache_flush
	 *
	 * @param 	array 			$args				Function arguments.
	 * @param 	array 			$assoc_args			Function arguments with parameter key.
	 * @return	void
	 */
	public function flush( $args, $assoc_args ) {
		$value = wp_cache_flush();

		if ( false === $value ) {
			WP_CLI::error( 'The object cache could not be flushed.' );
			exit;
		}

		WP_CLI::success( 'The cache was flushed.' );
	}

	/**
	 * Get a value from the object cache.
	 *
	 * @uses	wp_cache_get
	 *
	 * @param 	array 			$args				Function arguments.
	 * @param 	array 			$assoc_args			Function arguments with parameter key.
	 * @return	void
	 */
	public function get( $args, $assoc_args ) {
		if ( empty( $args ) ) {
			WP_CLI::line( 'usage: wp cache get <key> [group]' );
			exit;
		}

		$key = $args[0];

		$group = ( isset( $args[1] ) ) ? $args[1] : '';

		$value = wp_cache_get( $key, $group );

		if ( false === $value ) {
			WP_CLI::error( "Object with key '$key' and group '$group' not found." );
			exit;
		}

		WP_CLI::print_value( $value, $assoc_args );
	}

	/**
	 * Increment a value in the object cache.
	 *
	 * @uses	wp_cache_incr
	 *
	 * @param 	array 			$args				Function arguments.
	 * @param 	array 			$assoc_args			Function arguments with parameter key.
	 * @return	void
	 */
	public function incr( $args, $assoc_args ) {
		if ( empty( $args ) ) {
			WP_CLI::line( 'usage: wp cache incr <key> [offset] [group]' );
			exit;
		}

		$key = $args[0];

		$offset = ( isset( $args[1] ) ) ? $args[1] : 1;

		$group = ( isset( $args[2] ) ) ? $args[2] : '';

		$value = wp_cache_incr( $key, $offset, $group );

		if ( false === $value ) {
			WP_CLI::error( 'The value was not incremented.' );
			exit;
		}

		WP_CLI::print_value( $value, $assoc_args );
	}

	/**
	 * Replace an existing value in the object cache.
	 *
	 * @uses	wp_cache_replace
	 *
	 * @param 	array 			$args				Function arguments.
	 * @param 	array 			$assoc_args			Function arguments with parameter key.
	 * @return	void
	 */
	public function replace( $args, $assoc_args ) {
		if ( count( $assoc_args ) + count( $args ) < 2 ) {
			WP_CLI::line( 'usage: wp cache replace <key> <value> [group] [expiration]' );
			exit;
		}

		list( $key, $value ) = $args;

		$group = ( isset( $args[2] ) ) ? $args[2] : '';

		$expiration = ( isset( $args[3] ) ) ? $args[3] : 0;

		$result = wp_cache_replace( $key, $value, $group, $expiration );

		if ( false === $result ) {
			WP_CLI::error( "Could not replace object '$key' in group '$group'. Does it already exist?" );
			exit;
		}

		WP_CLI::success( "Replaced object '$key' in group '$group'." );
	}

	/**
	 * Set a value to the object cache.
	 *
	 * @uses	wp_cache_set
	 *
	 * @param 	array 			$args				Function arguments.
	 * @param 	array 			$assoc_args			Function arguments with parameter key.
	 * @return	void
	 */
	public function set( $args, $assoc_args ) {
		if ( count( $assoc_args ) + count( $args ) < 2 ) {
			WP_CLI::line( 'usage: wp cache set <key> <value> [group] [expiration]' );
			exit;
		}

		list( $key, $value ) = $args;

		$group = ( isset( $args[2] ) ) ? $args[2] : '';

		$expiration = ( isset( $args[3] ) ) ? $args[3] : 0;

		$result = wp_cache_set( $key, $value, $group, $expiration );

		if ( false === $result ) {
			WP_CLI::error( "Could not add object '$key' in group '$group'." );
			exit;
		}

		WP_CLI::success( "Set object '$key' in group '$group'." );
	}

	/**
	 * Switch the blog id, which switches the cache keys for all values.
	 *
	 * @uses	wp_cache_switch_to_blog
	 *
	 * @param 	array 			$args				Function arguments.
	 * @param 	array 			$assoc_args			Function arguments with parameter key.
	 * @return	void
	 */
	public function switch_to_blog( $args, $assoc_args ) {
		if ( empty( $args ) ) {
			WP_CLI::line( 'usage: wp cache switch_to_blog <blog_id>' );
			exit;
		}

		if ( ! function_exists( 'wp_cache_switch_to_blog' ) ) {
			WP_CLI::warning( "Command cannot be executed because the 'wp_cache_switch_to_blog' function does not exist." );
		}

		$blog_id = $args[0];

		wp_cache_switch_to_blog( $blog_id );
	}

	/**
	 * Attempts to determine which object cache is being used.
	 *
	 * Note that the guesses made by this function are based on the WP_Object_Cache classes
	 * that define the 3rd party object cache extension. Changes to those classes could render
	 * problems with this function's ability to determine which object cache is being used.
	 *
	 * @uses	$_wp_using_ext_object_cache
	 * @uses	$wp_object_cache
	 *
	 * @param 	array 			$args				Function arguments.
	 * @param 	array 			$assoc_args			Function arguments with parameter key.
	 * @return	void
	 */
	public function type( $args, $assoc_args ) {
		global $_wp_using_ext_object_cache, $wp_object_cache;

		$message = 'Unknown';

		if ( false !== $_wp_using_ext_object_cache ) {
			// Test for Memcached PECL extension memcached object cache (https://github.com/tollmanz/wordpress-memcached-backend)
			if ( isset( $wp_object_cache->m ) && is_a( $wp_object_cache->m, 'Memcached' ) )
				$message = 'Memcached (Memcached PECL extension)';

			// Test for Memcache PECL extension memcached object cache (http://wordpress.org/extend/plugins/memcached/)
			if ( isset( $wp_object_cache->mc ) ) {
				$is_memcache = true;
				foreach ( $wp_object_cache->mc as $bucket ) {
					if ( ! is_a( $bucket, 'Memcache' ) )
						$is_memcache = false;
				}

				if ( $is_memcache )
					$message = 'Memcached (Memcache PECL extension)';
			}

			// Test for Xcache object cache (http://plugins.svn.wordpress.org/xcache/trunk/object-cache.php)
			if ( is_a( $wp_object_cache, 'XCache_Object_Cache' ) )
				$message = 'Xcache';

			// Test for WinCache object cache (http://wordpress.org/extend/plugins/wincache-object-cache-backend/)
			if ( class_exists( 'WinCache_Object_Cache' ) )
				$message = 'WinCache';

			// Test for APC object cache (http://wordpress.org/extend/plugins/apc/)
			if ( class_exists( 'APC_Object_Cache' ) )
				$message = 'APC';
		} else {
			$message = 'Default object cache';
		}

		WP_CLI::print_value( $message );
	}
}