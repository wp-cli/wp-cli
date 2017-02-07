<?php
// Used by `wp server` to route requests.

namespace WP_CLI\Router;

/**
 * This is a copy of WordPress's add_filter() function.
 *
 * We duplicate it because WordPress is not loaded yet.
 */
function add_filter($tag, $function_to_add, $priority = 10, $accepted_args = 1) {
	global $wp_filter, $merged_filters;

	$idx = _wp_filter_build_unique_id($tag, $function_to_add, $priority);
	$wp_filter[$tag][$priority][$idx] = array('function' => $function_to_add, 'accepted_args' => $accepted_args);
	unset( $merged_filters[ $tag ] );
	return true;
}

/**
 * This is a copy of WordPress's _wp_filter_build_unique_id() function.
 *
 * We duplicate it because WordPress is not loaded yet.
 */
function _wp_filter_build_unique_id($tag, $function, $priority) {
	global $wp_filter;
	static $filter_id_count = 0;

	if ( is_string($function) )
		return $function;

	if ( is_object($function) ) {
		// Closures are currently implemented as objects
		$function = array( $function, '' );
	} else {
		$function = (array) $function;
	}

	if (is_object($function[0]) ) {
		// Object Class Calling
		if ( function_exists('spl_object_hash') ) {
			return spl_object_hash($function[0]) . $function[1];
		} else {
			$obj_idx = get_class($function[0]).$function[1];
			if ( !isset($function[0]->wp_filter_id) ) {
				if ( false === $priority )
					return false;
				$obj_idx .= isset($wp_filter[$tag][$priority]) ? count((array)$wp_filter[$tag][$priority]) : $filter_id_count;
				$function[0]->wp_filter_id = $filter_id_count;
				++$filter_id_count;
			} else {
				$obj_idx .= $function[0]->wp_filter_id;
			}

			return $obj_idx;
		}
	} else if ( is_string($function[0]) ) {
		// Static Calling
		return $function[0] . '::' . $function[1];
	}
}

function _get_full_host( $url ) {
	$parsed_url = parse_url( $url );

	$host = $parsed_url['host'];
	if ( isset( $parsed_url['port'] ) && $parsed_url['port'] != 80 )
		$host .= ':' . $parsed_url['port'];

	return $host;
}

// We need to trick WordPress into using the URL set by `wp server`, especially on multisite.
add_filter( 'option_home', function ( $url ) {
	$GLOBALS['_wp_cli_original_url'] = $url;

	return 'http://' . $_SERVER['HTTP_HOST'];
}, 20 );

add_filter( 'option_siteurl', function ( $url ) {
	if ( !isset( $GLOBALS['_wp_cli_original_url'] ) )
		get_option('home');  // trigger the option_home filter

	$home_url_host = _get_full_host( $GLOBALS['_wp_cli_original_url'] );
	$site_url_host = _get_full_host( $url );

	if ( $site_url_host == $home_url_host ) {
		$url = str_replace( $site_url_host, $_SERVER['HTTP_HOST'], $url );
	}

	return $url;
}, 20 );

$root = $_SERVER['DOCUMENT_ROOT'];
$path = '/'. ltrim( parse_url( urldecode( $_SERVER['REQUEST_URI'] ) )['path'], '/' );

if ( file_exists( $root.$path ) ) {
	if ( is_dir( $root.$path ) && substr( $path, -1 ) !== '/' ) {
		header( "Location: $path/" );
		exit;
	}

	if ( strpos( $path, '.php' ) !== false ) {
		chdir( dirname( $root.$path ) );
		require_once $root.$path;
	} else {
		return false;
	}
} else {
	chdir( $root );
	require_once 'index.php';
}
