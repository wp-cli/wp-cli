<?php

// Utilities that do NOT depend on WordPress code.

namespace WP_CLI\Utils;

function bootstrap() {
	$vendor_paths = array(
		WP_CLI_ROOT . '../../../../vendor',  // part of a larger project
		WP_CLI_ROOT . '../vendor',           // top-level project
	);

	$has_autoload = false;

	foreach ( $vendor_paths as $vendor_path ) {
		if ( file_exists( $vendor_path . '/autoload.php' ) ) {
			require $vendor_path . '/autoload.php';
			include $vendor_path . '/wp-cli/php-cli-tools/lib/cli/cli.php';
			$has_autoload = true;
			break;
		}
	}

	if ( !$has_autoload ) {
		include WP_CLI_ROOT . 'php-cli-tools/lib/cli/cli.php';
		\cli\register_autoload();
		register_autoload();
	}

	include WP_CLI_ROOT . 'Spyc.php';
}

function register_autoload() {
	spl_autoload_register( function($class) {
		// Only attempt to load classes in our namespace
		if ( 0 !== strpos( $class, 'WP_CLI\\' ) ) {
			return;
		}

		$path = WP_CLI_ROOT . str_replace( '\\', DIRECTORY_SEPARATOR, $class ) . '.php';

		if ( is_file( $path ) ) {
			require_once $path;
		}
	} );
}

function load_config( $allowed_keys ) {
	foreach ( array( 'wp-cli.local.yml', 'wp-cli.yml' ) as $fname ) {
		$path = getcwd() . '/' . $fname;

		if ( file_exists( $path ) ) {
			$config = spyc_load_file( $path );

			$sanitized_config = array();

			foreach ( $allowed_keys as $key ) {
				if ( isset( $config[ $key ] ) )
					$sanitized_config[ $key ] = $config[ $key ];
			}

			return $sanitized_config;
		}
	}

	return array();
}

/**
 * Splits $argv into positional and associative arguments.
 *
 * @param string
 * @return array
 */
function parse_args( $arguments ) {
	$regular_args = array();
	$assoc_args = array();

	foreach ( $arguments as $arg ) {
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

/**
 * Composes positional arguments into a command string.
 *
 * @param array
 * @return string
 */
function compose_args( $args ) {
	return ' ' . implode( ' ', array_map( 'escapeshellarg', $args ) );
}

/**
 * Composes associative arguments into a command string.
 *
 * @param array
 * @return string
 */
function compose_assoc_args( $assoc_args ) {
	$str = '';

	foreach ( $assoc_args as $key => $value ) {
		if ( true === $value )
			$str .= " --$key";
		else
			$str .= " --$key=" . escapeshellarg( $value );
	}

	return $str;
}

function set_url( $assoc_args ) {
	if ( isset( $assoc_args['url'] ) ) {
		$blog = $assoc_args['url'];
	} elseif ( isset( $assoc_args['blog'] ) ) {
		$blog = $assoc_args['blog'];
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

	$f = function( $key ) use ( $url_parts ) {
		return isset( $url_parts[ $key ] ) ? $url_parts[ $key ] : '';
	};

	$_SERVER['HTTP_HOST'] = $f('host');
	$_SERVER['REQUEST_URI'] = $f('path') . ( isset( $url_parts['query'] ) ? '?' . $url_parts['query'] : '' );
	$_SERVER['REQUEST_URL'] = $f('path');
	$_SERVER['QUERY_STRING'] = $f('query');
	$_SERVER['SERVER_NAME'] = substr($_SERVER['HTTP_HOST'], 0, strrpos($_SERVER['HTTP_HOST'], '.'));
	$_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.0';
	$_SERVER['HTTP_USER_AGENT'] = '';
	$_SERVER['REQUEST_METHOD'] = 'GET';
}

function set_wp_root( $config ) {
	if ( !empty( $config['path'] ) ) {
		define( 'WP_ROOT', rtrim( $config['path'], '/' ) . '/' );
	} else {
		define( 'WP_ROOT', getcwd() . '/' );
	}
}

function locate_wp_config() {
	if ( file_exists( WP_ROOT . 'wp-config.php' ) )
		return WP_ROOT . 'wp-config.php';

	if ( file_exists( WP_ROOT . '/../wp-config.php' ) && ! file_exists( WP_ROOT . '/../wp-settings.php' ) )
		return WP_ROOT . '/../wp-config.php';

	return false;
}

// Loads wp-config.php without loading the rest of WP
function load_wp_config() {
	define( 'ABSPATH', dirname(__FILE__) . '/' );

	if ( $wp_config_path = locate_wp_config() )
		require locate_wp_config();
	else
		\WP_CLI::error( 'No wp-config.php file.' );
}

/**
 * Take a serialised array and unserialise it replacing elements as needed and
 * unserialising any subordinate arrays and performing the replace on those too.
 *
 * @source https://github.com/interconnectit/Search-Replace-DB
 *
 * @param string $from       String we're looking to replace.
 * @param string $to         What we want it to be replaced with
 * @param array  $data       Used to pass any subordinate arrays back to in.
 * @param bool   $serialised Does the array passed via $data need serialising.
 *
 * @return array	The original array with all elements replaced as needed.
 */
function recursive_unserialize_replace( $from = '', $to = '', $data = '', $serialised = false ) {

	// some unseriliased data cannot be re-serialised eg. SimpleXMLElements
	try {

		if ( is_string( $data ) && ( $unserialized = @unserialize( $data ) ) !== false ) {
			$data = recursive_unserialize_replace( $from, $to, $unserialized, true );
		}

		elseif ( is_array( $data ) ) {
			$_tmp = array( );
			foreach ( $data as $key => $value ) {
				$_tmp[ $key ] = recursive_unserialize_replace( $from, $to, $value, false );
			}

			$data = $_tmp;
			unset( $_tmp );
		}

		// Submitted by Tina Matter
		elseif ( is_object( $data ) ) {
			$dataClass = get_class( $data );
			$_tmp = new $dataClass( );
			foreach ( $data as $key => $value ) {
				$_tmp->$key = recursive_unserialize_replace( $from, $to, $value, false );
			}

			$data = $_tmp;
			unset( $_tmp );
		}

		else {
			if ( is_string( $data ) )
				$data = str_replace( $from, $to, $data );
		}

		if ( $serialised )
			return serialize( $data );

	} catch( Exception $error ) {

	}

	return $data;
}

