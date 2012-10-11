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
	public function add_command( $name, $implementation ) {
		if ( is_string( $implementation ) && class_exists( $implementation ) )
			$class = '\WP_CLI\Dispatcher\CompositeCommand';
		else
			$class = '\WP_CLI\Dispatcher\SimpleCommand';

		self::$commands[ $name ] = new $class( $implementation, $name );
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
		if ( !WP_CLI_AUTOCOMPLETE ) {
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
	 * Splits a string into positional and associative arguments.
	 *
	 * @param string
	 * @return array
	 */
	static function parse_args( $arguments ) {
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
	 * Issue warnings for each missing associative argument.
	 *
	 * @param array List of required arg names
	 * @param array Passed args
	 */
	static function check_required_args( $required, $assoc_args ) {
		$missing = false;

		foreach ( $required as $arg ) {
			if ( !isset( $assoc_args[ $arg ] ) ) {
				WP_CLI::warning( "--$arg parameter is missing" );
				$missing = true;
			} elseif ( true === $assoc_args[ $arg ] ) {
				// passed as a flag
				WP_CLI::warning( "--$arg needs to have a value" );
				$missing = true;
			}
		}

		if ( $missing )
			exit(1);
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

	/**
	 * Sets the appropriate $_SERVER keys based on a given string
	 *
	 * @param string $url The URL
	 */
	static function set_url_params( $url ) {
	    $url_parts = parse_url( $url );

	    if ( !isset( $url_parts['scheme'] ) ) {
	        $url_parts = parse_url( 'http://' . $url );
	    }

		$_SERVER['HTTP_HOST'] = isset($url_parts['host']) ? $url_parts['host'] : '';
		$_SERVER['REQUEST_URI'] = (isset($url_parts['path']) ? $url_parts['path'] : '') . (isset($url_parts['query']) ? '?' . $url_parts['query'] : '');
		$_SERVER['REQUEST_URL'] = isset($url_parts['path']) ? $url_parts['path'] : '';
		$_SERVER['QUERY_STRING'] = isset($url_parts['query']) ? $url_parts['query'] : '';
	}

	static function get_upgrader( $class ) {
		if ( !class_exists( 'WP_Upgrader' ) )
			require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

		require WP_CLI_ROOT . '/class-cli-upgrader-skin.php';

		return new $class( new CLI_Upgrader_Skin );
	}

	static function _set_url( &$assoc_args ) {
		if ( isset( $assoc_args['url'] ) ) {
			$blog = $assoc_args['url'];
			/* unset( $assoc_args['url'] ); */
		} elseif ( isset( $assoc_args['blog'] ) ) {
			$blog = $assoc_args['blog'];
			unset( $assoc_args['blog'] );
			if ( true === $blog ) {
				WP_CLI::line( 'usage: wp --blog=example.com' );
			}
		} elseif ( is_readable( WP_ROOT . 'wp-cli-blog' ) ) {
			$blog = trim( file_get_contents( WP_ROOT . 'wp-cli-blog' ) );
		} elseif ( $wp_config_path = self::locate_wp_config() ) {
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
			WP_CLI::set_url_params( $blog );
		}
	}

	// Loads wp-config.php without loading the rest of WP
	static function load_wp_config() {
		define( 'ABSPATH', dirname(__FILE__) . '/' );

		if ( $wp_config_path = self::locate_wp_config() )
			require self::locate_wp_config();
		else
			WP_CLI::error( 'No wp-config.php file.' );
	}

	static function locate_wp_config() {
		if ( file_exists( WP_ROOT . 'wp-config.php' ) ) {
			return WP_ROOT . 'wp-config.php';
		} elseif ( file_exists( WP_ROOT . '/../wp-config.php' ) && ! file_exists( WP_ROOT . '/../wp-settings.php' ) ) {
			return WP_ROOT . '/../wp-config.php';
		} else {
			return false;
		}
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

	// back-compat
	static function addCommand( $name, $class ) {
		self::add_command( $name, $class );
	}
}

