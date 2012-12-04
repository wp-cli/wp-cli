<?php

if ( function_exists( 'wp_super_cache_enable' ) ) {
	WP_CLI::add_command( 'super-cache', 'WPSuperCache_Command' );
}

/**
 * The WP Super Cache plugin
 *
 * @package wp-cli
 * @subpackage commands/community
 */
class WPSuperCache_Command extends WP_CLI_Command {

	/**
	 * Clear something from the cache.
	 *
	 * @synopsis [--post_id=<post-id>] [--permalink=<permalink>]
	 */
	function flush( $args = array(), $assoc_args = array() ) {
		if ( isset($assoc_args['post_id']) ) {
			if ( is_numeric( $assoc_args['post_id'] ) ) {
				wp_cache_post_change( $assoc_args['post_id'] );
			} else {
				WP_CLI::error('This is not a valid post id.');
			}

			wp_cache_post_change( $assoc_args['post_id'] );
		}
		elseif ( isset( $assoc_args['permalink'] ) ) {
			$id = url_to_postid( $assoc_args['permalink'] );

			if ( is_numeric( $id ) ) {
				wp_cache_post_change( $id );
			} else {
				WP_CLI::error('There is no post with this permalink.');
			}
		} else {
			global $file_prefix;

			wp_cache_clean_cache( $file_prefix, true );

			WP_CLI::success( 'Cache cleared.' );
		}
	}

	/**
	 * Get the status of the cache.
	 */
	function status( $args = array(), $assoc_args = array() ) {
		$cache_stats = get_option( 'supercache_stats' );

		if ( !empty( $cache_stats ) ) {
			if ( $cache_stats['generated'] > time() - 3600 * 24 ) {
				global $super_cache_enabled;
				WP_CLI::line( 'Cache status: ' . ($super_cache_enabled ? '%gOn%n' : '%rOff%n') );
				WP_CLI::line( 'Cache content on ' . date('r', $cache_stats['generated'] ) . ': ' );
				WP_CLI::line();
				WP_CLI::line( '    WordPress cache:' );
				WP_CLI::line( '        Cached: ' . $cache_stats[ 'wpcache' ][ 'cached' ] );
				WP_CLI::line( '        Expired: ' . $cache_stats[ 'wpcache' ][ 'expired' ] );
				WP_CLI::line();
				WP_CLI::line( '    WP Super Cache:' );
				WP_CLI::line( '        Cached: ' . $cache_stats[ 'supercache' ][ 'cached' ] );
				WP_CLI::line( '        Expired: ' . $cache_stats[ 'supercache' ][ 'expired' ] );
			} else {
				WP_CLI::error('The WP Super Cache stats are too old to work with (older than 24 hours).');
			}
		} else {
			WP_CLI::error('No WP Super Cache stats found.');
		}
	}

	/**
	 * Enable the WP Super Cache.
	 */
	function enable( $args = array(), $assoc_args = array() ) {
		global $super_cache_enabled;

		wp_super_cache_enable();

		if($super_cache_enabled) {
			WP_CLI::success( 'The WP Super Cache is enabled.' );
		} else {
			WP_CLI::error('The WP Super Cache is not enabled, check its settings page for more info.');
		}
	}

	/**
	 * Disable the WP Super Cache.
	 */
	function disable( $args = array(), $assoc_args = array() ) {
		global $super_cache_enabled;

		wp_super_cache_disable();

		if(!$super_cache_enabled) {
			WP_CLI::success( 'The WP Super Cache is disabled.' );
		} else {
			WP_CLI::error('The WP Super Cache is still enabled, check its settings page for more info.');
		}
	}
}
