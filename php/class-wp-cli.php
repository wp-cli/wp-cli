<?php

use \WP_CLI\Utils;
use \WP_CLI\Dispatcher;
use \WP_CLI\FileCache;
use \WP_CLI\Process;
use \WP_CLI\WpHttpCacheManager;

/**
 * Various utilities for WP-CLI commands.
 */
class WP_CLI {

	private static $configurator;

	private static $logger;

	private static $hooks = array(), $hooks_passed = array();

	/**
	 * Set the logger instance.
	 *
	 * @param object $logger
	 */
	public static function set_logger( $logger ) {
		self::$logger = $logger;
	}

	/**
	 * Get the Configurator instance
	 *
	 * @return \WP_CLI\Configurator
	 */
	public static function get_configurator() {
		static $configurator;

		if ( !$configurator ) {
			$configurator = new WP_CLI\Configurator( WP_CLI_ROOT . '/php/config-spec.php' );
		}

		return $configurator;
	}

	public static function get_root_command() {
		static $root;

		if ( !$root ) {
			$root = new Dispatcher\RootCommand;
		}

		return $root;
	}

	public static function get_runner() {
		static $runner;

		if ( !$runner ) {
			$runner = new WP_CLI\Runner;
		}

		return $runner;
	}

	/**
	 * @return FileCache
	 */
	public static function get_cache() {
		static $cache;

		if ( !$cache ) {
			$home = getenv( 'HOME' );
			if ( !$home ) {
				// sometime in windows $HOME is not defined
				$home = getenv( 'HOMEDRIVE' ) . getenv( 'HOMEPATH' );
			}
			$dir = getenv( 'WP_CLI_CACHE_DIR' ) ? : "$home/.wp-cli/cache";

			// 6 months, 300mb
			$cache = new FileCache( $dir, 15552000, 314572800 );

			// clean older files on shutdown with 1/50 probability
			if ( 0 === mt_rand( 0, 50 ) ) {
				register_shutdown_function( function () use ( $cache ) {
					$cache->clean();
				} );
			}
		}

		return $cache;
	}

	/**
	 * Set the context in which WP-CLI should be run
	 */
	public static function set_url( $url ) {
		$url_parts = Utils\parse_url( $url );
		self::set_url_params( $url_parts );
	}

	private static function set_url_params( $url_parts ) {
		$f = function( $key ) use ( $url_parts ) {
			return \WP_CLI\Utils\get_flag_value( $url_parts, $key, '' );
		};

		if ( isset( $url_parts['host'] ) ) {
			if ( isset( $url_parts['scheme'] ) && 'https' === strtolower( $url_parts['scheme'] ) ) {
				$_SERVER['HTTPS'] = 'on';
			}

			$_SERVER['HTTP_HOST'] = $url_parts['host'];
			if ( isset( $url_parts['port'] ) ) {
				$_SERVER['HTTP_HOST'] .= ':' . $url_parts['port'];
			}

			$_SERVER['SERVER_NAME'] = $url_parts['host'];
		}

		$_SERVER['REQUEST_URI'] = $f('path') . ( isset( $url_parts['query'] ) ? '?' . $url_parts['query'] : '' );
		$_SERVER['SERVER_PORT'] = \WP_CLI\Utils\get_flag_value( $url_parts, 'port', '80' );
		$_SERVER['QUERY_STRING'] = $f('query');
	}

	/**
	 * @return WpHttpCacheManager
	 */
	public static function get_http_cache_manager() {
		static $http_cacher;

		if ( !$http_cacher ) {
			$http_cacher = new WpHttpCacheManager( self::get_cache() );
		}

		return $http_cacher;
	}

	public static function colorize( $string ) {
		return \cli\Colors::colorize( $string, self::get_runner()->in_color() );
	}

	/**
	 * Schedule a callback to be executed at a certain point (before WP is loaded).
	 */
	public static function add_hook( $when, $callback ) {
		if ( in_array( $when, self::$hooks_passed ) )
			call_user_func( $callback );

		self::$hooks[ $when ][] = $callback;
	}

	/**
	 * Execute registered callbacks.
	 */
	public static function do_hook( $when ) {
		self::$hooks_passed[] = $when;

		if ( !isset( self::$hooks[ $when ] ) )
			return;

		foreach ( self::$hooks[ $when ] as $callback ) {
			call_user_func( $callback );
		}
	}

	/**
	 * Add a command to the wp-cli list of commands
	 *
	 * @param string $name The name of the command that will be used in the CLI
	 * @param string $class The command implementation
	 * @param array $args An associative array with additional parameters:
	 *   'before_invoke' => callback to execute before invoking the command
	 */
	public static function add_command( $name, $class, $args = array() ) {
		if ( is_string( $class ) && ! class_exists( (string) $class ) ) {
			WP_CLI::error( sprintf( "Class '%s' does not exist.", $class ) );
		}

		if ( isset( $args['before_invoke'] ) ) {
			self::add_hook( "before_invoke:$name", $args['before_invoke'] );
		}

		$path = preg_split( '/\s+/', $name );

		$leaf_name = array_pop( $path );
		$full_path = $path;

		$command = self::get_root_command();

		while ( !empty( $path ) ) {
			$subcommand_name = $path[0];
			$subcommand = $command->find_subcommand( $path );

			// create an empty container
			if ( !$subcommand ) {
				$subcommand = new Dispatcher\CompositeCommand( $command, $subcommand_name,
					new \WP_CLI\DocParser( '' ) );
				$command->add_subcommand( $subcommand_name, $subcommand );
			}

			$command = $subcommand;
		}

		$leaf_command = Dispatcher\CommandFactory::create( $leaf_name, $class, $command );

		if ( ! $command->can_have_subcommands() ) {
			throw new Exception( sprintf( "'%s' can't have subcommands.",
				implode( ' ' , Dispatcher\get_path( $command ) ) ) );
		}

		$command->add_subcommand( $leaf_name, $leaf_command );
	}

	/**
	 * Display a message in the CLI and end with a newline
	 *
	 * @param string $message
	 */
	public static function line( $message = '' ) {
		echo $message . "\n";
	}

	/**
	 * Log an informational message.
	 *
	 * @param string $message
	 */
	public static function log( $message ) {
		self::$logger->info( $message );
	}

	/**
	 * Display a success in the CLI and end with a newline
	 *
	 * @param string $message
	 */
	public static function success( $message ) {
		self::$logger->success( $message );
	}

	/**
	 * Display a warning in the CLI and end with a newline
	 *
	 * @param string $message
	 */
	public static function warning( $message ) {
		self::$logger->warning( self::error_to_string( $message ) );
	}

	/**
	 * Display an error in the CLI and end with a newline
	 *
	 * @param string|WP_Error $message
	 * @param bool            $exit    if true, the script will exit()
	 */
	public static function error( $message, $exit = true ) {
		if ( ! isset( self::get_runner()->assoc_args[ 'completions' ] ) ) {
			self::$logger->error( self::error_to_string( $message ) );
		}

		if ( $exit ) {
			exit(1);
		}
	}

	/**
	 * Display an error in the CLI and end with a newline
	 *
	 * @param array $message  each element from the array will be printed on its own line
	 */
	public static function error_multi_line( $message_lines ) {
		if ( ! isset( self::get_runner()->assoc_args[ 'completions' ] ) && is_array( $message_lines ) ) {
			self::$logger->error_multi_line( array_map( array( __CLASS__, 'error_to_string' ), $message_lines ) );
		}
	}

	/**
	 * Ask for confirmation before running a destructive operation.
	 */
	public static function confirm( $question, $assoc_args = array() ) {
		if ( ! \WP_CLI\Utils\get_flag_value( $assoc_args, 'yes' ) ) {
			fwrite( STDOUT, $question . " [y/n] " );

			$answer = trim( fgets( STDIN ) );

			if ( 'y' != $answer )
				exit;
		}
	}

	/**
	 * Read value from a positional argument or from STDIN.
	 *
	 * @param array $args The list of positional arguments.
	 * @param int $index At which position to check for the value.
	 *
	 * @return string
	 */
	public static function get_value_from_arg_or_stdin( $args, $index ) {
		if ( isset( $args[ $index ] ) ) {
			$raw_value = $args[ $index ];
		} else {
			// We don't use file_get_contents() here because it doesn't handle
			// Ctrl-D properly, when typing in the value interactively.
			$raw_value = '';
			while ( ( $line = fgets( STDIN ) ) !== false ) {
				$raw_value .= $line;
			}
		}

		return $raw_value;
	}

	/**
	 * Read a value, from various formats.
	 *
	 * @param mixed $value
	 * @param array $assoc_args
	 */
	public static function read_value( $raw_value, $assoc_args = array() ) {
		if ( \WP_CLI\Utils\get_flag_value( $assoc_args, 'format' ) === 'json' ) {
			$value = json_decode( $raw_value, true );
			if ( null === $value ) {
				WP_CLI::error( sprintf( 'Invalid JSON: %s', $raw_value ) );
			}
		} else {
			$value = $raw_value;
		}

		return $value;
	}

	/**
	 * Display a value, in various formats
	 *
	 * @param mixed $value
	 * @param array $assoc_args
	 */
	public static function print_value( $value, $assoc_args = array() ) {
		if ( \WP_CLI\Utils\get_flag_value( $assoc_args, 'format' ) === 'json' ) {
			$value = json_encode( $value );
		} elseif ( is_array( $value ) || is_object( $value ) ) {
			$value = var_export( $value );
		}

		echo $value . "\n";
	}

	/**
	 * Convert a wp_error into a string
	 *
	 * @param mixed $errors
	 * @return string
	 */
	public static function error_to_string( $errors ) {
		if ( is_string( $errors ) ) {
			return $errors;
		}

		if ( is_object( $errors ) && is_a( $errors, 'WP_Error' ) ) {
			foreach ( $errors->get_error_messages() as $message ) {
				if ( $errors->get_error_data() )
					return $message . ' ' . $errors->get_error_data();
				else
					return $message;
			}
		}
	}

	/**
	 * Launch an external process that takes over I/O.
	 *
	 * @param string Command to call
	 * @param bool Whether to exit if the command returns an error status
	 * @param bool Whether to return an exit status (default) or detailed execution results
	 *
	 * @return int|ProcessRun The command exit status, or a ProcessRun instance
	 */
	public static function launch( $command, $exit_on_error = true, $return_detailed = false ) {

		$proc = Process::create( $command );
		$results = $proc->run();

		if ( $results->return_code && $exit_on_error )
			exit( $results->return_code );

		if ( $return_detailed ) {
			return $results;
		} else {
			return $results->return_code;
		}
	}

	/**
	 * Launch another WP-CLI command using the runtime arguments for the current process
	 *
	 * @param string Command to call
	 * @param array $args Positional arguments to use
	 * @param array $assoc_args Associative arguments to use
	 * @param bool Whether to exit if the command returns an error status
	 * @param bool Whether to return an exit status (default) or detailed execution results
	 *
	 * @return int|ProcessRun The command exit status, or a ProcessRun instance
	 */
	public static function launch_self( $command, $args = array(), $assoc_args = array(), $exit_on_error = true, $return_detailed = false ) {
		$reused_runtime_args = array(
			'path',
			'url',
			'user',
			'allow-root',
		);

		foreach ( $reused_runtime_args as $key ) {
			if ( $value = self::get_runner()->config[ $key ] )
				$assoc_args[ $key ] = $value;
		}

		$php_bin = self::get_php_binary();

		$script_path = $GLOBALS['argv'][0];

		$args = implode( ' ', array_map( 'escapeshellarg', $args ) );
		$assoc_args = \WP_CLI\Utils\assoc_args_to_str( $assoc_args );

		$full_command = "{$php_bin} {$script_path} {$command} {$args} {$assoc_args}";

		return self::launch( $full_command, $exit_on_error, $return_detailed );
	}

	/**
	 * Get the path to the PHP binary used when executing WP-CLI.
	 * Environment values permit specific binaries to be indicated.
	 *
	 * @return string
	 */
	private static function get_php_binary() {
		if ( defined( 'PHP_BINARY' ) )
			return PHP_BINARY;

		if ( getenv( 'WP_CLI_PHP_USED' ) )
			return getenv( 'WP_CLI_PHP_USED' );

		if ( getenv( 'WP_CLI_PHP' ) )
			return getenv( 'WP_CLI_PHP' );

		return 'php';
	}

	public static function get_config( $key = null ) {
		if ( null === $key ) {
			return self::get_runner()->config;
		}

		if ( !isset( self::get_runner()->config[ $key ] ) ) {
			self::warning( "Unknown config option '$key'." );
			return null;
		}

		return self::get_runner()->config[ $key ];
	}

	/**
	 * Run a given command.
	 *
	 * @param array
	 * @param array
	 */
	public static function run_command( $args, $assoc_args = array() ) {
		self::get_runner()->run_command( $args, $assoc_args );
	}



	// DEPRECATED STUFF

	public static function add_man_dir() {
		trigger_error( 'WP_CLI::add_man_dir() is deprecated. Add docs inline.', E_USER_WARNING );
	}

	// back-compat
	public static function out( $str ) {
		fwrite( STDOUT, $str );
	}

	// back-compat
	public static function addCommand( $name, $class ) {
		trigger_error( sprintf( 'wp %s: %s is deprecated. use WP_CLI::add_command() instead.',
			$name, __FUNCTION__ ), E_USER_WARNING );
		self::add_command( $name, $class );
	}
}

