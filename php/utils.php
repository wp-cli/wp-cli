<?php

// Utilities that do NOT depend on WordPress code.

namespace WP_CLI\Utils;

use ArrayIterator;
use cli;
use cli\progress\Bar;
use cli\Shell;
use Closure;
use Composer\Semver\Comparator;
use Composer\Semver\Semver;
use Exception;
use Iterator;
use Mustache\Engine as Mustache_Engine;
use ReflectionFunction;
use RuntimeException;
use WP_CLI;
use WP_CLI\ExitException;
use WP_CLI\Formatter;
use WP_CLI\Inflector;
use WP_CLI\Iterators\Transform;
use WP_CLI\NoOp;
use WP_CLI\Process;
use WP_CLI\RequestsLibrary;
use WpOrg\Requests\Response;

/**
 * File stream wrapper prefix for Phar archives.
 *
 * @var string
 */
const PHAR_STREAM_PREFIX = 'phar://';

/**
 * Regular expression pattern to match __FILE__ and __DIR__ constants.
 *
 * We try to be smart and only replace the constants when they are not within quotes.
 * Regular expressions being stateless, this is probably not 100% correct for edge cases.
 *
 * @see https://regex101.com/r/9hXp5d/11
 * @see https://stackoverflow.com/a/171499/933065
 *
 * @var string
 */
const FILE_DIR_PATTERN = '%(?>#.*?$)|(?>//.*?$)|(?>/\*.*?\*/)|(?>\'(?:(?=(\\\\?))\1.)*?\')|(?>"(?:(?=(\\\\?))\2.)*?")|(?<file>\b__FILE__\b)|(?<dir>\b__DIR__\b)%ms';

/**
 * Check if a certain path is within a Phar archive.
 *
 * If no path is provided, the function checks whether the current WP_CLI instance is
 * running from within a Phar archive.
 *
 * @param string|null $path Optional. Path to check. Defaults to null, which checks WP_CLI_ROOT.
 * @return bool Whether path is within a Phar archive.
 */
function inside_phar( $path = null ) {
	if ( null === $path ) {
		if ( ! defined( 'WP_CLI_ROOT' ) ) {
			return false;
		}

		$path = WP_CLI_ROOT;
	}

	return 0 === strpos( $path, PHAR_STREAM_PREFIX );
}

/**
 * Extract a file from a Phar archive.
 *
 * Files that need to be read by external programs have to be extracted from the Phar archive.
 * If the file is not within a Phar archive, the function returns the path unchanged.
 *
 * @param string $path Path to the file to extract.
 * @return string Path to the extracted file.
 */
function extract_from_phar( $path ) {
	if ( ! inside_phar( $path ) ) {
		return $path;
	}

	$fname = basename( $path );

	$tmp_path = get_temp_dir() . uniqid( 'wp-cli-extract-from-phar-', true ) . "-$fname";

	copy( $path, $tmp_path );

	register_shutdown_function(
		function () use ( $tmp_path ) {
			if ( file_exists( $tmp_path ) ) {
				unlink( $tmp_path );
			}
		}
	);

	return $tmp_path;
}

/**
 * Load dependencies.
 *
 * @return void|never
 */
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

	if ( ! $has_autoload ) {
		fwrite( STDERR, "Internal error: Can't find Composer autoloader.\nTry running: composer install\n" );
		exit( 3 );
	}
}

/**
 * Return vendor paths.
 *
 * @return array<string> List of paths.
 */
function get_vendor_paths() {
	$vendor_paths        = [
		WP_CLI_ROOT . '/../../../vendor',  // Part of a larger project / installed via Composer (preferred).
		WP_CLI_ROOT . '/vendor',           // Top-level project / installed as Git clone.
	];
	$maybe_composer_json = WP_CLI_ROOT . '/../../../composer.json';
	if ( file_exists( $maybe_composer_json ) && is_readable( $maybe_composer_json ) ) {
		/**
		 * @var object{config: object{'vendor-dir': string}} $composer
		 */
		$composer = json_decode( (string) file_get_contents( $maybe_composer_json ), false );
		if ( ! empty( $composer->config ) && ! empty( $composer->config->{'vendor-dir'} ) ) {
			array_unshift( $vendor_paths, WP_CLI_ROOT . '/../../../' . $composer->config->{'vendor-dir'} );
		}
	}
	return $vendor_paths;
}

/**
 * Load a file.
 *
 * Using require() directly inside a class grants access
 * to private methods to the loaded code, hence this wrapper helper.
 *
 * @param string $path
 * @return void
 */
function load_file( $path ) {
	require_once $path;
}

/**
 * Load a command.
 *
 * @param string $name
 * @return void
 */
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
 * @param array|Iterator $it     Either a plain array or another iterator.
 * @param callable       ...$fns The function to apply to an element.
 * @return Iterator An iterator that applies the given callback(s).
 */
function iterator_map( $it, ...$fns ) {
	if ( is_array( $it ) ) {
		$it = new ArrayIterator( $it );
	}

	if ( ! method_exists( $it, 'add_transform' ) ) {
		$it = new Transform( $it );
	}

	foreach ( $fns as $fn ) {
		/**
		 * @var Transform $it
		 */
		$it->add_transform( $fn );
	}

	return $it;
}

/**
 * Search for file by walking up the directory tree until the first file is found or until $stop_check($dir) returns true.
 *
 * @param string|array<string> $files      The files (or file) to search for.
 * @param string|null          $dir        The directory to start searching from; defaults to CWD.
 * @param callable             $stop_check Function which is passed the current dir each time a directory level is traversed.
 * @return null|string Null if the file was not found.
 */
function find_file_upward( $files, $dir = null, $stop_check = null ) {
	$files = (array) $files;
	if ( is_null( $dir ) ) {
		$dir = getcwd();
	}
	while ( $dir && is_readable( $dir ) ) {
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
		if ( empty( $parent_dir ) || $parent_dir === $dir ) {
			break;
		}
		$dir = $parent_dir;
	}
	return null;
}

/**
 * Determine whether a path is absolute.
 * @param string $path
 * @return bool
 */
function is_path_absolute( $path ) {
	// Empty path is not absolute.
	if ( '' === $path ) {
		return false;
	}
	// Windows drive letter + colon + slash or backslash.
	if ( preg_match( '#^[A-Z]:[\\\\/]#i', $path ) ) {
		return true;
	}

	// UNC path (\\Server\Share).
	if ( preg_match( '#^\\\\\\\\[^\\\\/]+[\\\\/][^\\\\/]+#', $path ) ) {
		return true;
	}

	// Unix root.
	return isset( $path[0] ) && '/' === $path[0];
}

/**
 * Expand tilde (~) in path to home directory.
 *
 * Expands paths that start with ~ to the current user's home directory.
 * Only handles the current user's home directory (not ~username patterns).
 *
 * @param string $path Path that may contain a tilde.
 * @return string Path with tilde expanded to home directory, or unchanged if tilde not at start or followed by username.
 */
function expand_tilde_path( $path ) {
	// Check if path starts with tilde
	if ( isset( $path[0] ) && '~' === $path[0] ) {
		$home = get_home_dir();
		// Only expand if we can determine the home directory
		// Handle both "~" and "~/..." patterns (but not "~username")
		if ( ! empty( $home ) && ( 1 === strlen( $path ) || '/' === $path[1] ) ) {
			$path = $home . substr( $path, 1 );
		}
		// If followed by anything other than '/', or home is empty, leave it unchanged
	}

	return $path;
}

/**
 * Composes positional arguments into a command string.
 *
 * @param array<string> $args Positional arguments to compose.
 * @return string
 */
function args_to_str( $args ) {
	return ' ' . implode( ' ', array_map( 'escapeshellarg', array_map( 'strval', $args ) ) );
}

/**
 * Composes associative arguments into a command string.
 *
 * @param array<string, array<int, string>|string|true|int> $assoc_args Associative arguments to compose.
 * @param array<string> $sensitive_args Optional. Array of argument keys that should be masked.
 * @return string
 */
function assoc_args_to_str( $assoc_args, $sensitive_args = [] ) {
	$str = '';

	foreach ( $assoc_args as $key => $value ) {
		if ( true === $value ) {
			$str .= " --$key";
		} elseif ( is_array( $value ) ) {
			foreach ( $value as $v ) {
				$str .= assoc_args_to_str(
					[
						$key => $v,
					],
					$sensitive_args
				);
			}
		} elseif ( in_array( $key, $sensitive_args, true ) ) {
			// Mask the value if this is a sensitive argument
			$str .= " --$key=" . escapeshellarg( '[REDACTED]' );
		} else {
			$str .= " --$key=" . escapeshellarg( (string) $value );
		}
	}

	return $str;
}

/**
 * Given a template string and an arbitrary number of arguments,
 * returns the final command, with the parameters escaped.
 *
 * @param string $cmd
 * @param string ...$args
 */
function esc_cmd( $cmd, ...$args ) {
	if ( func_num_args() < 2 ) {
		trigger_error( 'esc_cmd() requires at least two arguments.', E_USER_WARNING );
	}

	return vsprintf( $cmd, array_map( 'escapeshellarg', $args ) );
}

/**
 * Gets path to WordPress configuration.
 *
 * @return string
 */
function locate_wp_config() {
	static $path;

	if ( null === $path ) {
		$path = false;

		$config_path = (string) getenv( 'WP_CONFIG_PATH' );

		if ( $config_path && file_exists( $config_path ) ) {
			$path = $config_path;
		} elseif ( file_exists( ABSPATH . 'wp-config.php' ) ) {
			$path = ABSPATH . 'wp-config.php';
		} elseif ( file_exists( dirname( ABSPATH ) . '/wp-config.php' ) && ! file_exists( dirname( ABSPATH ) . '/wp-settings.php' ) ) {
			$path = dirname( ABSPATH ) . '/wp-config.php';
		}

		if ( $path ) {
			$path = realpath( $path );
		}
	}

	return $path;
}

/**
 * Compare a WordPress version.
 *
 * @param string $since
 * @param string $operator
 * @return bool
 */
function wp_version_compare( $since, $operator ) {
	/**
	 * @var string $wp_version
	 */
	$wp_version = $GLOBALS['wp_version'];
	$wp_version = str_replace( '-src', '', $wp_version );
	$since      = str_replace( '-src', '', $since );
	return version_compare( $wp_version, $since, $operator );
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
 * @param string       $format Format to use: 'table', 'json', 'csv', 'yaml', 'ids', 'count'.
 * @param array<mixed> $items  An array of items to output.
 * @param array<string>|string $fields Named fields for each item of data. Can be array or comma-separated list.
 */
function format_items( $format, $items, $fields ) {
	$assoc_args = [
		'format' => $format,
		'fields' => $fields,
	];
	$formatter  = new Formatter( $assoc_args );
	$formatter->display_items( $items );
}

/**
 * Write data as CSV to a given file.
 *
 * @access public
 *
 * @param resource                 $fd      File descriptor.
 * @param array<string[]>|iterable $rows    Array of rows to output.
 * @param array<string>            $headers List of CSV columns (optional).
 */
function write_csv( $fd, $rows, $headers = [] ) {
	if ( ! empty( $headers ) ) {
		$headers = array_map( __NAMESPACE__ . '\escape_csv_value', $headers );
		fputcsv( $fd, $headers, ',', '"', '\\' );
	}

	/**
	 * @var string[] $row
	 */
	foreach ( $rows as $row ) {
		if ( ! empty( $headers ) ) {
			$row = pick_fields( $row, $headers );
		}

		/**
		 * @var string[] $row
		 * @var callable $callback
		 */

		$callback = __NAMESPACE__ . '\escape_csv_value';
		$row      = array_map( $callback, $row );
		fputcsv( $fd, array_values( $row ), ',', '"', '\\' );
	}
}

/**
 * Pick fields from an associative array or object.
 *
 * @param array<string, mixed>|object $item   Associative array or object to pick fields from.
 * @param array<string>               $fields List of fields to pick.
 * @return array<string, mixed>
 */
function pick_fields( $item, $fields ) {
	$values = [];

	if ( is_object( $item ) ) {
		foreach ( $fields as $field ) {
			$values[ $field ] = isset( $item->$field ) ? $item->$field : null;
		}
	} else {
		foreach ( $fields as $field ) {
			$values[ $field ] = isset( $item[ $field ] ) ? $item[ $field ] : null;
		}
	}

	return $values;
}

/**
 * Launch system's $EDITOR for the user to edit some text.
 *
 * @access public
 * @category Input
 *
 * @param string $input Some form of text to edit (e.g. post content).
 * @param string $title Title to display in the editor.
 * @param string $ext   Extension to use with the temp file.
 * @return string|bool  Edited text, if file is saved from editor; false, if no change to file.
 */
function launch_editor_for_input( $input, $title = 'WP-CLI', $ext = 'tmp' ) {

	check_proc_available( 'launch_editor_for_input' );

	$tmpdir = get_temp_dir();

	do {
		$tmpfile  = basename( $title );
		$tmpfile  = preg_replace( '|\.[^.]*$|', '', $tmpfile );
		$tmpfile .= '-' . substr( md5( (string) mt_rand() ), 0, 6 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.rand_mt_rand -- no crypto and WP not loaded.
		$tmpfile  = $tmpdir . $tmpfile . '.' . $ext;
		$fp       = fopen( $tmpfile, 'xb' );
		if ( ! $fp && is_writable( $tmpdir ) && file_exists( $tmpfile ) ) {
			$tmpfile = '';
			continue;
		}
		if ( $fp ) {
			fclose( $fp );
		}
	} while ( ! $tmpfile );

	// @phpstan-ignore booleanNot.alwaysFalse
	if ( ! $tmpfile ) {
		WP_CLI::error( 'Error creating temporary file.' );
	}

	file_put_contents( $tmpfile, $input );

	$editor = getenv( 'EDITOR' );
	if ( ! $editor ) {
		$editor = is_windows() ? 'notepad' : 'vi';
	}

	$descriptorspec = [ STDIN, STDOUT, STDERR ];
	$process        = proc_open_compat( "$editor " . escapeshellarg( $tmpfile ), $descriptorspec, $pipes );
	if ( $process ) {
		$r = proc_close( $process );
		if ( $r ) {
			exit( $r );
		}
	}

	$output = file_get_contents( $tmpfile );

	unlink( $tmpfile );

	if ( $output === $input ) {
		return false;
	}

	return $output;
}

/**
 * @param string $raw_host MySQL host string, as defined in wp-config.php.
 *
 * @return array<string, string|int>
 */
function mysql_host_to_cli_args( $raw_host ) {
	$assoc_args = [];

	/**
	 * If the host string begins with 'p:' for a persistent db connection,
	 * replace 'p:' with nothing.
	 */
	if ( substr( $raw_host, 0, 2 ) === 'p:' ) {
		$raw_host = substr_replace( $raw_host, '', 0, 2 );
	}

	$host_parts = explode( ':', $raw_host );
	if ( count( $host_parts ) === 2 ) {
		list( $assoc_args['host'], $extra ) = $host_parts;
		$extra                              = trim( $extra );
		if ( is_numeric( $extra ) ) {
			$assoc_args['port']     = (int) $extra;
			$assoc_args['protocol'] = 'tcp';
		} elseif ( '' !== $extra ) {
			$assoc_args['socket'] = $extra;
		}
	} else {
		$assoc_args['host'] = $raw_host;
	}

	return $assoc_args;
}

/**
 * Run a MySQL command and optionally return the output.
 *
 * @since v2.5.0 Deprecated $descriptors argument.
 *
 * @param string                $cmd           Command to run.
 * @param array<string, string> $assoc_args    Associative array of arguments to use.
 * @param mixed                 $_             Deprecated. Former $descriptors argument.
 * @param bool                  $send_to_shell Optional. Whether to send STDOUT and STDERR
 *                                             immediately to the shell. Defaults to true.
 * @param bool                  $interactive   Optional. Whether MySQL is meant to be
 *                                             executed as an interactive process. Defaults
 *                                             to false.
 *
 * @return array {
 *     Associative array containing STDOUT and STDERR output.
 *
 *     @type string $stdout    Output that was sent to STDOUT.
 *     @type string $stderr    Output that was sent to STDERR.
 *     @type int    $exit_code Exit code of the process.
 * }
 */
function run_mysql_command( $cmd, $assoc_args, $_ = null, $send_to_shell = true, $interactive = false ) {
	check_proc_available( 'run_mysql_command' );

	/**
	 * @var array<resource> $descriptors
	 */
	$descriptors = ( $interactive || $send_to_shell ) ?
		[
			0 => STDIN,
			1 => STDOUT,
			2 => STDERR,
		] :
		[
			0 => STDIN,
			1 => [ 'pipe', 'w' ],
			2 => [ 'pipe', 'w' ],
		];

	$stdout = '';
	$stderr = '';

	/**
	 * @var array<int, resource> $pipes
	 */
	$pipes = [];

	if ( isset( $assoc_args['host'] ) ) {
		// phpcs:ignore WordPress.DB.RestrictedFunctions.mysql_mysql_host_to_cli_args -- Misidentified as PHP native MySQL function.
		$assoc_args = array_merge( $assoc_args, mysql_host_to_cli_args( $assoc_args['host'] ) );
	}

	if ( isset( $assoc_args['pass'] ) ) {
		$old_password = getenv( 'MYSQL_PWD' );
		putenv( 'MYSQL_PWD=' . $assoc_args['pass'] );
		unset( $assoc_args['pass'] );
	}

	$final_cmd = force_env_on_nix_systems( $cmd ) . assoc_args_to_str( $assoc_args );

	WP_CLI::debug( 'Final MySQL command: ' . $final_cmd, 'db' );
	$process = proc_open_compat( $final_cmd, $descriptors, $pipes );

	if ( isset( $old_password ) ) {
		putenv( 'MYSQL_PWD=' . $old_password );
	}

	if ( ! $process ) {
		WP_CLI::debug( 'Failed to create a valid process using proc_open_compat()', 'db' );
		exit( 1 );
	}

	if ( is_resource( $process ) && ! $send_to_shell && ! $interactive ) {
		$stdout = stream_get_contents( $pipes[1] );
		$stderr = stream_get_contents( $pipes[2] );

		fclose( $pipes[1] );
		fclose( $pipes[2] );
	}

	$exit_code = proc_close( $process );

	if ( $exit_code && ( $send_to_shell || $interactive ) ) {
		exit( $exit_code );
	}

	return [
		$stdout,
		$stderr,
		$exit_code,
	];
}

/**
 * Render PHP or other types of files using Mustache templates.
 *
 * IMPORTANT: Automatic HTML escaping is disabled!
 *
 * @param string               $template_name
 * @param array<string, mixed> $data
 */
function mustache_render( $template_name, $data = [] ) {
	if ( ! file_exists( $template_name ) ) {
		$template_name = WP_CLI_ROOT . "/templates/$template_name";
	}

	$template = (string) file_get_contents( $template_name );

	$mustache = new Mustache_Engine(
		[
			'escape' => function ( $val ) {
				return $val; },
		]
	);

	return $mustache->render( $template, $data );
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
 * @param int     $interval Optional. The interval in milliseconds between updates. Default 100.
 * @return \cli\progress\Bar|\WP_CLI\NoOp
 */
function make_progress_bar( $message, $count, $interval = 100 ) {
	if ( Shell::isPiped() ) {
		return new NoOp();
	}

	return new Bar( $message, $count, $interval );
}

/**
 * Helper function to use wp_parse_url when available or fall back to PHP's
 * parse_url if not.
 *
 * Additionally, this adds 'http://' to the URL if no scheme was found.
 *
 * @param string $url             The URL to parse.
 * @param int    $component       Optional. The specific component to retrieve.
 *                                Use one of the PHP predefined constants to
 *                                specify which one. Defaults to -1 (= return
 *                                all parts as an array).
 * @param bool   $auto_add_scheme Optional. Automatically add an http:// scheme if
 *                                none was found. Defaults to true.
 * @return mixed False on parse failure; Array of URL components on success;
 *               When a specific component has been requested: null if the
 *               component doesn't exist in the given URL; a string or - in the
 *               case of PHP_URL_PORT - integer when it does. See parse_url()'s
 *               return values.
 *
 * @phpstan-return ($component is non-negative-int ? string|null|int|false : array{scheme?: string, host?: string, port?: int, user?: string, pass?: string, query?: string, path?: string, fragment?: string})
 */
function parse_url( $url, $component = - 1, $auto_add_scheme = true ) {
	if ( function_exists( 'wp_parse_url' ) ) {
		$url_parts = wp_parse_url( $url, $component );
	} else {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- Fallback.
		$url_parts = \parse_url( $url, $component );
	}

	// phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- Own version based on WP one.
	if ( $auto_add_scheme && ! parse_url( $url, PHP_URL_SCHEME, false ) ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- Own version based on WP one.
		$url_parts = parse_url( 'http://' . $url, $component, false );
	}

	return $url_parts;
}

/**
 * Check if we're running in a Windows environment (cmd.exe).
 *
 * @return bool
 */
function is_windows() {
	$test_is_windows = getenv( 'WP_CLI_TEST_IS_WINDOWS' );
	return false !== $test_is_windows ? (bool) $test_is_windows : strtoupper( substr( PHP_OS, 0, 3 ) ) === 'WIN';
}

/**
 * Replace magic constants in some PHP source code.
 *
 * Replaces the __FILE__ and __DIR__ magic constants with the values they are
 * supposed to represent at runtime.
 *
 * @param string $source The PHP code to manipulate.
 * @param string $path The path to use instead of the magic constants.
 * @return string Adapted PHP code.
 */
function replace_path_consts( $source, $path ) {
	// Solve issue with Windows allowing single quotes in account names.
	$file = addslashes( $path );

	if ( file_exists( $file ) ) {
		$file = (string) realpath( $file );
	}

	$dir = dirname( $file );

	// Replace __FILE__ and __DIR__ constants with value of $file or $dir.
	return (string) preg_replace_callback(
		FILE_DIR_PATTERN,
		static function ( $matches ) use ( $file, $dir ) {
			if ( ! empty( $matches['file'] ) ) {
				return "'{$file}'";
			}

			if ( ! empty( $matches['dir'] ) ) {
				return "'{$dir}'";
			}

			return $matches[0];
		},
		$source
	);
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
 * @param string     $method  HTTP method (GET, POST, DELETE, etc.).
 * @param string     $url     URL to make the HTTP request to.
 * @param array|null $data    Data to send either as a query string for GET/HEAD requests,
 *                            or in the body for POST requests.
 * @param array      $headers Add specific headers to the request.
 * @param array      $options {
 *     Optional. An associative array of additional request options.
 *
 *     @type bool $halt_on_error Whether or not command execution should be halted on error. Default: true
 *     @type bool|string $verify A boolean to use enable/disable SSL verification
 *                               or string absolute path to CA cert to use.
 *                               Defaults to detected CA cert bundled with the Requests library.
 *     @type bool $insecure      Whether to retry automatically without certificate validation.
 *     @type int  $max_retries   Maximum number of retries of failed requests. Default 3.
 * }
 * @return \Requests_Response|Response
 * @throws RuntimeException If the request failed.
 * @throws ExitException If the request failed and $halt_on_error is true.
 *
 * @phpstan-param array{halt_on_error?: bool, verify?: bool|string, insecure?: bool} $options
 */
function http_request( $method, $url, $data = null, $headers = [], $options = [] ) {
	$insecure      = isset( $options['insecure'] ) && (bool) $options['insecure'];
	$halt_on_error = ! isset( $options['halt_on_error'] ) || (bool) $options['halt_on_error'];
	$max_retries   = isset( $options['max_retries'] ) ? (int) $options['max_retries'] : 3;
	unset( $options['halt_on_error'] );

	if ( ! isset( $options['verify'] ) ) {
		// 'curl.cainfo' enforces the CA file to use, otherwise fallback to system-wide defaults then use the embedded CA file.
		$options['verify'] = ! empty( ini_get( 'curl.cainfo' ) ) ? ini_get( 'curl.cainfo' ) : true;
	}

	/**
	 * @var array{halt_on_error?: bool, verify: bool|string, insecure?: bool} $options
	 */
	$options = WP_CLI::do_hook( 'http_request_options', $options, $method, $url, $data, $headers );

	RequestsLibrary::register_autoloader();

	/**
	 * @var callable $request_method
	 */
	$request_method = [ RequestsLibrary::get_class_name(), 'request' ];

	$attempt           = 0;
	$last_exception    = null;
	$retry_after_delay = 1; // Start with 1 second delay.

	while ( $attempt < $max_retries ) {
		++$attempt;
		try {
			try {
				return $request_method( $url, $headers, $data, $method, $options );
			} catch ( \Requests_Exception | \WpOrg\Requests\Exception $exception ) {
				$curl_handle = $exception->getData();
				// Get curl error code safely - only if curl is available and handle is valid.
				$curl_errno = null;
				if ( function_exists( 'curl_errno' ) && ( is_resource( $curl_handle ) || ( is_object( $curl_handle ) && $curl_handle instanceof \CurlHandle ) ) ) {
					// @phpstan-ignore argument.type
					$curl_errno = curl_errno( $curl_handle );
				}
				// CURLE_SSL_CACERT = 60
				$is_ssl_cacert_error = null !== $curl_errno && 60 === $curl_errno;

				if (
					true !== $options['verify']
					|| 'curlerror' !== $exception->getType()
					|| ! $is_ssl_cacert_error
				) {
					throw $exception;
				}

				$options['verify'] = get_default_cacert( $halt_on_error );

				return $request_method( $url, $headers, $data, $method, $options );
			}
		} catch ( \Requests_Exception | \WpOrg\Requests\Exception $exception ) {
			$curl_handle = $exception->getData();
			// Get curl error code safely - only if curl is available and handle is valid.
			$curl_errno = null;
			if ( function_exists( 'curl_errno' ) && ( is_resource( $curl_handle ) || ( is_object( $curl_handle ) && $curl_handle instanceof \CurlHandle ) ) ) {
				// @phpstan-ignore argument.type
				$curl_errno = curl_errno( $curl_handle );
			}
			// CURLE_SSL_CONNECT_ERROR = 35, CURLE_SSL_CERTPROBLEM = 58, CURLE_SSL_CACERT_BADFILE = 77
			$is_ssl_error = null !== $curl_errno && in_array( $curl_errno, [ 35, 58, 77 ], true );

			// CURLE_COULDNT_RESOLVE_HOST = 6, CURLE_COULDNT_CONNECT = 7, CURLE_PARTIAL_FILE = 18
			// CURLE_OPERATION_TIMEDOUT = 28, CURLE_GOT_NOTHING = 52, CURLE_SEND_ERROR = 55, CURLE_RECV_ERROR = 56
			$is_transient_error = null !== $curl_errno && in_array( $curl_errno, [ 6, 7, 18, 28, 52, 55, 56 ], true );

			if (
				! $insecure
				||
				'curlerror' !== $exception->getType()
				||
				! $is_ssl_error
			) {
				// Check if this is a transient error that should be retried.
				if ( ! $is_transient_error || $attempt >= $max_retries ) {
					$error_msg = sprintf( "Failed to get url '%s': %s.", $url, $exception->getMessage() );
					if ( $halt_on_error ) {
						WP_CLI::error( $error_msg );
					}
					throw new RuntimeException( $error_msg, 0, $exception );
				}

				// Store exception and retry.
				$last_exception = $exception;
				WP_CLI::debug( sprintf( 'Retrying HTTP request to %s (retry %d/%d) after transient error: %s', $url, $attempt, $max_retries, $exception->getMessage() ), 'http' );
				sleep( $retry_after_delay );
				$retry_after_delay = min( $retry_after_delay * 2, 10 ); // Exponential backoff, max 10 seconds.
				continue;
			}

			$warning = sprintf(
				"Re-trying without verify after failing to get verified url '%s' %s.",
				$url,
				$exception->getMessage()
			);
			WP_CLI::warning( $warning );

			// Disable certificate validation for the next try.
			$options['verify'] = false;

			try {
				return $request_method( $url, $headers, $data, $method, $options );
			} catch ( \Requests_Exception | \WpOrg\Requests\Exception $retry_exception ) {
				// Check if this is a transient error that should be retried.
				$retry_curl_handle = $retry_exception->getData();
				$retry_curl_errno  = null;
				if ( function_exists( 'curl_errno' ) && ( is_resource( $retry_curl_handle ) || ( is_object( $retry_curl_handle ) && $retry_curl_handle instanceof \CurlHandle ) ) ) {
					// @phpstan-ignore argument.type
					$retry_curl_errno = curl_errno( $retry_curl_handle );
				}
				$is_retry_transient = null !== $retry_curl_errno && in_array( $retry_curl_errno, [ 6, 7, 18, 28, 52, 55, 56 ], true );

				if ( $is_retry_transient && $attempt < $max_retries ) {
					// Transient error, let the retry loop handle it.
					$last_exception = $retry_exception;
					WP_CLI::debug( sprintf( 'Retrying HTTP request to %s (retry %d/%d) after transient error: %s', $url, $attempt, $max_retries, $retry_exception->getMessage() ), 'http' );
					sleep( $retry_after_delay );
					$retry_after_delay = min( $retry_after_delay * 2, 10 ); // Exponential backoff, max 10 seconds.
					continue;
				}

				$error_msg = sprintf( "Failed to get non-verified url '%s' %s.", $url, $retry_exception->getMessage() );
				if ( $halt_on_error ) {
					WP_CLI::error( $error_msg );
				}
				throw new RuntimeException( $error_msg, 0, $retry_exception );
			}
		}
	}

	// All retries exhausted, throw the last exception.
	$error_msg = sprintf( "Failed to get url '%s' after %d attempts.", $url, $max_retries );
	if ( $halt_on_error ) {
		WP_CLI::error( $error_msg );
	}
	throw new RuntimeException( $error_msg, 0, $last_exception );
}

/**
 * Gets the full path to the default CA cert.
 *
 * @param bool $halt_on_error Whether or not command execution should be halted on error. Default: false
 * @return string Absolute path to the default CA cert.
 * @throws RuntimeException If unable to locate the cert.
 * @throws ExitException If unable to locate the cert and $halt_on_error is true.
 */
function get_default_cacert( $halt_on_error = false ) {
	$cert_path = RequestsLibrary::get_bundled_certificate_path();
	$error_msg = 'Cannot find SSL certificate.';

	if ( inside_phar( $cert_path ) ) {
		// cURL can't read Phar archives.
		return extract_from_phar( $cert_path );
	}

	if ( file_exists( $cert_path ) ) {
		return $cert_path;
	}

	if ( $halt_on_error ) {
		WP_CLI::error( $error_msg );
	}

	throw new RuntimeException( $error_msg );
}

/**
 * Increments a version string using the "x.y.z-pre" format.
 *
 * Can increment the major, minor or patch number by one.
 * If $new_version == "same" the version string is not changed.
 * If $new_version is not a known keyword, it will be used as the new version string directly.
 *
 * @param string $current_version
 * @param string $new_version
 * @return string
 */
function increment_version( $current_version, $new_version ) {
	// split version assuming the format is x.y.z-pre.
	$_current_version    = explode( '-', $current_version, 2 );
	$_current_version[0] = explode( '.', $_current_version[0] );

	$_current_version = array_slice( $_current_version, 0, 2 );

	/**
	 * @var array{0: list<string>, 1?: string|list<string>|null} $_current_version
	 */
	// @phpstan-ignore varTag.type
	switch ( $new_version ) {
		case 'same':
			// do nothing.
			break;

		case 'patch':
			$_current_version[0][2] = (int) $_current_version[0][2] + 1;

			$_current_version = [ $_current_version[0] ]; // Drop possible pre-release info.
			break;

		case 'minor':
			$_current_version[0][1] = (int) $_current_version[0][1] + 1;
			$_current_version[0][2] = 0;

			$_current_version = [ $_current_version[0] ]; // Drop possible pre-release info.
			break;

		case 'major':
			$_current_version[0][0] = (int) $_current_version[0][0] + 1;
			$_current_version[0][1] = 0;
			$_current_version[0][2] = 0;

			$_current_version = [ $_current_version[0] ]; // Drop possible pre-release info.
			break;

		default: // not a keyword.
			$_current_version = [ [ $new_version ] ];
			break;
	}

	// Reconstruct version string.
	$_current_version[0] = implode( '.', $_current_version[0] );
	// @phpstan-ignore argument.type
	$_current_version = implode( '-', $_current_version );

	return $_current_version;
}

/**
 * Compare two version strings to get the named semantic version.
 *
 * @access public
 *
 * @param string $new_version
 * @param string $original_version
 * @return string 'major', 'minor', 'patch'
 */
function get_named_sem_ver( $new_version, $original_version ) {

	if ( ! Comparator::greaterThan( $new_version, $original_version ) ) {
		return '';
	}

	$parts = explode( '-', $original_version );
	$bits  = explode( '.', $parts[0] );
	$major = $bits[0];
	if ( isset( $bits[1] ) ) {
		$minor = $bits[1];
	}

	try {
		if ( isset( $minor ) && Semver::satisfies( $new_version, "{$major}.{$minor}.x" ) ) {
			return 'patch';
		}

		if ( Semver::satisfies( $new_version, "{$major}.x.x" ) ) {
			return 'minor';
		}
	} catch ( \UnexpectedValueException $e ) {
		return '';
	}

	return 'major';
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
 * @param array<string|int,string|bool> $assoc_args Arguments array.
 * @param string|int                    $flag       Flag to get the value.
 * @param string|bool|int|null          $default    Default value for the flag. Default: NULL.
 * @return string|bool|int|null
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
		// In Windows $HOME may not be defined.
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
	if ( ! is_string( $string ) ) {
		return '/';
	}

	return rtrim( $string, '/\\' ) . '/';
}

/**
 * Normalize a filesystem path.
 *
 * On Windows systems, replaces backslashes with forward slashes
 * and forces upper-case drive letters.
 * Allows for two leading slashes for Windows network shares, but
 * ensures that all other duplicate slashes are reduced to a single one.
 * Ensures upper-case drive letters on Windows systems.
 *
 * @access public
 * @category System
 *
 * @param string $path Path to normalize.
 * @return string Normalized path.
 */
function normalize_path( $path ) {
	$path = str_replace( '\\', '/', $path );
	$path = (string) preg_replace( '|(?<=.)/+|', '/', $path );
	if ( ':' === substr( $path, 1, 1 ) ) {
		$path = ucfirst( $path );
	}
	return $path;
}


/**
 * Convert Windows EOLs to *nix.
 *
 * @param string $str String to convert.
 * @return string String with carriage return / newline pairs reduced to newlines.
 */
function normalize_eols( $str ) {
	return str_replace( "\r\n", "\n", $str );
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

	// `sys_get_temp_dir()` introduced PHP 5.2.1. Will always return something.
	$temp = trailingslashit( sys_get_temp_dir() );

	if ( ! is_writable( $temp ) ) {
		WP_CLI::warning( "Temp directory isn't writable: {$temp}" );
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
 * @param string $url
 * @param int $component
 * @return mixed
 *
 * @phpstan-return ($component is non-negative-int ? string|null : array{scheme?: string, user?: string, host?: string, port?: string, path?: string})
 */
function parse_ssh_url( $url, $component = -1 ) {
	preg_match( '#^((docker|docker\-compose|docker\-compose\-run|ssh|vagrant):)?(([^@:]+)@)?([^:/~]+)(:([\d]*))?((/|~)(.+))?$#', $url, $matches );
	/**
	 * @var array{scheme?: string, user?: string, host?: string, port?: string, path?: string} $bits
	 */
	$bits = [];
	foreach ( [
		2 => 'scheme',
		4 => 'user',
		5 => 'host',
		7 => 'port',
		8 => 'path',
	] as $i => $key ) {
		if ( ! empty( $matches[ $i ] ) ) {
			$bits[ $key ] = $matches[ $i ];
		}
	}

	// Find the hostname from `vagrant ssh-config` automatically.
	if ( preg_match( '/^vagrant:?/', $url ) ) {
		if ( isset( $bits['host'] ) && 'vagrant' === $bits['host'] && empty( $bits['scheme'] ) ) {
			$bits['scheme'] = 'vagrant';
			$bits['host']   = '';
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
 * @param string       $noun      Resource being affected (e.g. plugin).
 * @param string       $verb      Type of action happening to the noun (e.g. activate).
 * @param integer      $total     Total number of resource being affected.
 * @param integer      $successes Number of successful operations.
 * @param integer      $failures  Number of failures.
 * @param null|integer $skips     Optional. Number of skipped operations. Default null (don't show skips).
 * @return void
 */
function report_batch_operation_results( $noun, $verb, $total, $successes, $failures, $skips = null ) {
	$plural_noun           = $noun . 's';
	$past_tense_verb       = past_tense_verb( $verb );
	$past_tense_verb_upper = ucfirst( $past_tense_verb );
	if ( $failures ) {
		$failed_skipped_message = null === $skips ? '' : " ({$failures} failed" . ( $skips ? ", {$skips} skipped" : '' ) . ')';
		if ( $successes ) {
			WP_CLI::error( "Only {$past_tense_verb} {$successes} of {$total} {$plural_noun}{$failed_skipped_message}." );
		} else {
			WP_CLI::error( "No {$plural_noun} {$past_tense_verb}{$failed_skipped_message}." );
		}
	} else {
		$skipped_message = $skips ? " ({$skips} skipped)" : '';
		if ( $successes || $skips ) {
			WP_CLI::success( "{$past_tense_verb_upper} {$successes} of {$total} {$plural_noun}{$skipped_message}." );
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
 * @return array<string>
 */
function parse_str_to_argv( $arguments ) {
	preg_match_all( '/(?:--[^\s=]+=(["\'])((\\{2})*|(?:[^\1]+?[^\\\\](\\{2})*))\1|--[^\s=]+=[^\s]+|--[^\s=]+|(["\'])((\\{2})*|(?:[^\5]+?[^\\\\](\\{2})*))\5|[^\s]+)/', $arguments, $matches, PREG_SET_ORDER );
	$argv = [];
	foreach ( $matches as $match ) {
		// Check if this is a quoted associative argument (--key="value" or --key='value').
		// For associative args, groups 1 and 2 contain the quote char and value.
		// For positional args, groups 5 and 6 contain the quote char and value, and group 1 is empty.
		if ( isset( $match[1], $match[2] ) && 0 < strlen( $match[1] ) ) {
			// Extract the key part (everything before the quote).
			if ( preg_match( '/^(--[^=]+=)/', $match[0], $key_match ) ) {
				$value = $match[2];
				// Unescape the quote character that was used to wrap the value.
				$quote_char = $match[1];
				$value      = str_replace( '\\' . $quote_char, $quote_char, $value );
				// Reconstruct without the outer quotes.
				$argv[] = $key_match[1] . $value;
			} else {
				$argv[] = $match[0];
			}
		} elseif ( isset( $match[5], $match[6] ) ) {
			// This is a quoted positional argument.
			$value = $match[6];
			// Unescape the quote character that was used to wrap the value.
			$quote_char = $match[5];
			$value      = str_replace( '\\' . $quote_char, $quote_char, $value );
			$argv[]     = $value;
		} else {
			// Unquoted argument.
			$argv[] = $match[0];
		}
	}
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
	// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.urlencode_urlencode -- Format required by wordpress.org API.
	return urldecode( \basename( str_replace( [ '%2F', '%5C' ], '/', urlencode( $path ) ), $suffix ) );
}

/**
 * Checks whether the output of the current script is a TTY or a pipe / redirect
 *
 * Returns `true` if `STDOUT` output is being redirected to a pipe or a file; `false` is
 * output is being sent directly to the terminal.
 *
 * If an env variable `SHELL_PIPE` exists, the returned result depends on its
 * value. Strings like `1`, `0`, `yes`, `no`, that validate to booleans are accepted.
 *
 * To enable ASCII formatting even when the shell is piped, use the
 * ENV variable `SHELL_PIPE=0`.
 * ```
 * SHELL_PIPE=0 wp plugin list | cat
 * ```
 *
 * Note that the db command forwards to the mysql client, which is unaware of the env
 * variable. For db commands, pass the `--table` option instead.
 * ```
 * wp db query --table "SELECT 1" | cat
 * ```
 *
 * @access public
 *
 * @return bool
 */
function isPiped() { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.FunctionNameInvalid -- Renaming would break BC.
	$shell_pipe = getenv( 'SHELL_PIPE' );

	if ( false !== $shell_pipe ) {
		return filter_var( $shell_pipe, FILTER_VALIDATE_BOOLEAN );
	}

	return function_exists( 'posix_isatty' ) && ! posix_isatty( STDOUT );
}

/**
 * Expand within paths to their matching paths.
 *
 * Has no effect on paths which do not use glob patterns.
 *
 * @param string|array<string>  $paths Single path as a string, or an array of paths.
 * @param int|'default'         $flags Optional. Flags to pass to glob. Defaults to GLOB_BRACE.
 * @return array<string> Expanded paths.
 */
function expand_globs( $paths, $flags = 'default' ) {
	// Compatibility for systems without GLOB_BRACE.
	$glob_func = 'glob';
	if ( 'default' === $flags ) {
		if ( ! defined( 'GLOB_BRACE' ) || getenv( 'WP_CLI_TEST_EXPAND_GLOBS_NO_GLOB_BRACE' ) ) {
			$glob_func = 'WP_CLI\Utils\glob_brace';
		} else {
			$flags = GLOB_BRACE;
		}
	}

	$expanded = [];

	foreach ( (array) $paths as $path ) {
		$matching = [ $path ];

		if ( preg_match( '/[' . preg_quote( '*?[]{}!', '/' ) . ']/', $path ) ) {
			/**
			 * @var int $flags
			 */
			$matching = $glob_func( $path, $flags ) ?: [];
		}
		$expanded = array_merge( $expanded, $matching );
	}

	return array_values( array_unique( $expanded ) );
}

/**
 * Simulate a `glob()` with the `GLOB_BRACE` flag set. For systems (eg Alpine Linux) built against a libc library (eg https://www.musl-libc.org/) that lacks it.
 * Copied and adapted from Zend Framework's `Glob::fallbackGlob()` and Glob::nextBraceSub()`.
 *
 * Zend Framework (https://framework.zend.com/)
 *
 * @link      https://github.com/zendframework/zf2 for the canonical source repository
 * @copyright Copyright (c) 2005-2015 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://framework.zend.com/license/new-bsd New BSD License
 *
 * @param string $pattern     Filename pattern.
 * @param void   $dummy_flags Not used.
 * @return array<string> Array of paths.
 */
function glob_brace( $pattern, $dummy_flags = null ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- $dummy_flags is needed for compatibility with the libc implementation.

	static $next_brace_sub;
	if ( ! $next_brace_sub ) {
		// Find the end of the subpattern in a brace expression.
		$next_brace_sub = function ( $pattern, $current ) {
			$length = strlen( $pattern );
			$depth  = 0;

			while ( $current < $length ) {
				if ( '\\' === $pattern[ $current ] ) {
					if ( ++$current === $length ) {
						break;
					}
					++$current;
				} else {
					if ( ( '}' === $pattern[ $current ] && 0 === $depth-- ) || ( ',' === $pattern[ $current ] && 0 === $depth ) ) {
						break;
					}

					if ( '{' === $pattern[ $current++ ] ) {
						++$depth;
					}
				}
			}

			return $current < $length ? $current : null;
		};
	}

	$length = strlen( $pattern );

	$begin = 0;

	// Find first opening brace.
	// @phpstan-ignore for.variableOverwrite
	for ( $begin = 0; $begin < $length; $begin++ ) {
		if ( '\\' === $pattern[ $begin ] ) {
			++$begin;
		} elseif ( '{' === $pattern[ $begin ] ) {
			break;
		}
	}

	// Find comma or matching closing brace.
	$next = $next_brace_sub( $pattern, $begin + 1 );
	if ( null === $next ) {
		$result = glob( $pattern );
		return $result ?: [];
	}

	$rest = $next;

	// Point `$rest` to matching closing brace.
	while ( '}' !== $pattern[ $rest ] ) {
		$rest = $next_brace_sub( $pattern, $rest + 1 );
		if ( null === $rest ) {
			$result = glob( $pattern );
			return $result ?: [];
		}
	}

	$paths = [];
	$p     = $begin + 1;

	// For each comma-separated subpattern.
	do {
		$subpattern = substr( $pattern, 0, $begin )
			. substr( $pattern, $p, $next - $p )
			. substr( $pattern, $rest + 1 );

		$result = glob_brace( $subpattern );
		if ( ! empty( $result ) ) {
			$paths = array_merge( $paths, $result );
		}

		if ( '}' === $pattern[ $next ] ) {
			break;
		}

		$p    = $next + 1;
		$next = $next_brace_sub( $pattern, $p );
	} while ( null !== $next );

	return array_values( array_unique( $paths ) );
}

/**
 * Get the closest suggestion for a mistyped target term amongst a list of
 * options.
 *
 * Uses the Levenshtein algorithm to calculate the relative "distance" between
 * terms.
 *
 * If the "distance" to the closest term is higher than the threshold, an empty
 * string is returned.
 *
 * @param string        $target    Target term to get a suggestion for.
 * @param array<string> $options   Array with possible options.
 * @param int           $threshold Threshold above which to return an empty string.
 * @return string
 */
function get_suggestion( $target, array $options, $threshold = 2 ) {

	$suggestion_map = [
		'add'        => 'create',
		'check'      => 'check-update',
		'capability' => 'cap',
		'clear'      => 'flush',
		'decrement'  => 'decr',
		'del'        => 'delete',
		'directory'  => 'dir',
		'exec'       => 'eval',
		'exec-file'  => 'eval-file',
		'increment'  => 'incr',
		'language'   => 'locale',
		'lang'       => 'locale',
		'new'        => 'create',
		'number'     => 'count',
		'remove'     => 'delete',
		'regen'      => 'regenerate',
		'rep'        => 'replace',
		'repl'       => 'replace',
		'trash'      => 'delete',
		'v'          => 'version',
	];

	if ( array_key_exists( $target, $suggestion_map ) && in_array( $suggestion_map[ $target ], $options, true ) ) {
		return $suggestion_map[ $target ];
	}

	if ( empty( $options ) ) {
		return '';
	}

	$levenshtein = [];

	foreach ( $options as $option ) {
		$distance               = levenshtein( $option, $target );
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
 * @return string A Phar-safe version of the path.
 */
function phar_safe_path( $path ) {

	if ( ! inside_phar() ) {
		return $path;
	}

	return str_replace(
		PHAR_STREAM_PREFIX . rtrim( WP_CLI_PHAR_PATH, '/' ) . '/',
		PHAR_STREAM_PREFIX,
		$path
	);
}

/**
 * Maybe prefix command string with "/usr/bin/env".
 * Removes (if there) if Windows, adds (if not there) if not.
 *
 * @param string $command
 * @return string
 */
function force_env_on_nix_systems( $command ) {
	$env_prefix     = '/usr/bin/env ';
	$env_prefix_len = strlen( $env_prefix );
	if ( is_windows() ) {
		if ( 0 === strncmp( $command, $env_prefix, $env_prefix_len ) ) {
			$command = substr( $command, $env_prefix_len );
		}
	} elseif ( 0 !== strncmp( $command, $env_prefix, $env_prefix_len ) ) {
		$command = $env_prefix . $command;
	}
	return $command;
}

/**
 * Check that `proc_open()` and `proc_close()` haven't been disabled.
 *
 * @param string $context Optional. If set will appear in error message. Default null.
 * @param bool   $return  Optional. If set will return false rather than error out. Default false.
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

/**
 * Returns past tense of verb, with limited accuracy. Only regular verbs catered for, apart from "reset".
 *
 * @param string $verb Verb to return past tense of.
 * @return string
 */
function past_tense_verb( $verb ) {
	static $irregular = [
		'reset' => 'reset',
	];
	if ( isset( $irregular[ $verb ] ) ) {
		return $irregular[ $verb ];
	}
	$last = substr( $verb, -1 );
	if ( 'e' === $last ) {
		$verb = substr( $verb, 0, -1 );
	} elseif ( 'y' === $last && ! preg_match( '/[aeiou]y$/', $verb ) ) {
		$verb = substr( $verb, 0, -1 ) . 'i';
	} elseif ( preg_match( '/^[^aeiou]*[aeiou][^aeiouhwxy]$/', $verb ) ) {
		// Rule of thumb that most (all?) one-voweled regular verbs ending in vowel + consonant (excluding "h", "w", "x", "y") double their final consonant - misses many cases (eg "submit").
		$verb .= $last;
	}
	return $verb . 'ed';
}

/**
 * Get the path to the PHP binary used when executing WP-CLI.
 *
 * Environment values permit specific binaries to be indicated.
 *
 * @access public
 * @category System
 *
 * @return string
 */
function get_php_binary() {
	// Phar installs always use PHP_BINARY.
	if ( inside_phar() ) {
		return PHP_BINARY;
	}

	$wp_cli_php_used = getenv( 'WP_CLI_PHP_USED' );
	if ( false !== $wp_cli_php_used ) {
		return $wp_cli_php_used;
	}

	$wp_cli_php = getenv( 'WP_CLI_PHP' );
	if ( false !== $wp_cli_php ) {
		return $wp_cli_php;
	}

	return PHP_BINARY;
}

/**
 * Windows compatible `proc_open()`.
 * Works around bug in PHP, and also deals with *nix-like `ENV_VAR=blah cmd` environment variable prefixes.
 *
 * @access public
 *
 * @param string                            $cmd            Command to execute.
 * @param array<int, list<string>|resource> $descriptorspec Indexed array of descriptor numbers and their values.
 * @param array<int, resource>              &$pipes         Indexed array of file pointers that correspond to PHP's end of any pipes that are created.
 * @param string                            $cwd            Initial working directory for the command.
 * @param array<string, string>             $env            Array of environment variables.
 * @param array<string, bool>|null          $other_options  Array of additional options (Windows only).
 * @return resource|false Command stripped of any environment variable settings, or false on failure.
 *
 * @param-out array<int, resource> $pipes
 */
function proc_open_compat( $cmd, $descriptorspec, &$pipes, $cwd = null, $env = null, $other_options = null ) {
	if ( is_windows() ) {
		// @phpstan-ignore no.private.function
		$cmd = _proc_open_compat_win_env( $cmd, $env );
	}
	return proc_open( $cmd, $descriptorspec, $pipes, $cwd, $env, $other_options );
}

/**
 * For use by `proc_open_compat()` only. Separated out for ease of testing. Windows only.
 * Turns *nix-like `ENV_VAR=blah command` environment variable prefixes into stripped `cmd` with prefixed environment variables added to passed in environment array.
 *
 * @access private
 *
 * @param string                $cmd  Command to execute.
 * @param array<string, string>|null &$env Array of existing environment variables. Will be modified if any settings in command.
 * @return string Command stripped of any environment variable settings.
 */
function _proc_open_compat_win_env( $cmd, &$env ) {
	if ( false !== strpos( $cmd, '=' ) ) {
		while ( preg_match( '/^([A-Za-z_][A-Za-z0-9_]*)=("[^"]*"|[^ ]*) /', $cmd, $matches ) ) {
			$cmd = substr( $cmd, strlen( $matches[0] ) );
			if ( null === $env ) {
				$env = [];
			}
			$env[ $matches[1] ] = isset( $matches[2][0] ) && '"' === $matches[2][0] ? substr( $matches[2], 1, -1 ) : $matches[2];
		}
	}
	return $cmd;
}

/**
 * First half of escaping for LIKE special characters % and _ before preparing for MySQL.
 *
 * Use this only before wpdb::prepare() or esc_sql().  Reversing the order is very bad for security.
 *
 * Copied from core "wp-includes/wp-db.php". Avoids dependency on WP 4.4 wpdb.
 *
 * @access public
 *
 * @param string $text The raw text to be escaped. The input typed by the user should have no
 *                     extra or deleted slashes.
 * @return string Text in the form of a LIKE phrase. The output is not SQL safe. Call $wpdb::prepare()
 *                or real_escape next.
 */
function esc_like( $text ) {
	/**
	 * @var null|\wpdb $wpdb
	 */
	global $wpdb;

	// Check if the esc_like() method exists on the global $wpdb object.
	// We need to do this because to ensure compatibility layers like the
	// SQLite integration plugin still work.
	if ( null !== $wpdb && method_exists( $wpdb, 'esc_like' ) ) {
		return $wpdb->esc_like( $text );
	}

	return addcslashes( $text, '_%\\' );
}

/**
 * Escapes (backticks) MySQL identifiers (aka schema object names) - i.e. column names, table names, and database/index/alias/view etc names.
 * See https://dev.mysql.com/doc/refman/5.5/en/identifiers.html
 *
 * @param  string|array<string> $idents A single identifier or an array of identifiers.
 * @return string|array<string> An escaped string if given a string, or an array of escaped strings if given an array of strings.
 *
 * @phpstan-return ($idents is string ? string : array<string>)
 */
function esc_sql_ident( $idents ) {
	$backtick = static function ( $v ) {
		// Escape any backticks in the identifier by doubling.
		return '`' . str_replace( '`', '``', $v ) . '`';
	};
	if ( is_string( $idents ) ) {
		return $backtick( $idents );
	}
	return array_map( $backtick, $idents );
}

/**
 * Check whether a given string is a valid JSON representation.
 *
 * @param mixed  $argument       String to evaluate.
 * @param bool   $ignore_scalars Optional. Whether to ignore scalar values.
 *                               Defaults to true.
 * @return bool Whether the provided string is a valid JSON representation.
 *
 * @phpstan-assert-if-true =non-empty-string $argument
 */
function is_json( $argument, $ignore_scalars = true ) {
	if ( ! is_string( $argument ) || '' === $argument ) {
		return false;
	}

	if ( $ignore_scalars && ! in_array( $argument[0], [ '{', '[' ], true ) ) {
		return false;
	}

	json_decode( $argument, $assoc = true );

	return json_last_error() === JSON_ERROR_NONE;
}

/**
 * Parse known shell arrays included in the $assoc_args array.
 *
 * @param array<string, string> $assoc_args      Associative array of arguments.
 * @param array<string>         $array_arguments Array of argument keys that should receive an
 *                                               array through the shell.
 * @return array<string, mixed>
 */
function parse_shell_arrays( $assoc_args, $array_arguments ) {
	if ( empty( $assoc_args ) || empty( $array_arguments ) ) {
		return $assoc_args;
	}

	foreach ( $array_arguments as $key ) {
		if ( array_key_exists( $key, $assoc_args ) && is_json( $assoc_args[ $key ] ) ) {
			// @phpstan-ignore cast.useless
			$assoc_args[ $key ] = json_decode( (string) $assoc_args[ $key ], $assoc = true );
		}
	}

	return $assoc_args;
}

/**
 * Describe a callable as a string.
 *
 * @param callable $callable The callable to describe.
 * @return string String description of the callable.
 */
function describe_callable( $callable ) {
	try {
		if ( $callable instanceof Closure ) {
			$reflection = new ReflectionFunction( $callable );

			return "Closure in file {$reflection->getFileName()} at line {$reflection->getStartLine()}";
		}

		if ( is_array( $callable ) ) {
			if ( is_object( $callable[0] ) ) {
				return sprintf(
					'%s->%s()',
					get_class( $callable[0] ),
					(string) $callable[1]
				);
			}

			// @phpstan-ignore cast.string
			return sprintf( '%s::%s()', (string) $callable[0], (string) $callable[1] );
		}

		return gettype( $callable );
	} catch ( Exception $exception ) {
		return 'Callable of unknown type';
	}
}

/**
 * Checks if the given class and method pair is a valid callable.
 *
 * This accommodates changes to `is_callable()` in PHP 8 that mean an array of a
 * classname and instance method is no longer callable.
 *
 * @param array $pair The class and method pair to check.
 * @return bool
 */
function is_valid_class_and_method_pair( $pair ) {
	if ( ! is_array( $pair ) || 2 !== count( $pair ) ) {
		return false;
	}

	if ( ! is_string( $pair[0] ) || ! is_string( $pair[1] ) ) {
		return false;
	}

	if ( ! class_exists( $pair[0] ) ) {
		return false;
	}

	if ( ! method_exists( $pair[0], $pair[1] ) ) {
		return false;
	}

	return true;
}

/**
 * Pluralizes a noun in a grammatically correct way.
 *
 * @param string   $noun  Noun to be pluralized. Needs to be in singular form.
 * @param int|null $count Optional. Count of the nouns, to decide whether to
 *                        pluralize. Will pluralize unconditionally if none
 *                        provided.
 * @return string Pluralized noun.
 */
function pluralize( $noun, $count = null ) {
	if ( 1 === $count ) {
		return $noun;
	}

	return Inflector::pluralize( $noun );
}

/**
 * Return the detected database type.
 *
 * Can be either 'sqlite' (if in a WordPress installation with the SQLite drop-in),
 * 'mysql', or 'mariadb'.
 *
 * @return string Database type.
 */
function get_db_type() {
	static $db_type = null;

	if ( defined( 'SQLITE_DB_DROPIN_VERSION' ) ) {
		return 'sqlite';
	}

	if ( null !== $db_type ) {
		return $db_type;
	}

	$db_type = 'mysql';

	$binary = get_mysql_binary_path();

	if ( '' !== $binary ) {
		$result = Process::create( "$binary --version", null, null )->run();

		if ( 0 === $result->return_code ) {
			$db_type = ( false !== strpos( $result->stdout, 'MariaDB' ) ) ? 'mariadb' : 'mysql';
		}
	}

	return $db_type;
}

/**
 * Get the path to the MySQL or MariaDB binary.
 *
 * If the MySQL binary is provided by MariaDB (as determined by the version string),
 * prefers the actual MariaDB binary.
 *
 * @since 2.12.0 Now also checks for MariaDB.
 *
 * @return string Path to the MySQL/MariaDB binary, or an empty string if not found.
 */
function get_mysql_binary_path() {
	static $path = null;

	if ( null !== $path ) {
		return $path;
	}

	$path    = '';
	$mysql   = Process::create( '/usr/bin/env which mysql', null, null )->run();
	$mariadb = Process::create( '/usr/bin/env which mariadb', null, null )->run();

	$mysql_binary   = trim( $mysql->stdout );
	$mariadb_binary = trim( $mariadb->stdout );

	if ( 0 === $mysql->return_code ) {
		if ( '' !== $mysql_binary ) {
			$path   = $mysql_binary;
			$result = Process::create( "$mysql_binary --version", null, null )->run();

			// It's actually MariaDB disguised as MySQL.
			if ( 0 === $result->return_code && false !== strpos( $result->stdout, 'MariaDB' ) && 0 === $mariadb->return_code ) {
				$path = $mariadb_binary;
			}
		}
	} elseif ( 0 === $mariadb->return_code ) {
		$path = $mariadb_binary;
	}

	return $path;
}

/**
 * Get the version of the MySQL or MariaDB database.
 *
 * @since 2.12.0 Now also checks for MariaDB.
 *
 * @return string Version of the MySQL/MariaDB database,
 *                or an empty string if not found.
 */
function get_mysql_version() {
	static $version = null;

	if ( null !== $version ) {
		return $version;
	}

	$version = '';

	$db_type = get_db_type();

	if ( 'sqlite' !== $db_type ) {
		$result = Process::create( "/usr/bin/env $db_type --version", null, null )->run();

		if ( 0 === $result->return_code ) {
			$version = trim( $result->stdout );
		}
	}

	return $version;
}

/**
 * Returns the correct `dump` command based on the detected database type.
 *
 * @return string The appropriate dump command.
 */
function get_sql_dump_command() {
	return 'mariadb' === get_db_type() ? 'mariadb-dump' : 'mysqldump';
}

/**
 * Returns the correct `check` command based on the detected database type.
 *
 * @return string The appropriate check command.
 */
function get_sql_check_command() {
	return 'mariadb' === get_db_type() ? 'mariadb-check' : 'mysqlcheck';
}

/**
 * Get the SQL modes of the MySQL session.
 *
 * @return string[] Array of SQL modes, or an empty array if they couldn't be
 *                  read.
 */
function get_sql_modes() {
	static $sql_modes = null;

	if ( null !== $sql_modes ) {
		return $sql_modes;
	}

	$binary = get_mysql_binary_path();

	if ( '' === $binary ) {
		$sql_modes = [];
	} else {
		$result = Process::create( "$binary --no-auto-rehash --batch --skip-column-names --execute=\"SELECT @@SESSION.sql_mode\"", null, null )->run();

		if ( 0 !== $result->return_code ) {
			$sql_modes = [];
		} else {
			$split_lines = preg_split( "/\r\n|\n|\r/", $result->stdout );
			$sql_modes   = array_filter(
				array_map(
					'trim',
					$split_lines ?: []
				)
			);
		}
	}

	return $sql_modes;
}

/**
 * Get the WP-CLI cache directory.
 *
 * @return string
 */
function get_cache_dir() {
	$home = get_home_dir();
	return getenv( 'WP_CLI_CACHE_DIR' ) ? : "$home/.wp-cli/cache";
}

/**
 * Check whether any input is passed to STDIN.
 *
 * @return bool
 */
function has_stdin() {
	$handle = fopen( 'php://stdin', 'r' );
	if ( ! $handle ) {
		return false;
	}

	$read    = array( $handle );
	$write   = null;
	$except  = null;
	$streams = stream_select( $read, $write, $except, 0 );

	fclose( $handle );

	return 1 === $streams;
}

/**
 * Return description of WP_CLI hooks used in @when tag
 *
 *  @param string $hook Name of WP_CLI hook
 *
 * @return string|null
 */
function get_hook_description( $hook ) {
	$events = [
		'find_command_to_run_pre'     => 'just before WP-CLI finds the command to run.',
		'before_registering_contexts' => 'before the contexts are registered.',
		'before_wp_load'              => 'just before the WP load process begins.',
		'before_wp_config_load'       => 'after wp-config.php has been located.',
		'after_wp_config_load'        => 'after wp-config.php has been loaded into scope.',
		'after_wp_load'               => 'just after the WP load process has completed.',
	];

	if ( array_key_exists( $hook, $events ) ) {
		return $events[ $hook ];
	}
	return null;
}

/**
 * Escape a value for CSV output.
 *
 * Values that start with the following characters are escaping with a single
 * quote: =, +, -, @, TAB (0x09) and CR (0x0D).
 *
 * @param string $value Value to escape.
 * @return string Escaped value.
 */
function escape_csv_value( $value ) {
	if ( null === $value ) {
		return '';
	}

	// Convert to string if not already
	$value = (string) $value;

	if (
		in_array(
			substr( $value, 0, 1 ),
			[ '=', '+', '-', '@', "\t", "\r" ],
			true
		)
	) {
		return "'{$value}";
	}

	return $value;
}
