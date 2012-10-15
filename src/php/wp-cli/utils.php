<?php

namespace WP_CLI\Utils;

/**
 * Splits $argv into positional and associative arguments.
 *
 * @param string
 * @return array
 */
function parse_args() {
	$regular_args = array();
	$assoc_args = array();

	foreach ( array_slice( $GLOBALS['argv'], 1 ) as $arg ) {
		if ( preg_match( '|^--([^=]+)$|', $arg, $matches ) ) {
			$assoc_args[ $matches[1] ] = true;
		} elseif ( preg_match( '|^--([^=]+)=(.+)|', $arg, $matches ) ) {
			$assoc_args[ $matches[1] ] = $matches[2];
		} else {
			$regular_args[] = $arg;
		}
	}

	return array( $regular_args, $assoc_args );
}

function set_url( &$assoc_args ) {
	if ( isset( $assoc_args['url'] ) ) {
		define( 'WP_CLI_URL', $assoc_args['url'] );
		$blog = $assoc_args['url'];
		unset( $assoc_args['url'] );
	} elseif ( isset( $assoc_args['blog'] ) ) {
		$blog = $assoc_args['blog'];
		unset( $assoc_args['blog'] );
		if ( true === $blog ) {
			\WP_CLI::line( 'usage: wp --blog=example.com' );
		}
	} elseif ( is_readable( WP_ROOT . 'wp-cli-blog' ) ) {
		$blog = trim( file_get_contents( WP_ROOT . 'wp-cli-blog' ) );
	} elseif ( $wp_config_path = locate_wp_config() ) {
		// Try to find the blog parameter in the wp-config file
		$wp_config_file = file_get_contents( $wp_config_path );
		$hit = array();
		if ( preg_match_all( "#.*define\s*\(\s*(['|\"]{1})(.+)(['|\"]{1})\s*,\s*(['|\"]{1})(.+)(['|\"]{1})\s*\)\s*;#iU", $wp_config_file, $matches ) ) {
			foreach ( $matches[2] as $def_key => $def_name ) {
				if ( 'DOMAIN_CURRENT_SITE' == $def_name )
					$hit['domain'] = $matches[5][$def_key];
				if ( 'PATH_CURRENT_SITE' == $def_name )
					$hit['path'] = $matches[5][$def_key];
			}
		}

		if ( !empty( $hit ) && isset( $hit['domain'] ) )
			$blog = $hit['domain'];
		if ( !empty( $hit ) && isset( $hit['path'] ) )
			$blog .= $hit['path'];
	}

	if ( isset( $blog ) ) {
		set_url_params( $blog );
	}
}

/**
 * Sets the appropriate $_SERVER keys based on a given string
 *
 * @param string $url The URL
 */
function set_url_params( $url ) {
	$url_parts = parse_url( $url );

	if ( !isset( $url_parts['scheme'] ) ) {
		$url_parts = parse_url( 'http://' . $url );
	}

	$_SERVER['HTTP_HOST'] = isset($url_parts['host']) ? $url_parts['host'] : '';
	$_SERVER['REQUEST_URI'] = (isset($url_parts['path']) ? $url_parts['path'] : '') . (isset($url_parts['query']) ? '?' . $url_parts['query'] : '');
	$_SERVER['REQUEST_URL'] = isset($url_parts['path']) ? $url_parts['path'] : '';
	$_SERVER['QUERY_STRING'] = isset($url_parts['query']) ? $url_parts['query'] : '';
}

function locate_wp_config() {
	if ( file_exists( WP_ROOT . 'wp-config.php' ) ) {
		return WP_ROOT . 'wp-config.php';
	} elseif ( file_exists( WP_ROOT . '/../wp-config.php' ) && ! file_exists( WP_ROOT . '/../wp-settings.php' ) ) {
		return WP_ROOT . '/../wp-config.php';
	} else {
		return false;
	}
}

// Loads wp-config.php without loading the rest of WP
function load_wp_config() {
	define( 'ABSPATH', dirname(__FILE__) . '/' );

	if ( $wp_config_path = locate_wp_config() )
		require locate_wp_config();
	else
		\WP_CLI::error( 'No wp-config.php file.' );
}



// ---- AFTER WORDPRESS IS LOADED ---- //



// Handle --user parameter
function set_user( &$assoc_args ) {
	if ( !isset( $assoc_args['user'] ) )
		return;

	$user = $assoc_args['user'];

	if ( is_numeric( $user ) ) {
		$user_id = (int) $user;
	} else {
		$user_id = (int) username_exists( $user );
	}

	if ( !$user_id || !wp_set_current_user( $user_id ) ) {
		\WP_CLI::error( sprintf( 'Could not get a user_id for this user: %s', var_export( $user, true ) ) );
	}

	unset( $assoc_args['user'] );
}

function get_upgrader( $class ) {
	if ( !class_exists( '\WP_Upgrader' ) )
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

	require WP_CLI_ROOT . '/class-cli-upgrader-skin.php';

	return new $class( new \CLI_Upgrader_Skin );
}

