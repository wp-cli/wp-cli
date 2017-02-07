<?php

// Utilities that depend on WordPress code.

namespace WP_CLI\Utils;

function wp_not_installed() {
	if ( !is_blog_installed() && !defined( 'WP_INSTALLING' ) ) {
		\WP_CLI::error(
			"The site you have requested is not installed.\n" .
			'Run `wp core install`.' );
	}
}

function wp_debug_mode() {
	if ( \WP_CLI::get_config( 'debug' ) ) {
		if ( !defined( 'WP_DEBUG' ) )
			define( 'WP_DEBUG', true );

		error_reporting( E_ALL & ~E_DEPRECATED & ~E_STRICT );
	} else {
		if ( WP_DEBUG ) {
			error_reporting( E_ALL );

			if ( WP_DEBUG_DISPLAY )
				ini_set( 'display_errors', 1 );
			elseif ( null !== WP_DEBUG_DISPLAY )
				ini_set( 'display_errors', 0 );

			if ( WP_DEBUG_LOG ) {
				ini_set( 'log_errors', 1 );
				ini_set( 'error_log', WP_CONTENT_DIR . '/debug.log' );
			}
		} else {
			error_reporting( E_CORE_ERROR | E_CORE_WARNING | E_COMPILE_ERROR | E_ERROR | E_WARNING | E_PARSE | E_USER_ERROR | E_USER_WARNING | E_RECOVERABLE_ERROR );
		}

		if ( defined( 'XMLRPC_REQUEST' ) || defined( 'REST_REQUEST' ) || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
			@ini_set( 'display_errors', 0 );
		}
	}

	// XDebug already sends errors to STDERR
	ini_set( 'display_errors', function_exists( 'xdebug_debug_zval' ) ? false : 'STDERR' );
}

function replace_wp_die_handler() {
	\remove_filter( 'wp_die_handler', '_default_wp_die_handler' );
	\add_filter( 'wp_die_handler', function() { return __NAMESPACE__ . '\\' . 'wp_die_handler'; } );
}

function wp_die_handler( $message ) {
	if ( $message instanceof \WP_Error ) {
		$message = $message->get_error_message();
	}

	$original_message = $message = trim( $message );
	if ( preg_match( '|^\<h1>(.+?)</h1>|', $original_message, $matches ) ) {
		$message = $matches[1] . '.';
	}
	if ( preg_match( '|\<p>(.+?)</p>|', $original_message, $matches ) ) {
		$message .= ' ' . $matches[1];
	}

	$search_replace = array(
		'<code>'    => '`',
		'</code>'   => '`',
	);
	$message = str_replace( array_keys( $search_replace ), array_values( $search_replace ), $message );
	$message = strip_tags( $message );
	$message = html_entity_decode( $message, ENT_COMPAT, 'UTF-8' );

	\WP_CLI::error( $message );
}

function wp_redirect_handler( $url ) {
	\WP_CLI::warning( 'Some code is trying to do a URL redirect. Backtrace:' );

	ob_start();
	debug_print_backtrace();
	fwrite( STDERR, ob_get_clean() );

	return $url;
}

function maybe_require( $since, $path ) {
	if ( wp_version_compare( $since, '>=' ) ) {
		require $path;
	}
}

function get_upgrader( $class ) {
	if ( !class_exists( '\WP_Upgrader' ) )
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

	return new $class( new \WP_CLI\UpgraderSkin );
}

/**
 * Converts a plugin basename back into a friendly slug.
 */
function get_plugin_name( $basename ) {
	if ( false === strpos( $basename, '/' ) )
		$name = basename( $basename, '.php' );
	else
		$name = dirname( $basename );

	return $name;
}

function is_plugin_skipped( $file ) {
	$name = get_plugin_name( str_replace( WP_PLUGIN_DIR . '/', '', $file ) );

	$skipped_plugins = \WP_CLI::get_runner()->config['skip-plugins'];
	if ( true === $skipped_plugins )
		return true;

	if ( ! is_array( $skipped_plugins ) ) {
		$skipped_plugins = explode( ',', $skipped_plugins );
	}

	return in_array( $name, array_filter( $skipped_plugins ) );
}

function get_theme_name( $path ) {
	return basename( $path );
}

function is_theme_skipped( $path ) {
	$name = get_theme_name( $path );

	$skipped_themes = \WP_CLI::get_runner()->config['skip-themes'];
	if ( true === $skipped_themes )
		return true;

	if ( ! is_array( $skipped_themes ) ) {
		$skipped_themes = explode( ',', $skipped_themes );
	}

	return in_array( $name, array_filter( $skipped_themes ) );
}

/**
 * Register the sidebar for unused widgets
 * Core does this in /wp-admin/widgets.php, which isn't helpful
 */
function wp_register_unused_sidebar() {

	register_sidebar(array(
		'name' => __('Inactive Widgets'),
		'id' => 'wp_inactive_widgets',
		'class' => 'inactive-sidebar',
		'description' => __( 'Drag widgets here to remove them from the sidebar but keep their settings.' ),
		'before_widget' => '',
		'after_widget' => '',
		'before_title' => '',
		'after_title' => '',
	));

}

/**
 * Attempts to determine which object cache is being used.
 *
 * Note that the guesses made by this function are based on the WP_Object_Cache classes
 * that define the 3rd party object cache extension. Changes to those classes could render
 * problems with this function's ability to determine which object cache is being used.
 *
 * @return string
 */
function wp_get_cache_type() {
	global $_wp_using_ext_object_cache, $wp_object_cache;

	if ( ! empty( $_wp_using_ext_object_cache ) ) {
		// Test for Memcached PECL extension memcached object cache (https://github.com/tollmanz/wordpress-memcached-backend)
		if ( isset( $wp_object_cache->m ) && is_a( $wp_object_cache->m, 'Memcached' ) ) {
			$message = 'Memcached';

		// Test for Memcache PECL extension memcached object cache (http://wordpress.org/extend/plugins/memcached/)
		} elseif ( isset( $wp_object_cache->mc ) ) {
			$is_memcache = true;
			foreach ( $wp_object_cache->mc as $bucket ) {
				if ( ! is_a( $bucket, 'Memcache' ) && ! is_a( $bucket, 'Memcached' ) )
					$is_memcache = false;
			}

			if ( $is_memcache )
				$message = 'Memcache';

		// Test for Xcache object cache (http://plugins.svn.wordpress.org/xcache/trunk/object-cache.php)
		} elseif ( is_a( $wp_object_cache, 'XCache_Object_Cache' ) ) {
			$message = 'Xcache';

		// Test for WinCache object cache (http://wordpress.org/extend/plugins/wincache-object-cache-backend/)
		} elseif ( class_exists( 'WinCache_Object_Cache' ) ) {
			$message = 'WinCache';

		// Test for APC object cache (http://wordpress.org/extend/plugins/apc/)
		} elseif ( class_exists( 'APC_Object_Cache' ) ) {
			$message = 'APC';

		// Test for Redis Object Cache (https://github.com/alleyinteractive/wp-redis)
		} elseif ( isset( $wp_object_cache->redis ) && is_a( $wp_object_cache->redis, 'Redis' ) ) {
			$message = 'Redis';

		// Test for WP LCache Object cache (https://github.com/lcache/wp-lcache)
		} elseif ( isset( $wp_object_cache->lcache ) && is_a( $wp_object_cache->lcache, '\LCache\Integrated' ) ) {
			$message = 'WP LCache';

		} elseif( function_exists( 'w3_instance' ) ) {
			$config = w3_instance( 'W3_Config' );
			if ( $config->get_boolean( 'objectcache.enabled' ) ) {
				$message = 'W3TC ' . $config->get_string( 'objectcache.engine' );
			} else {
				$message = 'Unknown';
			}
		} else {
			$message = 'Unknown';
		}
	} else {
		$message = 'Default';
	}
	return $message;
}

/**
 * Clear all of the caches for memory management
 */
function wp_clear_object_cache() {
	global $wpdb, $wp_object_cache;

	$wpdb->queries = array(); // or define( 'WP_IMPORTING', true );

	if ( ! is_object( $wp_object_cache ) ) {
		return;
	}

	$wp_object_cache->group_ops = array();
	$wp_object_cache->stats = array();
	$wp_object_cache->memcache_debug = array();
	$wp_object_cache->cache = array();

	if ( is_callable( $wp_object_cache, '__remoteset' ) ) {
		$wp_object_cache->__remoteset(); // important
	}
}

/**
 * Get a set of tables in the database.
 *
 * Interprets common command-line options into a resolved set of table names.
 *
 * @param array $args Provided table names, or tables with wildcards.
 * @param array $assoc_args Optional flags for groups of tables (e.g. --network)
 * @return array $tables
 */
function wp_get_table_names( $args, $assoc_args = array() ) {
	global $wpdb;

	// Prioritize any supplied $args as tables
	if ( ! empty( $args ) ) {
		$new_tables = array();
		$get_tables_for_glob = function( $glob ) {
			global $wpdb;
			static $all_tables = array();
			if ( ! $all_tables ) {
				$all_tables = $wpdb->get_col( 'SHOW TABLES' );
			}
			$tables = array();
			foreach ( $all_tables as $table) {
				if ( fnmatch( $glob, $table ) ) {
					$tables[] = $table;
				}
			}
			return $tables;
		};
		foreach( $args as $key => $table ) {
			if ( false !== strpos( $table, '*' ) || false !== strpos( $table, '?' ) ) {
				$expanded_tables = $get_tables_for_glob( $table );
				if ( empty( $expanded_tables ) ) {
					\WP_CLI::error( "Couldn't find any tables matching: {$table}" );
				}
				$new_tables = array_merge( $new_tables, $expanded_tables );
			} else {
				$new_tables[] = $table;
			}
		}
		return $new_tables;
	}

	// Fall back to flag if no tables were passed
	$table_type = 'wordpress';
	if ( get_flag_value( $assoc_args, 'network' ) ) {
		$table_type = 'network';
	}
	if ( get_flag_value( $assoc_args, 'all-tables-with-prefix' ) ) {
		$table_type = 'all-tables-with-prefix';
	}
	if ( get_flag_value( $assoc_args, 'all-tables' ) ) {
		$table_type = 'all-tables';
	}

	$network = 'network' == $table_type;

	if ( 'all-tables' == $table_type ) {
		return $wpdb->get_col( 'SHOW TABLES' );
	}

	$prefix = $network ? $wpdb->base_prefix : $wpdb->prefix;
	$matching_tables = $wpdb->get_col( $wpdb->prepare( "SHOW TABLES LIKE %s", $prefix . '%' ) );

	if ( 'all-tables-with-prefix' == $table_type ) {
		return $matching_tables;
	}

	if ( $scope = get_flag_value( $assoc_args, 'scope' ) ) {
		return $wpdb->tables( $scope );
	}

	$allowed_tables = array();
	$allowed_table_types = array( 'tables', 'global_tables' );
	if ( $network ) {
		$allowed_table_types[] = 'ms_global_tables';
	}
	foreach( $allowed_table_types as $table_type ) {
		foreach( $wpdb->$table_type as $table ) {
			$allowed_tables[] = $prefix . $table;
		}
	}

	// Given our matching tables, also allow site-specific tables on the network
	foreach( $matching_tables as $key => $matched_table ) {

		if ( in_array( $matched_table, $allowed_tables ) ) {
			continue;
		}

		if ( $network ) {
			$valid_table = false;
			foreach( array_merge( $wpdb->tables, $wpdb->old_tables ) as $maybe_site_table ) {
				if ( preg_match( "#{$prefix}([\d]+)_{$maybe_site_table}#", $matched_table ) ) {
					$valid_table = true;
				}
			}
			if ( $valid_table ) {
				continue;
			}
		}

		unset( $matching_tables[ $key ] );

	}

	return array_values( $matching_tables );
}
