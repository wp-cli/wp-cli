<?php

// Add the command to the wp-cli, only if the plugin is loaded
if ( function_exists( 'w3tc_pgcache_flush' ) ) {
	WP_CLI::addCommand( 'total-cache', 'W3TotalCacheCommand' );
}

/**
 * The WP Super Cache plugin
 *
 * @package wp-cli
 * @subpackage commands/community
 * @author Andreas Creten
 */
class W3TotalCacheCommand extends WP_CLI_Command {

	public static function get_description() {
		return 'Manage the W3 Total Cache.';
	}

	/**
	 * Clear something from the cache
	 *
	 * @param array $args
	 * @param array $vars
	 * @return void
	 */
	function flush( $args = array(), $vars = array() ) {
		if ( function_exists( 'w3tc_pgcache_flush' ) ) {
			$cache_type = array_shift($args);

			switch($cache_type) {
				case 'db':
				case 'database':
					if ( w3tc_dbcache_flush() ) {
						WP_CLI::success( 'The object cache is flushed successfully.' );
					} else {
						WP_CLI::error( 'Flushing the object cache failed.' );
					}
				break;
				
				case 'minify':
					if ( w3tc_minify_flush() ) {
						WP_CLI::success( 'The object cache is flushed successfully.' );
					} else {
						WP_CLI::error( 'Flushing the object cache failed.' );
					}
				break;
				
				case 'object':
					if ( w3tc_objectcache_flush() ) {
						WP_CLI::success( 'The object cache is flushed successfully.' );
					} else {
						WP_CLI::error( 'Flushing the object cache failed.' );
					}
				break;
				
				case 'post':
				default:
					if ( isset($vars['post_id']) ) {
						if ( is_numeric( $vars['post_id'] ) ) {
							w3tc_pgcache_flush_post( $vars['post_id'] );
						} else {
							WP_CLI::error('This is not a valid post id.');
						}

						w3tc_pgcache_flush_post( $vars['post_id'] );
					}
					elseif ( isset( $vars['permalink'] ) ) {
						$id = url_to_postid( $vars['permalink'] );

						if ( is_numeric( $id ) ) {
							w3tc_pgcache_flush_post( $id );
						} else {
							WP_CLI::error('There is no post with this permalink.');
						}
					} else {
						w3tc_pgcache_flush();
					}
			}
		} else {
			WP_CLI::error('The W3 Total Cache could not be found, is it installed?');
		}
	}
	
	/**
	 * Help function for this command
	 *
	 * @param string $args
	 * @return void
	 */
	public function help($args = array()) {
		// Shot the command description
		WP_CLI::line( $this->get_description() );
		WP_CLI::line();

		// Show the list of sub-commands for this command
		WP_CLI::line('Example usage:');
		WP_CLI::line('    wp total-cache flush [post|database|minify|object] [--post_id=<post-id>] [--permalink=<post-permalink>]');
		WP_CLI::line();
		WP_CLI::line('%9--- DETAILS ---%n');
		WP_CLI::line();
		WP_CLI::line('Remove all post/page caches:');
		WP_CLI::line('    wp total-cache flush');
		WP_CLI::line();
		WP_CLI::line('Remove the caches for one blog post based on the id:');
		WP_CLI::line('    wp total-cache flush --post_id=1');
		WP_CLI::line();
		WP_CLI::line('Remove the caches for one blog post based on the permalink:');
		WP_CLI::line('    wp total-cache flush --permalink=http://example.com');
		WP_CLI::line();
		WP_CLI::line('Remove the database cache:');
		WP_CLI::line('    wp total-cache flush database');
		WP_CLI::line();
		WP_CLI::line('Remove the object cache:');
		WP_CLI::line('    wp total-cache flush object');
		WP_CLI::line();
		WP_CLI::line('Remove the minify cache:');
		WP_CLI::line('    wp total-cache flush minify');
	}
}
