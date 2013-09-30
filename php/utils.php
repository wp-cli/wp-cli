<?php

// Utilities that do NOT depend on WordPress code.

namespace WP_CLI\Utils;

use \WP_CLI\Dispatcher;
use \WP_CLI\Iterators\Transform;

function load_dependencies() {
	if ( 0 === strpos( WP_CLI_ROOT, 'phar:' ) ) {
		require WP_CLI_ROOT . '/vendor/autoload.php';
		return;
	}

	$vendor_paths = array(
		WP_CLI_ROOT . '/../../../vendor',  // part of a larger project / installed via Composer (preferred)
		WP_CLI_ROOT . '/vendor',           // top-level project / installed as Git clone
	);

	$has_autoload = false;

	foreach ( $vendor_paths as $vendor_path ) {
		if ( file_exists( $vendor_path . '/autoload.php' ) ) {
			require $vendor_path . '/autoload.php';
			$has_autoload = true;
			break;
		}
	}

	if ( !$has_autoload ) {
		fputs( STDERR, "Internal error: Can't find Composer autoloader.\n" );
		exit(3);
	}
}

function load_command( $name ) {
	$path = WP_CLI_ROOT . "/php/commands/$name.php";

	if ( is_readable( $path ) ) {
		include_once $path;
	}
}

function load_all_commands() {
	$cmd_dir = WP_CLI_ROOT . '/php/commands';

	$iterator = new \DirectoryIterator( $cmd_dir );

	foreach ( $iterator as $filename ) {
		if ( '.php' != substr( $filename, -4 ) )
			continue;

		include_once "$cmd_dir/$filename";
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
	while ( is_readable( $dir ) ) {
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
	if ( ':' === $path[1] )
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
		if ( true === $value )
			$str .= " --$key";
		else
			$str .= " --$key=" . escapeshellarg( $value );
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

/**
 * Take a serialised array and unserialise it replacing elements as needed and
 * unserialising any subordinate arrays and performing the replace on those too.
 * Will only handle the first level of any object to avoid recursions.
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
			$_tmp = array();
			foreach ( $data as $key => $value ) {
				$_tmp[ $key ] = recursive_unserialize_replace( $from, $to, $value, false );
			}

			$data = $_tmp;
		}

		elseif ( is_object( $data ) ) {
			$_tmp = clone( $data );
			foreach ( $data as $key => $value ) {

                if ( is_string( $value ) ) {
                    $data[$key] = str_replace( $from, $to, $value );
                }

                elseif ( is_array( $value ) ) {
                    $_tmp = array();
                    foreach ( $value as $key => $sub_value ) {
                        $_tmp[ $key ] = str_replace( $from, $to, $sub_value );
                    }

                    $value = $_tmp;
                }

			}

			$data = $_tmp;
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

/**
 * Output items in a table, JSON, CSV, ids, or the total count
 *
 * @param string        $format     Format to use: 'table', 'json', 'csv', 'ids', 'count'
 * @param array         $items      Data to output
 * @param array|string  $fields     Named fields for each item of data. Can be array or comma-separated list
 */
function format_items( $format, $items, $fields ) {
	if ( ! is_array( $fields ) )
		$fields = explode( ',', $fields );

	switch ( $format ) {
		case 'count':
			if ( !is_array( $items ) ) {
				$items = iterator_to_array( $items );
			}
			echo count( $items );
			break;

		case 'ids':
			if ( !is_array( $items ) ) {
				$items = iterator_to_array( $items );
			}
			echo implode( ' ', $items );
			break;

		case 'table':
			$table = new \cli\Table();

			$table->setHeaders( $fields );

			foreach ( $items as $item ) {
				$table->addRow( array_values( pick_fields( $item, $fields ) ) );
			}

			$table->display();
			break;

		case 'csv':
			write_csv( STDOUT, $items, $fields );
			break;

		case 'json':
			$out = array();
			foreach ( $items as $item ) {
				$out[] = pick_fields( $item, $fields );
			}

			echo json_encode( $out );
			break;
	}
}

/**
 * Format an associative array as a table
 *
 * @param array     $fields    Fields and values to format
 */
function assoc_array_to_table( $fields ) {
	$rows = array();

	foreach ( $fields as $field => $value ) {
		if ( ! is_string( $value ) ) {
			$value = json_encode( $value );
		}

		$rows[] = (object) array(
			'Field' => $field,
			'Value' => $value
		);
	}

	format_items( 'table', $rows, array( 'Field', 'Value' ) );
}

/**
 * Write data as CSV to a given file.
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
 * Launch system's $EDITOR to edit text
 *
 * @param  str  $content  Text to edit (eg post content)
 * @return str|bool       Edited text, if file is saved from editor
 *                        False, if no change to file
 */
function launch_editor_for_input( $input, $title = 'WP-CLI' ) {

	$tmpfile = wp_tempnam( $title );

	if ( !$tmpfile )
		\WP_CLI::error( 'Error creating temporary file.' );

	file_put_contents( $tmpfile, $input );

	\WP_CLI::launch( "\${EDITOR:-vi} '$tmpfile'" );

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
	if ( !$descriptors )
		$descriptors = array( STDIN, STDOUT, STDERR );

	if ( isset( $assoc_args['host'] ) ) {
		$assoc_args = array_merge( $assoc_args, mysql_host_to_cli_args( $assoc_args['host'] ) );
	}

	$env = (array) $_ENV;
	if ( isset( $assoc_args['pass'] ) ) {
		$env['MYSQL_PWD'] = $assoc_args['pass'];
		unset( $assoc_args['pass'] );
	}

	$final_cmd = $cmd . assoc_args_to_str( $assoc_args );

	$proc = proc_open( $final_cmd, $descriptors, $pipes, null, $env );
	if ( !$proc )
		exit(1);

	$r = proc_close( $proc );

	if ( $r ) exit( $r );
}

function mustache_render( $template_name, $data ) {
	$template = file_get_contents( WP_CLI_ROOT . "/templates/$template_name" );

	$m = new \Mustache_Engine;

	return $m->render( $template, $data );
}

function make_progress_bar( $message, $count ) {
	if ( \cli\Shell::isPiped() )
		return new \WP_CLI\NoOp;

	return new \cli\progress\Bar( $message, $count );
}

