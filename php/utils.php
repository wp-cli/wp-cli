<?php

// Utilities that do NOT depend on WordPress code.

namespace WP_CLI\Utils;

use \Composer\Semver\Comparator;
use \Composer\Semver\Semver;
use \WP_CLI;
use \WP_CLI\Dispatcher;
use \WP_CLI\Iterators\Transform;

const PHAR_STREAM_PREFIX = 'phar://';

function inside_phar() {
	return 0 === strpos( WP_CLI_ROOT, PHAR_STREAM_PREFIX );
}

// Files that need to be read by external programs have to be extracted from the Phar archive.
function extract_from_phar( $path ) {
	if ( ! inside_phar() ) {
		return $path;
	}

	$fname = basename( $path );

	$tmp_path = get_temp_dir() . "wp-cli-$fname";

	copy( $path, $tmp_path );

	register_shutdown_function( function() use ( $tmp_path ) {
		@unlink( $tmp_path );
	} );

	return $tmp_path;
}

function load_dependencies() {
	if ( inside_phar() ) {
		if ( file_exists( WP_CLI_ROOT . '/vendor/autoload.php' ) ) {
			require WP_CLI_ROOT . '/vendor/autoload.php';
		} elseif ( file_exists( dirname( dirname( WP_CLI_ROOT ) ) . '/autoload.php' ) ) {
			require dirname( dirname( WP_CLI_ROOT ) ) . '/autoload.php';
		}
		return;
	}

	$has_autoload = false;

	foreach ( get_vendor_paths() as $vendor_path ) {
		if ( file_exists( $vendor_path . '/autoload.php' ) ) {
			require $vendor_path . '/autoload.php';
			$has_autoload = true;
			break;
		}
	}

	if ( !$has_autoload ) {
		fputs( STDERR, "Internal error: Can't find Composer autoloader.\nTry running: composer install\n" );
		exit(3);
	}
}

function get_vendor_paths() {
	$vendor_paths = array(
		WP_CLI_ROOT . '/../../../vendor',  // part of a larger project / installed via Composer (preferred)
		WP_CLI_ROOT . '/vendor',           // top-level project / installed as Git clone
	);
	$maybe_composer_json = WP_CLI_ROOT . '/../../../composer.json';
	if ( file_exists( $maybe_composer_json ) && is_readable( $maybe_composer_json ) ) {
		$composer = json_decode( file_get_contents( $maybe_composer_json ) );
		if ( ! empty( $composer->config ) && ! empty( $composer->config->{'vendor-dir'} ) ) {
			array_unshift( $vendor_paths, WP_CLI_ROOT . '/../../../' . $composer->config->{'vendor-dir'} );
		}
	}
	return $vendor_paths;
}

// Using require() directly inside a class grants access to private methods to the loaded code
function load_file( $path ) {
	require_once $path;
}

function load_command( $name ) {
	$path = WP_CLI_ROOT . "/php/commands/$name.php";

	if ( is_readable( $path ) ) {
		include_once $path;
	}
}

/**
 * Like array_map(), except it returns a new iterator, instead of a modified array.
 *
 * Example:
 *
 *     $arr = array('Football', 'Socker');
 *
 *     $it = iterator_map($arr, 'strtolower', function($val) {
 *       return str_replace('foo', 'bar', $val);
 *     });
 *
 *     foreach ( $it as $val ) {
 *       var_dump($val);
 *     }
 *
 * @param array|object Either a plain array or another iterator
 * @param callback The function to apply to an element
 * @return object An iterator that applies the given callback(s)
 */
function iterator_map( $it, $fn ) {
	if ( is_array( $it ) ) {
		$it = new \ArrayIterator( $it );
	}

	if ( !method_exists( $it, 'add_transform' ) ) {
		$it = new Transform( $it );
	}

	foreach ( array_slice( func_get_args(), 1 ) as $fn ) {
		$it->add_transform( $fn );
	}

	return $it;
}

/**
 * Search for file by walking up the directory tree until the first file is found or until $stop_check($dir) returns true
 * @param string|array The files (or file) to search for
 * @param string|null The directory to start searching from; defaults to CWD
 * @param callable Function which is passed the current dir each time a directory level is traversed
 * @return null|string Null if the file was not found
 */
function find_file_upward( $files, $dir = null, $stop_check = null ) {
	$files = (array) $files;
	if ( is_null( $dir ) ) {
		$dir = getcwd();
	}
	while ( @is_readable( $dir ) ) {
		// Stop walking up when the supplied callable returns true being passed the $dir
		if ( is_callable( $stop_check ) && call_user_func( $stop_check, $dir ) ) {
			return null;
		}

		foreach ( $files as $file ) {
			$path = $dir . DIRECTORY_SEPARATOR . $file;
			if ( file_exists( $path ) ) {
				return $path;
			}
		}

		$parent_dir = dirname( $dir );
		if ( empty($parent_dir) || $parent_dir === $dir ) {
			break;
		}
		$dir = $parent_dir;
	}
	return null;
}

function is_path_absolute( $path ) {
	// Windows
	if ( isset($path[1]) && ':' === $path[1] )
		return true;

	return $path[0] === '/';
}

/**
 * Composes positional arguments into a command string.
 *
 * @param array
 * @return string
 */
function args_to_str( $args ) {
	return ' ' . implode( ' ', array_map( 'escapeshellarg', $args ) );
}

/**
 * Composes associative arguments into a command string.
 *
 * @param array
 * @return string
 */
function assoc_args_to_str( $assoc_args ) {
	$str = '';

	foreach ( $assoc_args as $key => $value ) {
		if ( true === $value ) {
			$str .= " --$key";
		} elseif( is_array( $value ) ) {
			foreach( $value as $_ => $v ) {
				$str .= assoc_args_to_str( array( $key => $v ) );
			}
		} else {
			$str .= " --$key=" . escapeshellarg( $value );
		}
	}

	return $str;
}

/**
 * Given a template string and an arbitrary number of arguments,
 * returns the final command, with the parameters escaped.
 */
function esc_cmd( $cmd ) {
	if ( func_num_args() < 2 )
		trigger_error( 'esc_cmd() requires at least two arguments.', E_USER_WARNING );

	$args = func_get_args();

	$cmd = array_shift( $args );

	return vsprintf( $cmd, array_map( 'escapeshellarg', $args ) );
}

function locate_wp_config() {
	static $path;

	if ( null === $path ) {
		if ( file_exists( ABSPATH . 'wp-config.php' ) )
			$path = ABSPATH . 'wp-config.php';
		elseif ( file_exists( ABSPATH . '../wp-config.php' ) && ! file_exists( ABSPATH . '/../wp-settings.php' ) )
			$path = ABSPATH . '../wp-config.php';
		else
			$path = false;

		if ( $path )
			$path = realpath( $path );
	}

	return $path;
}

function wp_version_compare( $since, $operator ) {
	return version_compare( str_replace( array( '-src' ), '', $GLOBALS['wp_version'] ), $since, $operator );
}

/**
 * Render a collection of items as an ASCII table, JSON, CSV, YAML, list of ids, or count.
 *
 * Given a collection of items with a consistent data structure:
 *
 * ```
 * $items = array(
 *     array(
 *         'key'   => 'foo',
 *         'value'  => 'bar',
 *     )
 * );
 * ```
 *
 * Render `$items` as an ASCII table:
 *
 * ```
 * WP_CLI\Utils\format_items( 'table', $items, array( 'key', 'value' ) );
 *
 * # +-----+-------+
 * # | key | value |
 * # +-----+-------+
 * # | foo | bar   |
 * # +-----+-------+
 * ```
 *
 * Or render `$items` as YAML:
 *
 * ```
 * WP_CLI\Utils\format_items( 'yaml', $items, array( 'key', 'value' ) );
 *
 * # ---
 * # -
 * #   key: foo
 * #   value: bar
 * ```
 *
 * @access public
 * @category Output
 *
 * @param string        $format     Format to use: 'table', 'json', 'csv', 'yaml', 'ids', 'count'
 * @param array         $items      An array of items to output.
 * @param array|string  $fields     Named fields for each item of data. Can be array or comma-separated list.
 * @return null
 */
function format_items( $format, $items, $fields ) {
	$assoc_args = compact( 'format', 'fields' );
	$formatter = new \WP_CLI\Formatter( $assoc_args );
	$formatter->display_items( $items );
}

/**
 * Write data as CSV to a given file.
 *
 * @access public
 *
 * @param resource $fd         File descriptor
 * @param array    $rows       Array of rows to output
 * @param array    $headers    List of CSV columns (optional)
 */
function write_csv( $fd, $rows, $headers = array() ) {
	if ( ! empty( $headers ) ) {
		fputcsv( $fd, $headers );
	}

	foreach ( $rows as $row ) {
		if ( ! empty( $headers ) ) {
			$row = pick_fields( $row, $headers );
		}

		fputcsv( $fd, array_values( $row ) );
	}
}

/**
 * Pick fields from an associative array or object.
 *
 * @param array|object Associative array or object to pick fields from
 * @param array List of fields to pick
 * @return array
 */
function pick_fields( $item, $fields ) {
	$item = (object) $item;

	$values = array();

	foreach ( $fields as $field ) {
		$values[ $field ] = isset( $item->$field ) ? $item->$field : null;
	}

	return $values;
}

/**
 * Launch system's $EDITOR for the user to edit some text.
 *
 * @access public
 * @category Input
 *
 * @param  string  $content  Some form of text to edit (e.g. post content)
 * @return string|bool       Edited text, if file is saved from editor; false, if no change to file.
 */
function launch_editor_for_input( $input, $filename = 'WP-CLI' ) {

	check_proc_available( 'launch_editor_for_input' );

	$tmpdir = get_temp_dir();

	do {
		$tmpfile = basename( $filename );
		$tmpfile = preg_replace( '|\.[^.]*$|', '', $tmpfile );
		$tmpfile .= '-' . substr( md5( rand() ), 0, 6 );
		$tmpfile = $tmpdir . $tmpfile . '.tmp';
		$fp = @fopen( $tmpfile, 'x' );
		if ( ! $fp && is_writable( $tmpdir ) && file_exists( $tmpfile ) ) {
			$tmpfile = '';
			continue;
		}
		if ( $fp ) {
			fclose( $fp );
		}
	} while( ! $tmpfile );

	if ( ! $tmpfile ) {
		\WP_CLI::error( 'Error creating temporary file.' );
	}

	$output = '';
	file_put_contents( $tmpfile, $input );

	$editor = getenv( 'EDITOR' );
	if ( !$editor ) {
		if ( isset( $_SERVER['OS'] ) && false !== strpos( $_SERVER['OS'], 'indows' ) )
			$editor = 'notepad';
		else
			$editor = 'vi';
	}

	$descriptorspec = array( STDIN, STDOUT, STDERR );
	$process = proc_open( "$editor " . escapeshellarg( $tmpfile ), $descriptorspec, $pipes );
	$r = proc_close( $process );
	if ( $r ) {
		exit( $r );
	}

	$output = file_get_contents( $tmpfile );

	unlink( $tmpfile );

	if ( $output === $input )
		return false;

	return $output;
}

/**
 * @param string MySQL host string, as defined in wp-config.php
 * @return array
 */
function mysql_host_to_cli_args( $raw_host ) {
	$assoc_args = array();

	$host_parts = explode( ':',  $raw_host );
	if ( count( $host_parts ) == 2 ) {
		list( $assoc_args['host'], $extra ) = $host_parts;
		$extra = trim( $extra );
		if ( is_numeric( $extra ) ) {
			$assoc_args['port'] = intval( $extra );
			$assoc_args['protocol'] = 'tcp';
		} else if ( $extra !== '' ) {
			$assoc_args['socket'] = $extra;
		}
	} else {
		$assoc_args['host'] = $raw_host;
	}

	return $assoc_args;
}

function run_mysql_command( $cmd, $assoc_args, $descriptors = null ) {
	check_proc_available( 'run_mysql_command' );

	if ( !$descriptors )
		$descriptors = array( STDIN, STDOUT, STDERR );

	if ( isset( $assoc_args['host'] ) ) {
		$assoc_args = array_merge( $assoc_args, mysql_host_to_cli_args( $assoc_args['host'] ) );
	}

	$pass = $assoc_args['pass'];
	unset( $assoc_args['pass'] );

	$old_pass = getenv( 'MYSQL_PWD' );
	putenv( 'MYSQL_PWD=' . $pass );

	$final_cmd = force_env_on_nix_systems( $cmd ) . assoc_args_to_str( $assoc_args );

	$proc = proc_open( $final_cmd, $descriptors, $pipes );
	if ( !$proc )
		exit(1);

	$r = proc_close( $proc );

	putenv( 'MYSQL_PWD=' . $old_pass );

	if ( $r ) exit( $r );
}

/**
 * Render PHP or other types of files using Mustache templates.
 *
 * IMPORTANT: Automatic HTML escaping is disabled!
 */
function mustache_render( $template_name, $data = array() ) {
	if ( ! file_exists( $template_name ) )
		$template_name = WP_CLI_ROOT . "/templates/$template_name";

	$template = file_get_contents( $template_name );

	$m = new \Mustache_Engine( array(
		'escape' => function ( $val ) { return $val; },
	) );

	return $m->render( $template, $data );
}

/**
 * Create a progress bar to display percent completion of a given operation.
 *
 * Progress bar is written to STDOUT, and disabled when command is piped. Progress
 * advances with `$progress->tick()`, and completes with `$progress->finish()`.
 * Process bar also indicates elapsed time and expected total time.
 *
 * ```
 * # `wp user generate` ticks progress bar each time a new user is created.
 * #
 * # $ wp user generate --count=500
 * # Generating users  22 % [=======>                             ] 0:05 / 0:23
 *
 * $progress = \WP_CLI\Utils\make_progress_bar( 'Generating users', $count );
 * for ( $i = 0; $i < $count; $i++ ) {
 *     // uses wp_insert_user() to insert the user
 *     $progress->tick();
 * }
 * $progress->finish();
 * ```
 *
 * @access public
 * @category Output
 *
 * @param string  $message  Text to display before the progress bar.
 * @param integer $count    Total number of ticks to be performed.
 * @return cli\progress\Bar|WP_CLI\NoOp
 */
function make_progress_bar( $message, $count ) {
	if ( \cli\Shell::isPiped() )
		return new \WP_CLI\NoOp;

	return new \cli\progress\Bar( $message, $count );
}

function parse_url( $url ) {
	$url_parts = \parse_url( $url );

	if ( !isset( $url_parts['scheme'] ) ) {
		$url_parts = parse_url( 'http://' . $url );
	}

	return $url_parts;
}

/**
 * Check if we're running in a Windows environment (cmd.exe).
 *
 * @return bool
 */
function is_windows() {
	return false !== ( $test_is_windows = getenv( 'WP_CLI_TEST_IS_WINDOWS' ) ) ? (bool) $test_is_windows : strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
}

/**
 * Replace magic constants in some PHP source code.
 *
 * @param string $source The PHP code to manipulate.
 * @param string $path The path to use instead of the magic constants
 */
function replace_path_consts( $source, $path ) {
	$replacements = array(
		'__FILE__' => "'$path'",
		'__DIR__'  => "'" . dirname( $path ) . "'",
	);

	$old = array_keys( $replacements );
	$new = array_values( $replacements );

	return str_replace( $old, $new, $source );
}

/**
 * Make a HTTP request to a remote URL.
 *
 * Wraps the Requests HTTP library to ensure every request includes a cert.
 *
 * ```
 * # `wp core download` verifies the hash for a downloaded WordPress archive
 *
 * $md5_response = Utils\http_request( 'GET', $download_url . '.md5' );
 * if ( 20 != substr( $md5_response->status_code, 0, 2 ) ) {
 *      WP_CLI::error( "Couldn't access md5 hash for release (HTTP code {$response->status_code})" );
 * }
 * ```
 *
 * @access public
 *
 * @param string $method    HTTP method (GET, POST, DELETE, etc.)
 * @param string $url       URL to make the HTTP request to.
 * @param array $headers    Add specific headers to the request.
 * @param array $options
 * @return object
 */
function http_request( $method, $url, $data = null, $headers = array(), $options = array() ) {

	$cert_path = '/rmccue/requests/library/Requests/Transport/cacert.pem';
	if ( inside_phar() ) {
		// cURL can't read Phar archives
		$options['verify'] = extract_from_phar(
		WP_CLI_VENDOR_DIR . $cert_path );
	} else {
		foreach( get_vendor_paths() as $vendor_path ) {
			if ( file_exists( $vendor_path . $cert_path ) ) {
				$options['verify'] = $vendor_path . $cert_path;
				break;
			}
		}
		if ( empty( $options['verify'] ) ){
			WP_CLI::error( "Cannot find SSL certificate." );
		}
	}

	try {
		$request = \Requests::request( $url, $headers, $data, $method, $options );
		return $request;
	} catch( \Requests_Exception $ex ) {
		// Handle SSL certificate issues gracefully
		\WP_CLI::warning( $ex->getMessage() );
		$options['verify'] = false;
		try {
			return \Requests::request( $url, $headers, $data, $method, $options );
		} catch( \Requests_Exception $ex ) {
			\WP_CLI::error( $ex->getMessage() );
		}
	}
}

/**
 * Increments a version string using the "x.y.z-pre" format
 *
 * Can increment the major, minor or patch number by one
 * If $new_version == "same" the version string is not changed
 * If $new_version is not a known keyword, it will be used as the new version string directly
 *
 * @param  string $current_version
 * @param  string $new_version
 * @return string
 */
function increment_version( $current_version, $new_version ) {
	// split version assuming the format is x.y.z-pre
	$current_version    = explode( '-', $current_version, 2 );
	$current_version[0] = explode( '.', $current_version[0] );

	switch ( $new_version ) {
		case 'same':
			// do nothing
			break;

		case 'patch':
			$current_version[0][2]++;

			$current_version = array( $current_version[0] ); // drop possible pre-release info
			break;

		case 'minor':
			$current_version[0][1]++;
			$current_version[0][2] = 0;

			$current_version = array( $current_version[0] ); // drop possible pre-release info
			break;

		case 'major':
			$current_version[0][0]++;
			$current_version[0][1] = 0;
			$current_version[0][2] = 0;

			$current_version = array( $current_version[0] ); // drop possible pre-release info
			break;

		default: // not a keyword
			$current_version = array( array( $new_version ) );
			break;
	}

	// reconstruct version string
	$current_version[0] = implode( '.', $current_version[0] );
	$current_version    = implode( '-', $current_version );

	return $current_version;
}

/**
 * Compare two version strings to get the named semantic version.
 *
 * @access public
 *
 * @param string $new_version
 * @param string $original_version
 * @return string $name 'major', 'minor', 'patch'
 */
function get_named_sem_ver( $new_version, $original_version ) {

	if ( ! Comparator::greaterThan( $new_version, $original_version ) ) {
		return '';
	}

	$parts = explode( '-', $original_version );
	$bits = explode( '.', $parts[0] );
	$major = $bits[0];
	if ( isset( $bits[1] ) ) {
		$minor = $bits[1];
	}
	if ( isset( $bits[2] ) ) {
		$patch = $bits[2];
	}

	if ( ! is_null( $minor ) && Semver::satisfies( $new_version, "{$major}.{$minor}.x" ) ) {
		return 'patch';
	} else if ( Semver::satisfies( $new_version, "{$major}.x.x" ) ) {
		return 'minor';
	} else {
		return 'major';
	}
}

/**
 * Return the flag value or, if it's not set, the $default value.
 *
 * Because flags can be negated (e.g. --no-quiet to negate --quiet), this
 * function provides a safer alternative to using
 * `isset( $assoc_args['quiet'] )` or similar.
 *
 * @access public
 * @category Input
 *
 * @param array  $assoc_args  Arguments array.
 * @param string $flag        Flag to get the value.
 * @param mixed  $default     Default value for the flag. Default: NULL
 * @return mixed
 */
function get_flag_value( $assoc_args, $flag, $default = null ) {
	return isset( $assoc_args[ $flag ] ) ? $assoc_args[ $flag ] : $default;
}

/**
 * Get the home directory.
 *
 * @access public
 * @category System
 *
 * @return string
 */
function get_home_dir() {
	$home = getenv( 'HOME' );
	if ( ! $home ) {
		// In Windows $HOME may not be defined
		$home = getenv( 'HOMEDRIVE' ) . getenv( 'HOMEPATH' );
	}

	return rtrim( $home, '/\\' );
}

/**
 * Appends a trailing slash.
 *
 * @access public
 * @category System
 *
 * @param string $string What to add the trailing slash to.
 * @return string String with trailing slash added.
 */
function trailingslashit( $string ) {
	return rtrim( $string, '/\\' ) . '/';
}

/**
 * Get the system's temp directory. Warns user if it isn't writable.
 *
 * @access public
 * @category System
 *
 * @return string
 */
function get_temp_dir() {
	static $temp = '';

	if ( $temp ) {
		return $temp;
	}

	// `sys_get_temp_dir()` introduced PHP 5.2.1.
	if ( $try = sys_get_temp_dir() ) {
		$temp = trailingslashit( $try );
	} elseif ( $try = ini_get( 'upload_tmp_dir' ) ) {
		$temp = trailingslashit( $try );
	} else {
		$temp = '/tmp/';
	}

	if ( ! @is_writable( $temp ) ) {
		\WP_CLI::warning( "Temp directory isn't writable: {$temp}" );
	}

	return $temp;
}

/**
 * Parse a SSH url for its host, port, and path.
 *
 * Similar to parse_url(), but adds support for defined SSH aliases.
 *
 * ```
 * host OR host/path/to/wordpress OR host:port/path/to/wordpress
 * ```
 *
 * @access public
 *
 * @return mixed
 */
function parse_ssh_url( $url, $component = -1 ) {
	preg_match( '#^((docker|docker\-compose|ssh):)?(([^@:]+)@)?([^:/~]+)(:([\d]*))?((/|~)(.+))?$#', $url, $matches );
	$bits = array();
	foreach( array(
		2 => 'scheme',
		4 => 'user',
		5 => 'host',
		7 => 'port',
		8 => 'path',
	) as $i => $key ) {
		if ( ! empty( $matches[ $i ] ) ) {
			$bits[ $key ] = $matches[ $i ];
		}
	}
	switch ( $component ) {
		case PHP_URL_SCHEME:
			return isset( $bits['scheme'] ) ? $bits['scheme'] : null;
		case PHP_URL_USER:
			return isset( $bits['user'] ) ? $bits['user'] : null;
		case PHP_URL_HOST:
			return isset( $bits['host'] ) ? $bits['host'] : null;
		case PHP_URL_PATH:
			return isset( $bits['path'] ) ? $bits['path'] : null;
		case PHP_URL_PORT:
			return isset( $bits['port'] ) ? $bits['port'] : null;
		default:
			return $bits;
	}
}

/**
 * Report the results of the same operation against multiple resources.
 *
 * @access public
 * @category Input
 *
 * @param string  $noun      Resource being affected (e.g. plugin)
 * @param string  $verb      Type of action happening to the noun (e.g. activate)
 * @param integer $total     Total number of resource being affected.
 * @param integer $successes Number of successful operations.
 * @param integer $failures  Number of failures.
 */
function report_batch_operation_results( $noun, $verb, $total, $successes, $failures ) {
	$plural_noun = $noun . 's';
	if ( in_array( $verb, array( 'reset' ), true ) ) {
		$past_tense_verb = $verb;
	} else {
		$past_tense_verb = 'e' === substr( $verb, -1 ) ? $verb . 'd' : $verb . 'ed';
	}
	$past_tense_verb_upper = ucfirst( $past_tense_verb );
	if ( $failures ) {
		if ( $successes ) {
			WP_CLI::error( "Only {$past_tense_verb} {$successes} of {$total} {$plural_noun}." );
		} else {
			WP_CLI::error( "No {$plural_noun} {$past_tense_verb}." );
		}
	} else {
		if ( $successes ) {
			WP_CLI::success( "{$past_tense_verb_upper} {$successes} of {$total} {$plural_noun}." );
		} else {
			$message = $total > 1 ? ucfirst( $plural_noun ) : ucfirst( $noun );
			WP_CLI::success( "{$message} already {$past_tense_verb}." );
		}
	}
}

/**
 * Parse a string of command line arguments into an $argv-esqe variable.
 *
 * @access public
 * @category Input
 *
 * @param string $arguments
 * @return array
 */
function parse_str_to_argv( $arguments ) {
	preg_match_all ('/(?<=^|\s)([\'"]?)(.+?)(?<!\\\\)\1(?=$|\s)/', $arguments, $matches );
	$argv = isset( $matches[0] ) ? $matches[0] : array();
	$argv = array_map( function( $arg ){
		foreach( array( '"', "'" ) as $char ) {
			if ( $char === substr( $arg, 0, 1 ) && $char === substr( $arg, -1 ) ) {
				$arg = substr( $arg, 1, -1 );
				break;
			}
		}
		return $arg;
	}, $argv );
	return $argv;
}

/**
 * Locale-independent version of basename()
 *
 * @access public
 *
 * @param string $path
 * @param string $suffix
 * @return string
 */
function basename( $path, $suffix = '' ) {
	return urldecode( \basename( str_replace( array( '%2F', '%5C' ), '/', urlencode( $path ) ), $suffix ) );
}

/**
 * Checks whether the output of the current script is a TTY or a pipe / redirect
 *
 * Returns true if STDOUT output is being redirected to a pipe or a file; false is
 * output is being sent directly to the terminal.
 *
 * If an env variable SHELL_PIPE exists, returned result depends it's
 * value. Strings like 1, 0, yes, no, that validate to booleans are accepted.
 *
 * To enable ASCII formatting even when shell is piped, use the
 * ENV variable SHELL_PIPE=0
 *
 * @access public
 *
 * @return bool
 */
function isPiped() {
	$shellPipe = getenv('SHELL_PIPE');

	if ($shellPipe !== false) {
		return filter_var($shellPipe, FILTER_VALIDATE_BOOLEAN);
	} else {
		return (function_exists('posix_isatty') && !posix_isatty(STDOUT));
	}
}

/**
 * Expand within paths to their matching paths.
 *
 * Has no effect on paths which do not use glob patterns.
 *
 * @param string|array $paths Single path as a string, or an array of paths.
 * @param int          $flags Flags to pass to glob.
 *
 * @return array Expanded paths.
 */
function expand_globs( $paths, $flags = GLOB_BRACE ) {
	$expanded = array();

	foreach ( (array) $paths as $path ) {
		$matching = array( $path );

		if ( preg_match( '/[' . preg_quote( '*?[]{}!', '/' ) . ']/', $path ) ) {
			$matching = glob( $path, $flags ) ?: array();
		}

		$expanded = array_merge( $expanded, $matching );
	}

	return array_unique( $expanded );
}

/**
 * Get the closest suggestion for a mis-typed target term amongst a list of
 * options.
 *
 * Uses the Levenshtein algorithm to calculate the relative "distance" between
 * terms.
 *
 * If the "distance" to the closest term is higher than the threshold, an empty
 * string is returned.
 *
 * @param string $target    Target term to get a suggestion for.
 * @param array  $options   Array with possible options.
 * @param int    $threshold Threshold above which to return an empty string.
 *
 * @return string
 */
function get_suggestion( $target, array $options, $threshold = 2 ) {
	if ( empty( $options ) ) {
		return '';
	}
	foreach ( $options as $option ) {
		$distance = levenshtein( $option, $target );
		$levenshtein[ $option ] = $distance;
	}

	// Sort known command strings by distance to user entry.
	asort( $levenshtein );

	// Fetch the closest command string.
	reset( $levenshtein );
	$suggestion = key( $levenshtein );

	// Only return a suggestion if below a given threshold.
	return $levenshtein[ $suggestion ] <= $threshold && $suggestion !== $target
		? (string) $suggestion
		: '';
}

/**
 * Get a Phar-safe version of a path.
 *
 * For paths inside a Phar, this strips the outer filesystem's location to
 * reduce the path to what it needs to be within the Phar archive.
 *
 * Use the __FILE__ or __DIR__ constants as a starting point.
 *
 * @param string $path An absolute path that might be within a Phar.
 *
 * @return string A Phar-safe version of the path.
 */
function phar_safe_path( $path ) {

	if ( ! inside_phar() ) {
		return $path;
	}

	return str_replace(
		PHAR_STREAM_PREFIX . WP_CLI_PHAR_PATH . '/',
		PHAR_STREAM_PREFIX,
		$path
	);
}

/**
 * Check whether a given Command object is part of the bundled set of
 * commands.
 *
 * This function accepts both a fully qualified class name as a string as
 * well as an object that extends `WP_CLI\Dispatcher\CompositeCommand`.
 *
 * @param \WP_CLI\Dispatcher\CompositeCommand|string $command
 *
 * @return bool
 */
function is_bundled_command( $command ) {
	static $classes;

	if ( null === $classes ) {
		$classes = array();
		$class_map = WP_CLI_VENDOR_DIR . '/composer/autoload_commands_classmap.php';
		if ( file_exists( WP_CLI_VENDOR_DIR . '/composer/') ) {
			$classes = include $class_map;
		}
	}

	if ( is_object( $command ) ) {
		$command = get_class( $command );
	}

	return is_string( $command )
		? array_key_exists( $command, $classes )
		: false;
}

/**
 * Maybe prefix command string with "/usr/bin/env".
 * Removes (if there) if Windows, adds (if not there) if not.
 *
 * @param string $command
 *
 * @return string
 */
function force_env_on_nix_systems( $command ) {
	$env_prefix = '/usr/bin/env ';
	$env_prefix_len = strlen( $env_prefix );
	if ( is_windows() ) {
		if ( 0 === strncmp( $command, $env_prefix, $env_prefix_len ) ) {
			$command = substr( $command, $env_prefix_len );
		}
	} else {
		if ( 0 !== strncmp( $command, $env_prefix, $env_prefix_len ) ) {
			$command = $env_prefix . $command;
		}
	}
	return $command;
}

/**
 * Check that `proc_open()` and `proc_close()` haven't been disabled.
 *
 * @param string $context Optional. If set will appear in error message. Default null.
 * @param bool   $return  Optional. If set will return false rather than error out. Default false.
 *
 * @return bool
 */
function check_proc_available( $context = null, $return = false ) {
	if ( ! function_exists( 'proc_open' ) || ! function_exists( 'proc_close' ) ) {
		if ( $return ) {
			return false;
		}
		$msg = 'The PHP functions `proc_open()` and/or `proc_close()` are disabled. Please check your PHP ini directive `disable_functions` or suhosin settings.';
		if ( $context ) {
			WP_CLI::error( sprintf( "Cannot do '%s': %s", $context, $msg ) );
		} else {
			WP_CLI::error( $msg );
		}
	}
	return true;
}
