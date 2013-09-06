<?php

use \WP_CLI\Utils;
use \WP_CLI\Dispatcher;

/**
 * Various utilities for WP-CLI commands.
 */
class WP_CLI {

	private static $configurator;

	private static $logger;

	private static $hooks = array(), $hooks_passed = array();

	/**
	 * Initialize WP_CLI static variables.
	 */
	static function init() {
		self::$configurator = new WP_CLI\Configurator( WP_CLI_ROOT . '/php/config-spec.php' );
	}

	/**
	 * Set the logger instance.
	 *
	 * @param object $logger
	 */
	static function set_logger( $logger ) {
		self::$logger = $logger;
	}

	static function get_configurator() {
		return self::$configurator;
	}

	static function get_root_command() {
		static $root;

		if ( !$root ) {
			$root = new Dispatcher\RootCommand;
		}

		return $root;
	}

	static function get_runner() {
		static $runner;

		if ( !$runner ) {
			$runner = new WP_CLI\Runner;
		}

		return $runner;
	}

	static function colorize( $string ) {
		return \cli\Colors::colorize( $string, self::get_runner()->in_color() );
	}

	/**
	 * Schedule a callback to be executed at a certain point (before WP is loaded).
	 */
	static function add_action( $when, $callback ) {
		if ( in_array( $when, self::$hooks_passed ) )
			call_user_func( $callback );

		self::$hooks[ $when ][] = $callback;
	}

	/**
	 * Execute registered callbacks.
	 */
	static function do_action( $when ) {
		self::$hooks_passed[] = $when;

		if ( !isset( self::$hooks[ $when ] ) )
			return;

		array_map( 'call_user_func', self::$hooks[ $when ] );
	}

	/**
	 * Add a command to the wp-cli list of commands
	 *
	 * @param string $name The name of the command that will be used in the cli
	 * @param string $class The command implementation
	 * @param array $args An associative array with additional parameters:
	 *   'before_invoke' => callback to execute before invoking the command
	 */
	static function add_command( $name, $class, $args = array() ) {
		$command = Dispatcher\CommandFactory::create( $name, $class, self::get_root_command() );

		if ( isset( $args['before_invoke'] ) ) {
			self::add_action( "before_invoke:$name", $args['before_invoke'] );
		}

		self::get_root_command()->add_subcommand( $name, $command );
	}

	/**
	 * Display a message in the CLI and end with a newline
	 *
	 * @param string $message
	 */
	static function line( $message = '' ) {
		echo $message . "\n";
	}

	/**
	 * Log an informational message.
	 *
	 * @param string $message
	 */
	static function log( $message ) {
		self::$logger->info( $message );
	}

	/**
	 * Display a success in the CLI and end with a newline
	 *
	 * @param string $message
	 */
	static function success( $message ) {
		self::$logger->success( $message );
	}

	/**
	 * Display a warning in the CLI and end with a newline
	 *
	 * @param string $message
	 */
	static function warning( $message ) {
		self::$logger->warning( self::error_to_string( $message ) );
	}

	/**
	 * Display an error in the CLI and end with a newline
	 *
	 * @param string $message
	 */
	static function error( $message ) {
		if ( ! isset( self::get_runner()->assoc_args[ 'completions' ] ) ) {
			self::$logger->error( self::error_to_string( $message ) );
		}

		exit(1);
	}

	/**
	 * Ask for confirmation before running a destructive operation.
	 */
	static function confirm( $question, $assoc_args = array() ) {
		if ( !isset( $assoc_args['yes'] ) ) {
			echo $question . " [y/n] ";

			$answer = trim( fgets( STDIN ) );

			if ( 'y' != $answer )
				exit;
		}
	}

	/**
	 * Read a value, from various formats
	 *
	 * @param mixed $value
	 * @param array $assoc_args
	 */
	static function read_value( $raw_value, $assoc_args = array() ) {
		if ( isset( $assoc_args['format'] ) && 'json' == $assoc_args['format'] ) {
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
	static function print_value( $value, $assoc_args = array() ) {
		if ( isset( $assoc_args['format'] ) && 'json' == $assoc_args['format'] ) {
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
	static function error_to_string( $errors ) {
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
	 *
	 * @return int The command exit status
	 */
	static function launch( $command, $exit_on_error = true ) {
		$r = proc_close( proc_open( $command, array( STDIN, STDOUT, STDERR ), $pipes ) );

		if ( $r && $exit_on_error )
			exit($r);

		return $r;
	}

	static function get_config_path() {
		return self::get_runner()->config_path;
	}

	static function get_config( $key = null ) {
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
	static function run_command( $args, $assoc_args = array() ) {
		self::get_runner()->run_command( $args, $assoc_args );
	}



	// DEPRECATED STUFF

	static function add_man_dir() {
		trigger_error( 'WP_CLI::add_man_dir() is deprecated. Add docs inline.', E_USER_WARNING );
	}

	// back-compat
	static function out( $str ) {
		echo $str;
	}

	// back-compat
	static function addCommand( $name, $class ) {
		trigger_error( sprintf( 'wp %s: %s is deprecated. use WP_CLI::add_command() instead.',
			$name, __FUNCTION__ ), E_USER_WARNING );
		self::add_command( $name, $class );
	}
}

