<?php

use \WP_CLI\Dispatcher;
use \WP_CLI\Utils;

/**
 * Wrapper class for WP-CLI
 *
 * @package wp-cli
 */
class WP_CLI {

	private static $commands = array();
	private static $man_dirs = array();
	private static $arguments, $assoc_args, $assoc_special;

	/**
	 * Add a command to the wp-cli list of commands
	 *
	 * @param string $name The name of the command that will be used in the cli
	 * @param string|object $implementation The command implementation
	 */
	static function add_command( $name, $implementation ) {
		if ( is_string( $implementation ) )
			$command = new Dispatcher\CompositeCommand( $name, $implementation );
		else
			$command = new Dispatcher\SingleCommand( $name, $implementation );

		self::$commands[ $name ] = $command;
	}

	static function add_man_dir( $dest_dir, $src_dir ) {
		$dest_dir = realpath( $dest_dir ) . '/';

		if ( $src_dir )
			$src_dir = realpath( $src_dir ) . '/';

		self::$man_dirs[ $dest_dir ] = $src_dir;
	}

	static function get_man_dirs() {
		return self::$man_dirs;
	}

	/**
	 * Display a message in the cli
	 *
	 * @param string $message
	 */
	static function out( $message ) {
		if ( WP_CLI_QUIET ) return;
		\cli\out($message);
	}

	/**
	 * Display a message in the CLI and end with a newline
	 *
	 * @param string $message
	 */
	static function line( $message = '' ) {
		if ( WP_CLI_QUIET ) return;
		\cli\line($message);
	}

	/**
	 * Display an error in the CLI and end with a newline
	 *
	 * @param string $message
	 * @param string $label
	 */
	static function error( $message, $label = 'Error' ) {
		if ( !isset( self::$assoc_special['completions'] ) ) {
			\cli\err( '%R' . $label . ': %n' . self::error_to_string( $message ) );
		}

		exit(1);
	}

	/**
	 * Display a success in the CLI and end with a newline
	 *
	 * @param string $message
	 * @param string $label
	 */
	static function success( $message, $label = 'Success' ) {
		if ( WP_CLI_QUIET ) return;
		\cli\line( '%G' . $label . ': %n' . $message );
	}

	/**
	 * Display a warning in the CLI and end with a newline
	 *
	 * @param string $message
	 * @param string $label
	 */
	static function warning( $message, $label = 'Warning' ) {
		if ( WP_CLI_QUIET ) return;
		\cli\err( '%C' . $label . ': %n' . self::error_to_string( $message ) );
	}

	/**
	 * Ask for confirmation before running a destructive operation.
	 */
	static function confirm( $question, $assoc_args ) {
		if ( !isset( $assoc_args['yes'] ) ) {
			WP_CLI::out( $question . " [y/n] " );

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
	static function read_value( $value, $assoc_args = array() ) {
		if ( isset( $assoc_args['json'] ) ) {
			$value = json_decode( $value, true );
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
		if ( isset( $assoc_args['json'] ) ) {
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
		if( is_string( $errors ) ) {
			return $errors;
		} elseif( is_wp_error( $errors ) && $errors->get_error_code() ) {
			foreach( $errors->get_error_messages() as $message ) {
				if( $errors->get_error_data() )
					return $message . ' ' . $errors->get_error_data();
				else
					return $message;
			}
		}
	}

	/**
	 * Composes positional and associative arguments into a string.
	 *
	 * @param array
	 * @return string
	 */
	static function compose_args( $args, $assoc_args = array() ) {
		$str = ' ' . implode( ' ', array_map( 'escapeshellarg', $args ) );

		foreach ( $assoc_args as $key => $value ) {
			if ( true === $value )
				$str .= " --$key";
			else
				$str .= " --$key=" . escapeshellarg( $value );
		}

		return $str;
	}

	/**
	 * Launch an external process, closing the current one
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

	static function load_all_commands() {
		foreach ( array( 'internals', 'community' ) as $dir ) {
			foreach ( glob( WP_CLI_ROOT . "/commands/$dir/*.php" ) as $filename ) {
				$command = substr( basename( $filename ), 0, -4 );

				if ( isset( self::$commands[ $command ] ) )
					continue;

				include $filename;
			}
		}

		return self::$commands;
	}

	static function load_command( $command ) {
		if ( !isset( self::$commands[$command] ) ) {
			foreach ( array( 'internals', 'community' ) as $dir ) {
				$path = WP_CLI_ROOT . "/commands/$dir/$command.php";

				if ( is_readable( $path ) ) {
					include $path;
					break;
				}
			}
		}

		if ( !isset( self::$commands[$command] ) ) {
			return false;
		}

		return self::$commands[$command];
	}

	private static function parse_args() {
		$r = Utils\parse_args( array_slice( $GLOBALS['argv'], 1 ) );

		list( self::$arguments, self::$assoc_args ) = $r;

		self::$assoc_special = Utils\split_assoc( self::$assoc_args, array(
			'path', 'url', 'blog', 'user', 'require',
			'quiet', 'completions', 'man', 'syn-list'
		) );
	}

	static function get_assoc_special() {
		return self::$assoc_special;
	}

	static function before_wp_load() {
		self::add_man_dir(
			WP_CLI_ROOT . "../../../man/",
			WP_CLI_ROOT . "../../docs/"
		);

		self::parse_args();

		// Handle --version parameter
		if ( isset( self::$assoc_args['version'] ) && empty( self::$arguments ) ) {
			self::line( 'wp-cli ' . WP_CLI_VERSION );
			exit;
		}

		define( 'WP_CLI_QUIET', isset( self::$assoc_special['quiet'] ) );

		$_SERVER['DOCUMENT_ROOT'] = getcwd();

		// Handle --path
		Utils\set_wp_root( self::$assoc_special );

		// Handle --url and --blog parameters
		Utils\set_url( self::$assoc_special );

		if ( array( 'core', 'download' ) == self::$arguments ) {
			self::run_command();
			exit;
		}

		if ( !is_readable( WP_ROOT . 'wp-load.php' ) ) {
			WP_CLI::error( 'This does not seem to be a WordPress install. Pass --path=`path/to/wordpress` or run `wp core download`.' );
		}

		if ( array( 'core', 'config' ) == self::$arguments ) {
			self::run_command();
			exit;
		}

		// The db commands don't need any WP files
		if ( array( 'db' ) == array_slice( self::$arguments, 0, 1 ) ) {
			Utils\load_wp_config();
			self::run_command();
			exit;
		}

		// Set installer flag before loading any WP files
		if ( array( 'core', 'install' ) == self::$arguments ) {
			define( 'WP_INSTALLING', true );
		}
	}

	static function after_wp_load() {
		add_filter( 'filesystem_method', function() { return 'direct'; }, 99 );

		Utils\set_user( self::$assoc_special );

		if ( !defined( 'WP_INSTALLING' ) && isset( self::$assoc_special['url'] ) )
			Utils\set_wp_query();

		if ( isset( self::$assoc_special['require'] ) )
			require self::$assoc_special['require'];

		if ( isset( self::$assoc_special['man'] ) ) {
			self::generate_man( self::$arguments );
			exit;
		}

		// Handle --syn-list parameter
		if ( isset( self::$assoc_special['syn-list'] ) ) {
			foreach ( self::load_all_commands() as $command ) {
				if ( $command instanceof \WP_CLI\Dispatcher\Composite ) {
					foreach ( $command->get_subcommands() as $subcommand )
						$subcommand->show_usage( '' );
				} else {
					$command->show_usage( '' );
				}
			}
			exit;
		}

		if ( isset( self::$assoc_special['completions'] ) ) {
			self::render_automcomplete();
			exit;
		}

		self::run_command();
	}

	private static function run_command() {
		$root = new Dispatcher\RootCommand;

		$root->invoke( self::$arguments, self::$assoc_args );
	}

	private static function generate_man( $args ) {
		$command = Dispatcher\traverse( $args );
		if ( !$command )
			WP_CLI::error( sprintf( "'%s' command not found." ) );

		foreach ( self::$man_dirs as $dest_dir => $src_dir ) {
			\WP_CLI\Man\generate( $src_dir, $dest_dir, $command );
		}
	}

	private static function render_automcomplete() {
		foreach ( self::load_all_commands() as $name => $command ) {
			$subcommands = $command->get_subcommands();

			self::line( $name . ' ' . implode( ' ', array_keys( $subcommands ) ) );
		}
	}

	// back-compat
	static function addCommand( $name, $class ) {
		self::add_command( $name, $class );
	}
}

