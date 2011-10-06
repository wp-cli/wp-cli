<?php

// Add the command to the wp-cli, only if the plugin is loaded
if ( function_exists( 'wp_super_cache_enable' ) ) {
	WP_CLI::addCommand( 'super-cache', 'WPSuperCacheCommand' );
}

/**
 * The WP Super Cache plugin
 *
 * @package wp-cli
 * @subpackage commands/community
 * @author Andreas Creten
 */
class WPSuperCacheCommand extends WP_CLI_Command {

	public static function get_description() {
		return 'Manage the WP Super Cache.';
	}

	/**
	 * Clear something from the cache
	 *
	 * @param array $args
	 * @param array $vars
	 * @return void
	 */
	function flush( $args = array(), $vars = array() ) {
		if ( function_exists( 'wp_cache_clear_cache' ) ) {
			if ( isset($vars['post_id']) ) {
				if ( is_numeric( $vars['post_id'] ) ) {
					wp_cache_post_change( $vars['post_id'] );
				} else {
					WP_CLI::error('This is not a valid post id.');
				}
				
				wp_cache_post_change( $vars['post_id'] );
			}
			elseif ( isset( $vars['permalink'] ) ) {
				$id = url_to_postid( $vars['permalink'] );
				
				if ( is_numeric( $id ) ) {
					wp_cache_post_change( $id );
				} else {
					WP_CLI::error('There is no post with this permalink.');
				}
			} else {
				wp_cache_clear_cache();
			}
		} else {
			WP_CLI::error('The WP Super Cache could not be found, is it installed?');
		}
	}
	
	/**
	 * Get the status of the cache
	 *
	 * @param array $args
	 * @param array $vars
	 * @return void
	 */
	function status( $args = array(), $vars = array() ) {
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
	 * Enable the WP Super Cache
	 *
	 * @param array $args
	 * @param array $vars
	 * @return void
	 */
	function enable( $args = array(), $vars = array() ) {
		if ( function_exists( 'wp_super_cache_enable' ) ) {
			global $super_cache_enabled;
			wp_super_cache_enable();
			if($super_cache_enabled) {
				WP_CLI::success( 'The WP Super Cache is enabled.' );
			} else {
				WP_CLI::error('The WP Super Cache is not enabled, check its settings page for more info.');
			}
		} else {
			WP_CLI::error('The WP Super Cache could not be found, is it installed?');
		}
	}
	
	/**
	 * Disable the WP Super Cache
	 *
	 * @param array $args
	 * @param array $vars
	 * @return void
	 */
	function disable( $args = array(), $vars = array() ) {
		if ( function_exists( 'wp_super_cache_disable' ) ) {
			global $super_cache_enabled;
			wp_super_cache_disable();
			if(!$super_cache_enabled) {
				WP_CLI::success( 'The WP Super Cache is disabled.' );
			} else {
				WP_CLI::error('The WP Super Cache is still enabled, check its settings page for more info.');
			}
		} else {
			WP_CLI::error('The WP Super Cache could not be found, is it installed?');
		}
	}

	/**
	 * Help function for this command
	 *
	 * @param array $args
	 * @return void
	 */
	public function help($args = array()) {
		// Shot the command description
		WP_CLI::line( $this->get_description() );
		WP_CLI::line();

		// Show the list of sub-commands for this command
		WP_CLI::line('Example usage:');
		WP_CLI::line('    wp wp-super-cache flush [--post_id=<post-id>] [--permalink=<post-permalink>]');
		WP_CLI::line('    wp wp-super-cache status');
		WP_CLI::line('    wp wp-super-cache enable');
		WP_CLI::line('    wp wp-super-cache disable');
		WP_CLI::line();
		WP_CLI::line('%9--- DETAILS ---%n');
		WP_CLI::line();
		WP_CLI::line('Remove the whole cache content:');
		WP_CLI::line('    wp wp-super-cache flush');
		WP_CLI::line();
		WP_CLI::line('Remove the caches for one blog post based on the id:');
		WP_CLI::line('    wp wp-super-cache flush --post_id=1');
		WP_CLI::line();
		WP_CLI::line('Remove the caches for one blog post based on the permalink:');
		WP_CLI::line('    wp wp-super-cache flush --permalink=http://example.com');
		WP_CLI::line();
		WP_CLI::line('Get details on the WP Super Cache content:');
		WP_CLI::line('    wp wp-super-cache status');
		WP_CLI::line();
		WP_CLI::line('Enable the WP Super Cache:');
		WP_CLI::line('    wp wp-super-cache enable');
		WP_CLI::line();
		WP_CLI::line('Disable the WP Super Cache:');
		WP_CLI::line('    wp wp-super-cache disable');
	}
}
