<?php

if ( function_exists( 'wp_super_cache_enable' ) ) {
	WP_CLI::add_command( 'super-cache', 'WPSuperCache_Command' );
}

/**
 * The WP Super Cache plugin
 *
 * @package wp-cli
 * @subpackage commands/community
 * @maintainer Andreas Creten
 */
class WPSuperCache_Command extends WP_CLI_Command {

	/**
	 * Clear something from the cache
	 *
	 * @param array $args
	 * @param array $vars
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
	 */
	public static function help() {
		WP_CLI::line( <<<EOB
usage: wp super-cache [flush|status|enable|disable] --post_id=<id> --permalink=<post-permalink>

Available sub-commands:
	flush      flushes whole cache, or post with given permalink or ID --post_id=<id> --permalink=<post-permalink>
	status     shows status of WP Super Cache
	enable     enables WP Super Cache
	disable    disables WP Super Cache
EOB
	);
	}
}
