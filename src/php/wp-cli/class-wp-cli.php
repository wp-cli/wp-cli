<?php

/**
 * Wrapper class for WP-CLI
 *
 * @package wp-cli
 */
class WP_CLI {

	private static $commands = array();

	/**
	 * Add a command to the wp-cli list of commands
	 *
	 * @param string $name The name of the command that will be used in the cli
	 * @param string $class The class to manage the command
	 */
	public function add_command( $name, $class ) {
		if ( is_string( $class ) )
			$command = new \WP_CLI\Dispatcher\CompositeCommand( $name, $class );
		else
			$command = new \WP_CLI\Dispatcher\SingleCommand( $name, $class );

		self::$commands[ $name ] = $command;
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

	static function get_numeric_arg( $args, $index, $name ) {
		if ( ! isset( $args[$index] ) ) {
			WP_CLI::error( "$name required" );
		}

		if ( ! is_numeric( $args[$index] ) ) {
			WP_CLI::error( "$name must be numeric" );
		}

		return $args[$index];
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
		if ( !isset( WP_CLI::$commands[$command] ) ) {
			foreach ( array( 'internals', 'community' ) as $dir ) {
				$path = WP_CLI_ROOT . "/commands/$dir/$command.php";

				if ( is_readable( $path ) ) {
					include $path;
					break;
				}
			}
		}

		if ( !isset( WP_CLI::$commands[$command] ) ) {
			WP_CLI::error( "'$command' is not a registered wp command. See 'wp help'." );
			exit;
		}

		return WP_CLI::$commands[$command];
	}

	static function run_command( $arguments, $assoc_args ) {
		if ( empty( $arguments ) ) {
			$command = 'help';
		} else {
			$command = array_shift( $arguments );

			$aliases = array(
				'sql' => 'db'
			);

			if ( isset( $aliases[ $command ] ) )
				$command = $aliases[ $command ];
		}

		define( 'WP_CLI_COMMAND', $command );

		$command = self::load_command( $command );

		$command->invoke( $arguments, $assoc_args );
	}

	private static $arguments, $assoc_args, $assoc_special;

	static function before_wp_load() {
		$r = WP_CLI\Utils\parse_args( array_slice( $GLOBALS['argv'], 1 ) );

		list( self::$arguments, self::$assoc_args ) = $r;

		self::$assoc_special = WP_CLI\Utils\split_assoc( self::$assoc_args, array(
			'path', 'url', 'blog', 'user', 'require',
			'quiet', 'completions'
		) );

		define( 'WP_CLI_QUIET', isset( self::$assoc_special['quiet'] ) );

		// Handle --version parameter
		if ( isset( self::$assoc_args['version'] ) && empty( self::$arguments ) ) {
			self::line( 'wp-cli ' . WP_CLI_VERSION );
			exit;
		}

		$_SERVER['DOCUMENT_ROOT'] = getcwd();

		// Define the WordPress location
		if ( !empty( self::$assoc_special['path'] ) ) {
			// trailingslashit() isn't available yet
			define( 'WP_ROOT', rtrim( self::$assoc_args['path'], '/' ) . '/' );
		} else {
			define( 'WP_ROOT', $_SERVER['PWD'] . '/' );
		}

		// Handle --url and --blog parameters
		WP_CLI\Utils\set_url( self::$assoc_special );

		if ( array( 'core', 'download' ) == self::$arguments ) {
			WP_CLI::run_command( self::$arguments, self::$assoc_args );
			exit;
		}

		if ( !is_readable( WP_ROOT . 'wp-load.php' ) ) {
			WP_CLI::error( 'This does not seem to be a WordPress install. Pass --path=`path/to/wordpress` or run `wp core download`.' );
		}

		if ( array( 'core', 'config' ) == self::$arguments ) {
			WP_CLI::run_command( self::$arguments, self::$assoc_args );
			exit;
		}

		// The db commands don't need any WP files
		if ( array( 'db' ) == array_slice( self::$arguments, 0, 1 ) ) {
			WP_CLI\Utils\load_wp_config();
			WP_CLI::run_command( self::$arguments, self::$assoc_args );
			exit;
		}

		// Set installer flag before loading any WP files
		if ( array( 'core', 'install' ) == self::$arguments ) {
			define( 'WP_INSTALLING', true );
		}
	}

	static function get_assoc_special() {
		return self::$assoc_special;
	}

	static function after_wp_load() {
		add_filter( 'filesystem_method', function() { return 'direct'; }, 99 );

		WP_CLI\Utils\set_user( self::$assoc_special );

		if ( !defined( 'WP_INSTALLING' ) && isset( self::$assoc_special['url'] ) )
			WP_CLI\Utils\set_wp_query();

		if ( isset( self::$assoc_special['require'] ) )
			require self::$assoc_special['require'];

		if ( isset( self::$assoc_special['completions'] ) ) {
			foreach ( self::load_all_commands() as $name => $command ) {
				self::line( $command->autocomplete() );
			}
			exit;
		}

		self::run_command( self::$arguments, self::$assoc_args );
	}

	// back-compat
	static function addCommand( $name, $class ) {
		self::add_command( $name, $class );
	}
}

