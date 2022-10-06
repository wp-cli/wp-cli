<?php

use cli\Colors;
use Mustangostang\Spyc;
use WP_CLI\Configurator;
use WP_CLI\Dispatcher;
use WP_CLI\Dispatcher\CommandAddition;
use WP_CLI\Dispatcher\CommandFactory;
use WP_CLI\Dispatcher\CommandNamespace;
use WP_CLI\Dispatcher\CompositeCommand;
use WP_CLI\Dispatcher\RootCommand;
use WP_CLI\DocParser;
use WP_CLI\ExitException;
use WP_CLI\FileCache;
use WP_CLI\Loggers\Execution;
use WP_CLI\Process;
use WP_CLI\ProcessRun;
use WP_CLI\Runner;
use WP_CLI\SynopsisParser;
use WP_CLI\Utils;
use WP_CLI\WpHttpCacheManager;

/**
 * Various utilities for WP-CLI commands.
 */
class WP_CLI {

	private static $logger;

	private static $hooks = [];

	private static $hooks_passed = [];

	private static $capture_exit = false;

	private static $deferred_additions = [];

	/**
	 * Set the logger instance.
	 *
	 * @param object $logger Logger instance to set.
	 */
	public static function set_logger( $logger ) {
		self::$logger = $logger;
	}

	/**
	 * Get the logger instance.
	 *
	 * @return object $logger Logger instance.
	 */
	public static function get_logger() {
		return self::$logger;
	}

	/**
	 * Get the Configurator instance
	 *
	 * @return Configurator
	 */
	public static function get_configurator() {
		static $configurator;

		if ( ! $configurator ) {
			$configurator = new Configurator( WP_CLI_ROOT . '/php/config-spec.php' );
		}

		return $configurator;
	}

	public static function get_root_command() {
		static $root;

		if ( ! $root ) {
			$root = new RootCommand();
		}

		return $root;
	}

	public static function get_runner() {
		static $runner;

		if ( ! $runner ) {
			$runner = new Runner();
		}

		return $runner;
	}

	/**
	 * @return FileCache
	 */
	public static function get_cache() {
		static $cache;

		if ( ! $cache ) {
			$dir      = Utils\get_cache_dir();
			$ttl      = getenv( 'WP_CLI_CACHE_EXPIRY' ) ? : 15552000;
			$max_size = getenv( 'WP_CLI_CACHE_MAX_SIZE' ) ? : 314572800;
			// 6 months, 300mb
			$cache = new FileCache( $dir, $ttl, $max_size );

			// Clean older files on shutdown with 1/50 probability.
			// phpcs:ignore WordPress.WP.AlternativeFunctions.rand_mt_rand -- no crypto and WP not loaded.
			if ( 0 === mt_rand( 0, 50 ) ) {
				register_shutdown_function(
					function () use ( $cache ) {
						$cache->clean();
					}
				);
			}
		}

		return $cache;
	}

	/**
	 * Set the context in which WP-CLI should be run
	 */
	public static function set_url( $url ) {
		self::debug( 'Set URL: ' . $url, 'bootstrap' );
		$url_parts = Utils\parse_url( $url );
		self::set_url_params( $url_parts );
	}

	private static function set_url_params( $url_parts ) {
		$f = function( $key ) use ( $url_parts ) {
			return Utils\get_flag_value( $url_parts, $key, '' );
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

		$_SERVER['REQUEST_URI']  = $f( 'path' ) . ( isset( $url_parts['query'] ) ? '?' . $url_parts['query'] : '' );
		$_SERVER['SERVER_PORT']  = Utils\get_flag_value( $url_parts, 'port', '80' );
		$_SERVER['QUERY_STRING'] = $f( 'query' );
	}

	/**
	 * @return WpHttpCacheManager
	 */
	public static function get_http_cache_manager() {
		static $http_cacher;

		if ( ! $http_cacher ) {
			$http_cacher = new WpHttpCacheManager( self::get_cache() );
		}

		return $http_cacher;
	}

	/**
	 * Colorize a string for output.
	 *
	 * Yes, you can change the color of command line text too. For instance,
	 * here's how `WP_CLI::success()` colorizes "Success: "
	 *
	 * ```
	 * WP_CLI::colorize( "%GSuccess:%n " )
	 * ```
	 *
	 * Uses `\cli\Colors::colorize()` to transform color tokens to display
	 * settings. Choose from the following tokens (and note 'reset'):
	 *
	 * * %y => ['color' => 'yellow'],
	 * * %g => ['color' => 'green'],
	 * * %b => ['color' => 'blue'],
	 * * %r => ['color' => 'red'],
	 * * %p => ['color' => 'magenta'],
	 * * %m => ['color' => 'magenta'],
	 * * %c => ['color' => 'cyan'],
	 * * %w => ['color' => 'grey'],
	 * * %k => ['color' => 'black'],
	 * * %n => ['color' => 'reset'],
	 * * %Y => ['color' => 'yellow', 'style' => 'bright'],
	 * * %G => ['color' => 'green', 'style' => 'bright'],
	 * * %B => ['color' => 'blue', 'style' => 'bright'],
	 * * %R => ['color' => 'red', 'style' => 'bright'],
	 * * %P => ['color' => 'magenta', 'style' => 'bright'],
	 * * %M => ['color' => 'magenta', 'style' => 'bright'],
	 * * %C => ['color' => 'cyan', 'style' => 'bright'],
	 * * %W => ['color' => 'grey', 'style' => 'bright'],
	 * * %K => ['color' => 'black', 'style' => 'bright'],
	 * * %N => ['color' => 'reset', 'style' => 'bright'],
	 * * %3 => ['background' => 'yellow'],
	 * * %2 => ['background' => 'green'],
	 * * %4 => ['background' => 'blue'],
	 * * %1 => ['background' => 'red'],
	 * * %5 => ['background' => 'magenta'],
	 * * %6 => ['background' => 'cyan'],
	 * * %7 => ['background' => 'grey'],
	 * * %0 => ['background' => 'black'],
	 * * %F => ['style' => 'blink'],
	 * * %U => ['style' => 'underline'],
	 * * %8 => ['style' => 'inverse'],
	 * * %9 => ['style' => 'bright'],
	 * * %_ => ['style' => 'bright']
	 *
	 * @access public
	 * @category Output
	 *
	 * @param string $string String to colorize for output, with color tokens.
	 * @return string Colorized string.
	 */
	public static function colorize( $string ) {
		return Colors::colorize( $string, self::get_runner()->in_color() );
	}

	/**
	 * Schedule a callback to be executed at a certain point.
	 *
	 * Hooks conceptually are very similar to WordPress actions. WP-CLI hooks
	 * are typically called before WordPress is loaded.
	 *
	 * WP-CLI hooks include:
	 *
	 * * `before_add_command:<command>` - Before the command is added.
	 * * `after_add_command:<command>` - After the command was added.
	 * * `before_invoke:<command>` - Just before a command is invoked.
	 * * `after_invoke:<command>` - Just after a command is invoked.
	 * * `find_command_to_run_pre` - Just before WP-CLI finds the command to run.
	 * * `before_registering_contexts` (1) - Before the contexts are registered.
	 * * `before_wp_load` - Just before the WP load process begins.
	 * * `before_wp_config_load` - After wp-config.php has been located.
	 * * `after_wp_config_load` - After wp-config.php has been loaded into scope.
	 * * `after_wp_load` - Just after the WP load process has completed.
	 * * `before_run_command` (3) - Just before the command is executed.
	 *
	 * The parentheses behind the hook name denote the number of arguments
	 * being passed into the hook. For such hooks, the callback should return
	 * the first argument again, making them work like a WP filter.
	 *
	 * WP-CLI commands can create their own hooks with `WP_CLI::do_hook()`.
	 *
	 * If additional arguments are passed through the `WP_CLI::do_hook()` call,
	 * these will be passed on to the callback provided by `WP_CLI::add_hook()`.
	 *
	 * ```
	 * # `wp network meta` confirms command is executing in multisite context.
	 * WP_CLI::add_command( 'network meta', 'Network_Meta_Command', array(
	 *    'before_invoke' => function () {
	 *        if ( !is_multisite() ) {
	 *            WP_CLI::error( 'This is not a multisite installation.' );
	 *        }
	 *    }
	 * ) );
	 * ```
	 *
	 * @access public
	 * @category Registration
	 *
	 * @param string $when Identifier for the hook.
	 * @param mixed $callback Callback to execute when hook is called.
	 * @return null
	 */
	public static function add_hook( $when, $callback ) {
		if ( array_key_exists( $when, self::$hooks_passed ) ) {
			self::debug(
				sprintf(
					'Immediately invoking on passed hook "%s": %s',
					$when,
					Utils\describe_callable( $callback )
				),
				'hooks'
			);
			call_user_func_array( $callback, (array) self::$hooks_passed[ $when ] );
		}

		self::$hooks[ $when ][] = $callback;
	}

	/**
	 * Execute callbacks registered to a given hook.
	 *
	 * See `WP_CLI::add_hook()` for details on WP-CLI's internal hook system.
	 * Commands can provide and call their own hooks.
	 *
	 * @access public
	 * @category Registration
	 *
	 * @param string $when    Identifier for the hook.
	 * @param mixed  ...$args Optional. Arguments that will be passed onto the
	 *                        callback provided by `WP_CLI::add_hook()`.
	 * @return null|mixed Returns the first optional argument if optional
	 *                    arguments were passed, otherwise returns null.
	 */
	public static function do_hook( $when, ...$args ) {
		self::$hooks_passed[ $when ] = $args;

		$has_args = count( $args ) > 0;

		if ( ! isset( self::$hooks[ $when ] ) ) {
			if ( $has_args ) {
				return $args[0];
			}

			return null;
		}

		self::debug(
			sprintf(
				'Processing hook "%s" with %d callbacks',
				$when,
				count( self::$hooks[ $when ] )
			),
			'hooks'
		);

		foreach ( self::$hooks[ $when ] as $callback ) {
			self::debug(
				sprintf(
					'On hook "%s": %s',
					$when,
					Utils\describe_callable( $callback )
				),
				'hooks'
			);

			if ( $has_args ) {
				$return_value = $callback( ...$args );
				if ( isset( $return_value ) ) {
					$args[0] = $return_value;
				}
			} else {
				$callback();
			}
		}

		if ( $has_args ) {
			return $args[0];
		}

		return null;
	}

	/**
	 * Add a callback to a WordPress action or filter.
	 *
	 * `add_action()` without needing access to `add_action()`. If WordPress is
	 * already loaded though, you should use `add_action()` (and `add_filter()`)
	 * instead.
	 *
	 * @access public
	 * @category Registration
	 *
	 * @param string $tag Named WordPress action or filter.
	 * @param mixed $function_to_add Callable to execute when the action or filter is evaluated.
	 * @param integer $priority Priority to add the callback as.
	 * @param integer $accepted_args Number of arguments to pass to callback.
	 * @return true
	 */
	public static function add_wp_hook( $tag, $function_to_add, $priority = 10, $accepted_args = 1 ) {
		global $wp_filter, $merged_filters;

		if ( function_exists( 'add_filter' ) ) {
			add_filter( $tag, $function_to_add, $priority, $accepted_args );
		} else {
			$idx = self::wp_hook_build_unique_id( $tag, $function_to_add, $priority );

			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- This is intentional & the purpose of this function.
			$wp_filter[ $tag ][ $priority ][ $idx ] = [
				'function'      => $function_to_add,
				'accepted_args' => $accepted_args,
			];
			unset( $merged_filters[ $tag ] );
		}

		return true;
	}

	/**
	 * Build Unique ID for storage and retrieval.
	 *
	 * Essentially _wp_filter_build_unique_id() without needing access to _wp_filter_build_unique_id()
	 */
	private static function wp_hook_build_unique_id( $tag, $function, $priority ) {
		global $wp_filter;
		static $filter_id_count = 0;

		if ( is_string( $function ) ) {
			return $function;
		}

		if ( is_object( $function ) ) {
			// Closures are currently implemented as objects
			$function = [ $function, '' ];
		} else {
			$function = (array) $function;
		}

		if ( is_object( $function[0] ) ) {
			// Object Class Calling
			if ( function_exists( 'spl_object_hash' ) ) {
				return spl_object_hash( $function[0] ) . $function[1];
			}

			$obj_idx = get_class( $function[0] ) . $function[1];
			if ( ! isset( $function[0]->wp_filter_id ) ) {
				if ( false === $priority ) {
					return false;
				}
				$obj_idx                  .= isset( $wp_filter[ $tag ][ $priority ] ) ? count( (array) $wp_filter[ $tag ][ $priority ] ) : $filter_id_count;
				$function[0]->wp_filter_id = $filter_id_count;
				++$filter_id_count;
			} else {
				$obj_idx .= $function[0]->wp_filter_id;
			}

			return $obj_idx;
		}

		if ( is_string( $function[0] ) ) {
			// Static Calling
			return $function[0] . '::' . $function[1];
		}
	}

	/**
	 * Register a command to WP-CLI.
	 *
	 * WP-CLI supports using any callable class, function, or closure as a
	 * command. `WP_CLI::add_command()` is used for both internal and
	 * third-party command registration.
	 *
	 * Command arguments are parsed from PHPDoc by default, but also can be
	 * supplied as an optional third argument during registration.
	 *
	 * ```
	 * # Register a custom 'foo' command to output a supplied positional param.
	 * #
	 * # $ wp foo bar --append=qux
	 * # Success: bar qux
	 *
	 * /**
	 *  * My awesome closure command
	 *  *
	 *  * <message>
	 *  * : An awesome message to display
	 *  *
	 *  * --append=<message>
	 *  * : An awesome message to append to the original message.
	 *  *
	 *  * @when before_wp_load
	 *  *\/
	 * $foo = function( $args, $assoc_args ) {
	 *     WP_CLI::success( $args[0] . ' ' . $assoc_args['append'] );
	 * };
	 * WP_CLI::add_command( 'foo', $foo );
	 * ```
	 *
	 * @access public
	 * @category Registration
	 *
	 * @param string   $name Name for the command (e.g. "post list" or "site empty").
	 * @param callable $callable Command implementation as a class, function or closure.
	 * @param array    $args {
	 *    Optional. An associative array with additional registration parameters.
	 *
	 *    @type callable $before_invoke Callback to execute before invoking the command.
	 *    @type callable $after_invoke  Callback to execute after invoking the command.
	 *    @type string   $shortdesc     Short description (80 char or less) for the command.
	 *    @type string   $longdesc      Description of arbitrary length for examples, etc.
	 *    @type string   $synopsis      The synopsis for the command (string or array).
	 *    @type string   $when          Execute callback on a named WP-CLI hook (e.g. before_wp_load).
	 *    @type bool     $is_deferred   Whether the command addition had already been deferred.
	 * }
	 * @return bool True on success, false if deferred, hard error if registration failed.
	 */
	public static function add_command( $name, $callable, $args = [] ) {
		// Bail immediately if the WP-CLI executable has not been run.
		if ( ! defined( 'WP_CLI' ) ) {
			return false;
		}

		$valid = false;
		if ( is_callable( $callable ) ) {
			$valid = true;
		} elseif ( is_string( $callable ) && class_exists( (string) $callable ) ) {
			$valid = true;
		} elseif ( is_object( $callable ) ) {
			$valid = true;
		} elseif ( Utils\is_valid_class_and_method_pair( $callable ) ) {
			$valid = true;
		}
		if ( ! $valid ) {
			if ( is_array( $callable ) ) {
				$callable[0] = is_object( $callable[0] ) ? get_class( $callable[0] ) : $callable[0];
				$callable    = [ $callable[0], $callable[1] ];
			}
			self::error( sprintf( 'Callable %s does not exist, and cannot be registered as `wp %s`.', json_encode( $callable ), $name ) );
		}

		$addition = new CommandAddition();
		self::do_hook( "before_add_command:{$name}", $addition );

		if ( $addition->was_aborted() ) {
			self::warning( "Aborting the addition of the command '{$name}' with reason: {$addition->get_reason()}." );
			return false;
		}

		foreach ( [ 'before_invoke', 'after_invoke' ] as $when ) {
			if ( isset( $args[ $when ] ) ) {
				self::add_hook( "{$when}:{$name}", $args[ $when ] );
			}
		}

		$path = preg_split( '/\s+/', $name );

		$leaf_name = array_pop( $path );

		$command = self::get_root_command();

		while ( ! empty( $path ) ) {
			$subcommand_name = $path[0];
			$parent          = implode( ' ', $path );
			$subcommand      = $command->find_subcommand( $path );

			// Parent not found. Defer addition or create an empty container as
			// needed.
			if ( ! $subcommand ) {
				if ( isset( $args['is_deferred'] ) && $args['is_deferred'] ) {
					$subcommand = new CompositeCommand(
						$command,
						$subcommand_name,
						new DocParser( '' )
					);

					self::debug(
						"Adding empty container for deferred command: {$name}",
						'commands'
					);

					$command->add_subcommand( $subcommand_name, $subcommand );
				} else {
					self::debug( "Deferring command: {$name}", 'commands' );

					self::defer_command_addition(
						$name,
						$parent,
						$callable,
						$args
					);

					return false;
				}
			}

			$command = $subcommand;
		}

		$leaf_command = CommandFactory::create( $leaf_name, $callable, $command );

		// Only add a command namespace if the command itself does not exist yet.
		if ( $leaf_command instanceof CommandNamespace
			&& array_key_exists( $leaf_name, $command->get_subcommands() ) ) {
			return false;
		}

		// Reattach commands attached to namespace to real command.
		$subcommand_name  = (array) $leaf_name;
		$existing_command = $command->find_subcommand( $subcommand_name );
		if ( $existing_command instanceof CompositeCommand && $existing_command->can_have_subcommands() ) {
			if ( $leaf_command instanceof CommandNamespace || ! $leaf_command->can_have_subcommands() ) {
				$command_to_keep = $existing_command;
			} else {
				$command_to_keep = $leaf_command;
			}

			self::merge_sub_commands( $command_to_keep, $existing_command, $leaf_command );
		}

		/** @var Dispatcher\Subcommand|Dispatcher\CompositeCommand|Dispatcher\CommandNamespace $leaf_command */

		if ( ! $command->can_have_subcommands() ) {
			throw new Exception(
				sprintf(
					"'%s' can't have subcommands.",
					implode( ' ', Dispatcher\get_path( $command ) )
				)
			);
		}

		if ( isset( $args['shortdesc'] ) ) {
			$leaf_command->set_shortdesc( $args['shortdesc'] );
		}

		if ( isset( $args['longdesc'] ) ) {
			$leaf_command->set_longdesc( $args['longdesc'] );
		}

		if ( isset( $args['synopsis'] ) ) {
			if ( is_string( $args['synopsis'] ) ) {
				$leaf_command->set_synopsis( $args['synopsis'] );
			} elseif ( is_array( $args['synopsis'] ) ) {
				$synopsis = SynopsisParser::render( $args['synopsis'] );
				$leaf_command->set_synopsis( $synopsis );
				$long_desc = '';
				$bits      = explode( ' ', $synopsis );
				foreach ( $args['synopsis'] as $key => $arg ) {
					$long_desc .= $bits[ $key ] . "\n";
					if ( ! empty( $arg['description'] ) ) {
						$long_desc .= ': ' . $arg['description'] . "\n";
					}
					$yamlify = [];
					foreach ( [ 'default', 'options' ] as $key ) {
						if ( isset( $arg[ $key ] ) ) {
							$yamlify[ $key ] = $arg[ $key ];
						}
					}
					if ( ! empty( $yamlify ) ) {
						$long_desc .= Spyc::YAMLDump( $yamlify );
						$long_desc .= '---' . "\n";
					}
					$long_desc .= "\n";
				}
				if ( ! empty( $long_desc ) ) {
					$long_desc = rtrim( $long_desc, "\r\n" );
					$long_desc = '## OPTIONS' . "\n\n" . $long_desc;
					if ( ! empty( $args['longdesc'] ) ) {
						$long_desc .= "\n\n" . ltrim( $args['longdesc'], "\r\n" );
					}
					$leaf_command->set_longdesc( $long_desc );
				}
			}
		}

		if ( isset( $args['when'] ) ) {
			self::get_runner()->register_early_invoke( $args['when'], $leaf_command );
		}

		if ( ! empty( $parent ) ) {
			$sub_command = trim( str_replace( $parent, '', $name ) );
			self::debug( "Adding command: {$sub_command} in {$parent} Namespace", 'commands' );
		} else {
			self::debug( "Adding command: {$name}", 'commands' );
		}

		$command->add_subcommand( $leaf_name, $leaf_command );

		self::do_hook( "after_add_command:{$name}" );
		return true;
	}

	/**
	 * Merge the sub-commands of two commands into a single command to keep.
	 *
	 * @param CompositeCommand $command_to_keep Command to merge the sub commands into. This is typically one of the
	 *                                          two others.
	 * @param CompositeCommand $old_command     Command that was already registered.
	 * @param CompositeCommand $new_command     New command that is being added.
	 */
	private static function merge_sub_commands(
		CompositeCommand $command_to_keep,
		CompositeCommand $old_command,
		CompositeCommand $new_command
	) {
		foreach ( $old_command->get_subcommands() as $subname => $subcommand ) {
			$command_to_keep->add_subcommand( $subname, $subcommand, false );
		}

		foreach ( $new_command->get_subcommands() as $subname => $subcommand ) {
			$command_to_keep->add_subcommand( $subname, $subcommand, true );
		}
	}

	/**
	 * Defer command addition for a sub-command if the parent command is not yet
	 * registered.
	 *
	 * @param string $name     Name for the sub-command.
	 * @param string $parent   Name for the parent command.
	 * @param string $callable Command implementation as a class, function or closure.
	 * @param array  $args     Optional. See `WP_CLI::add_command()` for details.
	 */
	private static function defer_command_addition( $name, $parent, $callable, $args = [] ) {
		$args['is_deferred']               = true;
		self::$deferred_additions[ $name ] = [
			'parent'   => $parent,
			'callable' => $callable,
			'args'     => $args,
		];
		self::add_hook(
			"after_add_command:$parent",
			function () use ( $name ) {
				$deferred_additions = WP_CLI::get_deferred_additions();

				if ( ! array_key_exists( $name, $deferred_additions ) ) {
					return;
				}

				$callable = $deferred_additions[ $name ]['callable'];
				$args     = $deferred_additions[ $name ]['args'];
				WP_CLI::remove_deferred_addition( $name );

				WP_CLI::add_command( $name, $callable, $args );
			}
		);
	}

	/**
	 * Get the list of outstanding deferred command additions.
	 *
	 * @return array Array of outstanding command additions.
	 */
	public static function get_deferred_additions() {
		return self::$deferred_additions;
	}

	/**
	 * Remove a command addition from the list of outstanding deferred additions.
	 */
	public static function remove_deferred_addition( $name ) {
		if ( ! array_key_exists( $name, self::$deferred_additions ) ) {
			self::warning( "Trying to remove a non-existent command addition '{$name}'." );
		}

		unset( self::$deferred_additions[ $name ] );
	}

	/**
	 * Display informational message without prefix, and ignore `--quiet`.
	 *
	 * Message is written to STDOUT. `WP_CLI::log()` is typically recommended;
	 * `WP_CLI::line()` is included for historical compat.
	 *
	 * @access public
	 * @category Output
	 *
	 * @param string $message Message to display to the end user.
	 * @return null
	 */
	public static function line( $message = '' ) {
		echo $message . "\n";
	}

	/**
	 * Display informational message without prefix.
	 *
	 * Message is written to STDOUT, or discarded when `--quiet` flag is supplied.
	 *
	 * ```
	 * # `wp cli update` lets user know of each step in the update process.
	 * WP_CLI::log( sprintf( 'Downloading from %s...', $download_url ) );
	 * ```
	 *
	 * @access public
	 * @category Output
	 *
	 * @param string $message Message to write to STDOUT.
	 */
	public static function log( $message ) {
		if ( null === self::$logger ) {
			return;
		}

		self::$logger->info( $message );
	}

	/**
	 * Display success message prefixed with "Success: ".
	 *
	 * Success message is written to STDOUT.
	 *
	 * Typically recommended to inform user of successful script conclusion.
	 *
	 * ```
	 * # wp rewrite flush expects 'rewrite_rules' option to be set after flush.
	 * flush_rewrite_rules( \WP_CLI\Utils\get_flag_value( $assoc_args, 'hard' ) );
	 * if ( ! get_option( 'rewrite_rules' ) ) {
	 *     WP_CLI::warning( "Rewrite rules are empty." );
	 * } else {
	 *     WP_CLI::success( 'Rewrite rules flushed.' );
	 * }
	 * ```
	 *
	 * @access public
	 * @category Output
	 *
	 * @param string $message Message to write to STDOUT.
	 * @return null
	 */
	public static function success( $message ) {
		if ( null === self::$logger ) {
			return;
		}

		self::$logger->success( $message );
	}

	/**
	 * Display debug message prefixed with "Debug: " when `--debug` is used.
	 *
	 * Debug message is written to STDERR, and includes script execution time.
	 *
	 * Helpful for optionally showing greater detail when needed. Used throughout
	 * WP-CLI bootstrap process for easier debugging and profiling.
	 *
	 * ```
	 * # Called in `WP_CLI\Runner::set_wp_root()`.
	 * private static function set_wp_root( $path ) {
	 *     define( 'ABSPATH', Utils\trailingslashit( $path ) );
	 *     WP_CLI::debug( 'ABSPATH defined: ' . ABSPATH );
	 *     $_SERVER['DOCUMENT_ROOT'] = realpath( $path );
	 * }
	 *
	 * # Debug details only appear when `--debug` is used.
	 * # $ wp --debug
	 * # [...]
	 * # Debug: ABSPATH defined: /srv/www/wordpress-develop.dev/src/ (0.225s)
	 * ```
	 *
	 * @access public
	 * @category Output
	 *
	 * @param string|WP_Error|Exception|Throwable $message Message to write to STDERR.
	 * @param string|bool $group Organize debug message to a specific group.
	 * Use `false` to not group the message.
	 * @return null
	 */
	public static function debug( $message, $group = false ) {
		static $storage = [];

		if ( ! self::$logger ) {
			$storage[] = [ $message, $group ];
			return;
		}

		if ( ! empty( $storage ) && self::$logger ) {
			foreach ( $storage as $entry ) {
				list( $stored_message, $stored_group ) = $entry;
				self::$logger->debug( self::error_to_string( $stored_message ), $stored_group );
			}
			$storage = [];
		}

		self::$logger->debug( self::error_to_string( $message ), $group );
	}

	/**
	 * Display warning message prefixed with "Warning: ".
	 *
	 * Warning message is written to STDERR.
	 *
	 * Use instead of `WP_CLI::debug()` when script execution should be permitted
	 * to continue.
	 *
	 * ```
	 * # `wp plugin activate` skips activation when plugin is network active.
	 * $status = $this->get_status( $plugin->file );
	 * // Network-active is the highest level of activation status
	 * if ( 'active-network' === $status ) {
	 *   WP_CLI::warning( "Plugin '{$plugin->name}' is already network active." );
	 *   continue;
	 * }
	 * ```
	 *
	 * @access public
	 * @category Output
	 *
	 * @param string|WP_Error|Exception|Throwable $message Message to write to STDERR.
	 * @return null
	 */
	public static function warning( $message ) {
		if ( null === self::$logger ) {
			return;
		}

		self::$logger->warning( self::error_to_string( $message ) );
	}

	/**
	 * Display error message prefixed with "Error: " and exit script.
	 *
	 * Error message is written to STDERR. Defaults to halting script execution
	 * with return code 1.
	 *
	 * Use `WP_CLI::warning()` instead when script execution should be permitted
	 * to continue.
	 *
	 * ```
	 * # `wp cache flush` considers flush failure to be a fatal error.
	 * if ( false === wp_cache_flush() ) {
	 *     WP_CLI::error( 'The object cache could not be flushed.' );
	 * }
	 * ```
	 *
	 * @access public
	 * @category Output
	 *
	 * @param string|WP_Error|Exception|Throwable $message Message to write to STDERR.
	 * @param boolean|integer            $exit    True defaults to exit(1).
	 * @return null
	 */
	public static function error( $message, $exit = true ) {
		if ( null !== self::$logger && ! isset( self::get_runner()->assoc_args['completions'] ) ) {
			self::$logger->error( self::error_to_string( $message ) );
		}

		$return_code = false;
		if ( true === $exit ) {
			$return_code = 1;
		} elseif ( is_int( $exit ) && $exit >= 1 ) {
			$return_code = $exit;
		}

		if ( $return_code ) {
			if ( self::$capture_exit ) {
				throw new ExitException( null, $return_code );
			}
			exit( $return_code );
		}
	}

	/**
	 * Halt script execution with a specific return code.
	 *
	 * Permits script execution to be overloaded by `WP_CLI::runcommand()`
	 *
	 * @access public
	 * @category Output
	 *
	 * @param integer $return_code
	 * @return never
	 */
	public static function halt( $return_code ) {
		if ( self::$capture_exit ) {
			throw new ExitException( null, $return_code );
		}
		exit( $return_code );
	}

	/**
	 * Display a multi-line error message in a red box. Doesn't exit script.
	 *
	 * Error message is written to STDERR.
	 *
	 * @access public
	 * @category Output
	 *
	 * @param array $message_lines Multi-line error message to be displayed.
	 */
	public static function error_multi_line( $message_lines ) {
		if ( null === self::$logger ) {
			return;
		}

		if ( ! isset( self::get_runner()->assoc_args['completions'] ) && is_array( $message_lines ) ) {
			self::$logger->error_multi_line( array_map( [ __CLASS__, 'error_to_string' ], $message_lines ) );
		}
	}

	/**
	 * Ask for confirmation before running a destructive operation.
	 *
	 * If 'y' is provided to the question, the script execution continues. If
	 * 'n' or any other response is provided to the question, script exits.
	 *
	 * ```
	 * # `wp db drop` asks for confirmation before dropping the database.
	 *
	 * WP_CLI::confirm( "Are you sure you want to drop the database?", $assoc_args );
	 * ```
	 *
	 * @access public
	 * @category Input
	 *
	 * @param string $question Question to display before the prompt.
	 * @param array $assoc_args Skips prompt if 'yes' is provided.
	 */
	public static function confirm( $question, $assoc_args = [] ) {
		if ( ! Utils\get_flag_value( $assoc_args, 'yes' ) ) {
			fwrite( STDOUT, $question . ' [y/n] ' );

			$answer = strtolower( trim( fgets( STDIN ) ) );

			if ( 'y' !== $answer ) {
				exit;
			}
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
			while ( false !== ( $line = fgets( STDIN ) ) ) {
				$raw_value .= $line;
			}
		}

		return $raw_value;
	}

	/**
	 * Read a value, from various formats.
	 *
	 * @access public
	 * @category Input
	 *
	 * @param mixed $raw_value
	 * @param array $assoc_args
	 */
	public static function read_value( $raw_value, $assoc_args = [] ) {
		if ( Utils\get_flag_value( $assoc_args, 'format' ) === 'json' ) {
			$value = json_decode( $raw_value, true );
			if ( null === $value ) {
				self::error( sprintf( 'Invalid JSON: %s', $raw_value ) );
			}
		} else {
			$value = $raw_value;
		}

		return $value;
	}

	/**
	 * Display a value, in various formats
	 *
	 * @param mixed $value Value to display.
	 * @param array $assoc_args Arguments passed to the command, determining format.
	 */
	public static function print_value( $value, $assoc_args = [] ) {
		if ( Utils\get_flag_value( $assoc_args, 'format' ) === 'json' ) {
			$value = json_encode( $value );
		} elseif ( Utils\get_flag_value( $assoc_args, 'format' ) === 'yaml' ) {
			$value = Spyc::YAMLDump( $value, 2, 0 );
		} elseif ( is_array( $value ) || is_object( $value ) ) {
			$value = var_export( $value, true );
		}

		echo $value . "\n";
	}

	/**
	 * Convert a WP_Error or Exception into a string
	 *
	 * @param string|WP_Error|Exception|Throwable $errors
	 * @throws InvalidArgumentException
	 *
	 * @return string
	 */
	public static function error_to_string( $errors ) {
		if ( is_string( $errors ) ) {
			return $errors;
		}

		// Only json_encode() the data when it needs it
		$render_data = function( $data ) {
			if ( is_array( $data ) || is_object( $data ) ) {
				return json_encode( $data );
			}

			return '"' . $data . '"';
		};

		if ( $errors instanceof WP_Error ) {
			foreach ( $errors->get_error_messages() as $message ) {
				if ( $errors->get_error_data() ) {
					return $message . ' ' . $render_data( $errors->get_error_data() );
				}

				return $message;
			}
		}

		// PHP 7+: internal and user exceptions must implement Throwable interface.
		// PHP 5: internal and user exceptions must extend Exception class.
		if ( interface_exists( 'Throwable' ) && ( $errors instanceof Throwable ) || ( $errors instanceof Exception ) ) {
			return get_class( $errors ) . ': ' . $errors->getMessage();
		}

		throw new InvalidArgumentException(
			sprintf(
				"Unsupported argument type passed to WP_CLI::error_to_string(): '%s'",
				gettype( $errors )
			)
		);
	}

	/**
	 * Launch an arbitrary external process that takes over I/O.
	 *
	 * ```
	 * # `wp core download` falls back to the `tar` binary when PharData isn't available
	 * if ( ! class_exists( 'PharData' ) ) {
	 *     $cmd = "tar xz --strip-components=1 --directory=%s -f $tarball";
	 *     WP_CLI::launch( Utils\esc_cmd( $cmd, $dest ) );
	 *     return;
	 * }
	 * ```
	 *
	 * @access public
	 * @category Execution
	 *
	 * @param string $command External process to launch.
	 * @param boolean $exit_on_error Whether to exit if the command returns an elevated return code.
	 * @param boolean $return_detailed Whether to return an exit status (default) or detailed execution results.
	 * @return int|ProcessRun The command exit status, or a ProcessRun object for full details.
	 */
	public static function launch( $command, $exit_on_error = true, $return_detailed = false ) {
		Utils\check_proc_available( 'launch' );

		$proc    = Process::create( $command );
		$results = $proc->run();

		if ( -1 === $results->return_code ) {
			self::warning( "Spawned process returned exit code {$results->return_code}, which could be caused by a custom compiled version of PHP that uses the --enable-sigchild option." );
		}

		if ( $results->return_code && $exit_on_error ) {
			exit( $results->return_code );
		}

		if ( $return_detailed ) {
			return $results;
		}

		return $results->return_code;
	}

	/**
	 * Run a WP-CLI command in a new process reusing the current runtime arguments.
	 *
	 * Use `WP_CLI::runcommand()` instead, which is easier to use and works better.
	 *
	 * Note: While this command does persist a limited set of runtime arguments,
	 * it *does not* persist environment variables. Practically speaking, WP-CLI
	 * packages won't be loaded when using WP_CLI::launch_self() because the
	 * launched process doesn't have access to the current process $HOME.
	 *
	 * @access public
	 * @category Execution
	 *
	 * @param string $command WP-CLI command to call.
	 * @param array $args Positional arguments to include when calling the command.
	 * @param array $assoc_args Associative arguments to include when calling the command.
	 * @param bool $exit_on_error Whether to exit if the command returns an elevated return code.
	 * @param bool $return_detailed Whether to return an exit status (default) or detailed execution results.
	 * @param array $runtime_args Override one or more global args (path,url,user,allow-root)
	 * @return int|ProcessRun The command exit status, or a ProcessRun instance
	 */
	public static function launch_self( $command, $args = [], $assoc_args = [], $exit_on_error = true, $return_detailed = false, $runtime_args = [] ) {
		$reused_runtime_args = [
			'path',
			'url',
			'user',
			'allow-root',
		];

		foreach ( $reused_runtime_args as $key ) {
			if ( isset( $runtime_args[ $key ] ) ) {
				$assoc_args[ $key ] = $runtime_args[ $key ];
				continue;
			}

			$value = self::get_runner()->config[ $key ];
			if ( $value ) {
				$assoc_args[ $key ] = $value;
			}
		}

		$php_bin = escapeshellarg( Utils\get_php_binary() );

		$script_path = $GLOBALS['argv'][0];

		if ( getenv( 'WP_CLI_CONFIG_PATH' ) ) {
			$config_path = getenv( 'WP_CLI_CONFIG_PATH' );
		} else {
			$config_path = Utils\get_home_dir() . '/.wp-cli/config.yml';
		}
		$config_path = escapeshellarg( $config_path );

		$args       = implode( ' ', array_map( 'escapeshellarg', $args ) );
		$assoc_args = Utils\assoc_args_to_str( $assoc_args );

		$full_command = "WP_CLI_CONFIG_PATH={$config_path} {$php_bin} {$script_path} {$command} {$args} {$assoc_args}";

		return self::launch( $full_command, $exit_on_error, $return_detailed );
	}

	/**
	 * Get the path to the PHP binary used when executing WP-CLI.
	 *
	 * Environment values permit specific binaries to be indicated.
	 *
	 * Note: moved to Utils, left for BC.
	 *
	 * @access public
	 * @category System
	 *
	 * @return string
	 */
	public static function get_php_binary() {
		return Utils\get_php_binary();
	}

	/**
	 * Confirm that a global configuration parameter does exist.
	 *
	 * @access public
	 * @category Input
	 *
	 * @param string $key Config parameter key to check.
	 *
	 * @return bool
	 */
	public static function has_config( $key ) {
		return array_key_exists( $key, self::get_runner()->config );
	}

	/**
	 * Get values of global configuration parameters.
	 *
	 * Provides access to `--path=<path>`, `--url=<url>`, and other values of
	 * the [global configuration parameters](https://wp-cli.org/config/).
	 *
	 * ```
	 * WP_CLI::log( 'The --url=<url> value is: ' . WP_CLI::get_config( 'url' ) );
	 * ```
	 *
	 * @access public
	 * @category Input
	 *
	 * @param string $key Get value for a specific global configuration parameter.
	 * @return mixed
	 */
	public static function get_config( $key = null ) {
		if ( null === $key ) {
			return self::get_runner()->config;
		}

		if ( ! isset( self::get_runner()->config[ $key ] ) ) {
			self::warning( "Unknown config option '$key'." );
			return null;
		}

		return self::get_runner()->config[ $key ];
	}

	/**
	 * Run a WP-CLI command.
	 *
	 * Launches a new child process to run a specified WP-CLI command.
	 * Optionally:
	 *
	 * * Run the command in an existing process.
	 * * Prevent halting script execution on error.
	 * * Capture and return STDOUT, or full details about command execution.
	 * * Parse JSON output if the command rendered it.
	 *
	 * ```
	 * $options = array(
	 *   'return'     => true,   // Return 'STDOUT'; use 'all' for full object.
	 *   'parse'      => 'json', // Parse captured STDOUT to JSON array.
	 *   'launch'     => false,  // Reuse the current process.
	 *   'exit_error' => true,   // Halt script execution on error.
	 * );
	 * $plugins = WP_CLI::runcommand( 'plugin list --format=json', $options );
	 * ```
	 *
	 * @access public
	 * @category Execution
	 *
	 * @param string $command WP-CLI command to run, including arguments.
	 * @param array  $options Configuration options for command execution.
	 * @return mixed
	 */
	public static function runcommand( $command, $options = [] ) {
		$defaults   = [
			'launch'     => true, // Launch a new process, or reuse the existing.
			'exit_error' => true, // Exit on error by default.
			'return'     => false, // Capture and return output, or render in realtime.
			'parse'      => false, // Parse returned output as a particular format.
		];
		$options    = array_merge( $defaults, $options );
		$launch     = $options['launch'];
		$exit_error = $options['exit_error'];
		$return     = $options['return'];
		$parse      = $options['parse'];
		$retval     = null;
		if ( $launch ) {
			Utils\check_proc_available( 'launch option' );

			$descriptors = [
				0 => STDIN,
				1 => STDOUT,
				2 => STDERR,
			];

			if ( $return ) {
				$descriptors = [
					0 => STDIN,
					1 => [ 'pipe', 'w' ],
					2 => [ 'pipe', 'w' ],
				];
			}

			$php_bin     = escapeshellarg( Utils\get_php_binary() );
			$script_path = $GLOBALS['argv'][0];

			// Persist runtime arguments unless they've been specified otherwise.
			$configurator = self::get_configurator();
			$argv         = array_slice( $GLOBALS['argv'], 1 );

			list( $ignore1, $ignore2, $runtime_config ) = $configurator->parse_args( $argv );
			foreach ( $runtime_config as $k => $v ) {
				if ( preg_match( "|^--{$k}=?$|", $command ) ) {
					unset( $runtime_config[ $k ] );
				}
			}
			$runtime_config = Utils\assoc_args_to_str( $runtime_config );

			$runcommand = "{$php_bin} {$script_path} {$runtime_config} {$command}";

			$pipes = [];
			$proc  = Utils\proc_open_compat( $runcommand, $descriptors, $pipes, getcwd() );

			if ( $return ) {
				$stdout = stream_get_contents( $pipes[1] );
				fclose( $pipes[1] );
				$stderr = stream_get_contents( $pipes[2] );
				fclose( $pipes[2] );
			}
			$return_code = proc_close( $proc );
			if ( -1 === $return_code ) {
				self::warning( 'Spawned process returned exit code -1, which could be caused by a custom compiled version of PHP that uses the --enable-sigchild option.' );
			} elseif ( $return_code && $exit_error ) {
				exit( $return_code );
			}
			if ( true === $return || 'stdout' === $return ) {
				$retval = trim( $stdout );
			} elseif ( 'stderr' === $return ) {
				$retval = trim( $stderr );
			} elseif ( 'return_code' === $return ) {
				$retval = $return_code;
			} elseif ( 'all' === $return ) {
				$retval = (object) [
					'stdout'      => trim( $stdout ),
					'stderr'      => trim( $stderr ),
					'return_code' => $return_code,
				];
			}
		} else {
			$configurator                               = self::get_configurator();
			$argv                                       = Utils\parse_str_to_argv( $command );
			list( $args, $assoc_args, $runtime_config ) = $configurator->parse_args( $argv );
			if ( $return ) {
				$existing_logger = self::$logger;
				self::$logger    = new Execution();
				self::$logger->ob_start();
			}
			if ( ! $exit_error ) {
				self::$capture_exit = true;
			}
			try {
				self::get_runner()->run_command(
					$args,
					$assoc_args,
					[
						'back_compat_conversions' => true,
					]
				);
				$return_code = 0;
			} catch ( ExitException $e ) {
				$return_code = $e->getCode();
			}
			if ( $return ) {
				$execution_logger = self::$logger;
				$execution_logger->ob_end();
				self::$logger = $existing_logger;
				$stdout       = $execution_logger->stdout;
				$stderr       = $execution_logger->stderr;
				if ( true === $return || 'stdout' === $return ) {
					$retval = trim( $stdout );
				} elseif ( 'stderr' === $return ) {
					$retval = trim( $stderr );
				} elseif ( 'return_code' === $return ) {
					$retval = $return_code;
				} elseif ( 'all' === $return ) {
					$retval = (object) [
						'stdout'      => trim( $stdout ),
						'stderr'      => trim( $stderr ),
						'return_code' => $return_code,
					];
				}
			}
			if ( ! $exit_error ) {
				self::$capture_exit = false;
			}
		}
		if ( ( true === $return || 'stdout' === $return )
			&& 'json' === $parse ) {
			$retval = json_decode( $retval, true );
		}
		return $retval;
	}

	/**
	 * Run a given command within the current process using the same global
	 * parameters.
	 *
	 * Use `WP_CLI::runcommand()` instead, which is easier to use and works better.
	 *
	 * To run a command using a new process with the same global parameters,
	 * use WP_CLI::launch_self(). To run a command using a new process with
	 * different global parameters, use WP_CLI::launch().
	 *
	 * ```
	 * ob_start();
	 * WP_CLI::run_command( array( 'cli', 'cmd-dump' ) );
	 * $ret = ob_get_clean();
	 * ```
	 *
	 * @access public
	 * @category Execution
	 *
	 * @param array $args Positional arguments including command name.
	 * @param array $assoc_args
	 */
	public static function run_command( $args, $assoc_args = [] ) {
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
	// phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- Deprecated method.
	public static function addCommand( $name, $class ) {
		trigger_error(
			sprintf(
				'wp %s: %s is deprecated. use WP_CLI::add_command() instead.',
				$name,
				__FUNCTION__
			),
			E_USER_WARNING
		);
		self::add_command( $name, $class );
	}
}
