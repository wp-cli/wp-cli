<?php

if ( function_exists( 'w3tc_pgcache_flush' ) ) {
	WP_CLI::add_command( 'total-cache', 'W3TotalCache_Command' );
}

/**
 * The W3 Total Cache plugin
 *
 * @package wp-cli
 * @subpackage commands/community
 */
class W3TotalCache_Command extends WP_CLI_Command {

	/**
	 * Clear something from the cache.
	 *
	 * @synopsis <cache-type>... [--post_id=<post-id>] [--permalink=<permalink>]
	 */
	function flush( $args = array(), $vars = array() ) {
		$args = array_unique( $args );
		do {
			$cache_type = array_shift($args);

			switch($cache_type) {
			case 'db':
			case 'database':
				if ( w3tc_dbcache_flush() ) {
					WP_CLI::success( 'The database cache is flushed successfully.' );
				} else {
					WP_CLI::error( 'Flushing the database cache failed.' );
				}
				break;

			case 'minify':
				if ( w3tc_minify_flush() ) {
					WP_CLI::success( 'The minify cache is flushed successfully.' );
				} else {
					WP_CLI::error( 'Flushing the minify cache failed.' );
				}
				break;

			case 'object':
				if ( w3tc_objectcache_flush() ) {
					WP_CLI::success( 'The object cache is flushed successfully.' );
				} else {
					WP_CLI::error( 'Flushing the object cache failed.' );
				}
				break;

			case 'page':
				if ( w3tc_pgcache_flush() ) {
					WP_CLI::success( 'The page cache is flushed successfully.' );
				} else {
					WP_CLI::error( 'Flushing the page cache failed.' );
				}
				break;

			case 'post':
			default:
				if ( isset($vars['post_id']) ) {
					if ( is_numeric( $vars['post_id'] ) && get_post( $vars['post_id'] ) ) {
						if ( w3tc_pgcache_flush_post( $vars['post_id'] ) ) {
							WP_CLI::success( 'Post '.$vars['post_id'].' is flushed successfully.' );
						} else {
							WP_CLI::error( 'Flushing '.$vars['post_id'].' from cache failed.' );
						}
					} else {
						WP_CLI::error('This is not a valid post id.');
					}
				}
				elseif ( isset( $vars['permalink'] ) ) {
					$id = url_to_postid( $vars['permalink'] );

					if ( is_numeric( $id ) && $id > 0 ) {
						if ( w3tc_pgcache_flush_post( $id ) ) {
							WP_CLI::success( $id.' is flushed successfully.' );
						} else {
							WP_CLI::error( 'Flushing '.$id.' from cache failed.' );
						}
					} else {
						WP_CLI::error('There is no post with this permalink.');
					}
				}
			}
		} while ( !empty( $args ) );
	}

	/**
	 * Help function for this command
	 */
	public static function help() {
		WP_CLI::line( <<<EOB
usage: wp total-cache flush [post|database|minify|object|page] [--post_id=<post-id>] [--permalink=<post-permalink>]

Available sub-commands:
	flush
			 --post_id=<id>                  flush specific ID
			 --permalink=<post-permalink>    flush specific permalink
			 database                        flushes database cache
			 object                          flush object cache
			 minify                          flush minify cache
			 page                            flush page cache
EOB
	);
	}
}
