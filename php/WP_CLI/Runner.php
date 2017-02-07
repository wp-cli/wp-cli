<?php

namespace WP_CLI;

use WP_CLI;
use WP_CLI\Utils;
use WP_CLI\Dispatcher;

/**
 * Performs the execution of a command.
 *
 * @package WP_CLI
 */
class Runner {

	private $global_config_path, $project_config_path;

	private $config, $extra_config;

	private $alias;

	private $aliases;

	private $arguments, $assoc_args;

	private $_early_invoke = array();

	private $_global_config_path_debug;

	private $_project_config_path_debug;

	private $_required_files;

	public function __get( $key ) {
		if ( '_' === $key[0] )
			return null;

		return $this->$key;
	}

	/**
	 * Register a command for early invocation, generally before WordPress loads.
	 *
	 * @param string $when Named execution hook
	 * @param WP_CLI\Dispatcher\Subcommand $command
	 */
	public function register_early_invoke( $when, $command ) {
		$this->_early_invoke[ $when ][] = array_slice( Dispatcher\get_path( $command ), 1 );
	}

	/**
	 * Perform the early invocation of a command.
	 *
	 * @param string $when Named execution hook
	 */
	private function do_early_invoke( $when ) {
		if ( !isset( $this->_early_invoke[ $when ] ) )
			return;

		foreach ( $this->_early_invoke[ $when ] as $path ) {
			if ( $this->cmd_starts_with( $path ) ) {
				$this->_run_command();
				exit;
			}
		}
	}

	/**
	 * Get the path to the global configuration YAML file.
	 *
	 * @return string|false
	 */
	private function get_global_config_path() {

		if ( getenv( 'WP_CLI_CONFIG_PATH' ) ) {
			$config_path = getenv( 'WP_CLI_CONFIG_PATH' );
			$this->_global_config_path_debug = 'Using global config from WP_CLI_CONFIG_PATH env var: ' . $config_path;
		} else {
			$config_path = getenv( 'HOME' ) . '/.wp-cli/config.yml';
			$this->_global_config_path_debug = 'Using default global config: ' . $config_path;
		}

		if ( is_readable( $config_path ) ) {
			return $config_path;
		} else {
			$this->_global_config_path_debug = 'No readable global config found';
			return false;
		}
	}

	/**
	 * Get the path to the project-specific configuration
	 * YAML file.
	 * wp-cli.local.yml takes priority over wp-cli.yml.
	 *
	 * @return string|false
	 */
	private function get_project_config_path() {
		$config_files = array(
			'wp-cli.local.yml',
			'wp-cli.yml'
		);

		// Stop looking upward when we find we have emerged from a subdirectory
		// install into a parent install
		$project_config_path = Utils\find_file_upward( $config_files, getcwd(), function ( $dir ) {
			static $wp_load_count = 0;
			$wp_load_path = $dir . DIRECTORY_SEPARATOR . 'wp-load.php';
			if ( file_exists( $wp_load_path ) ) {
				$wp_load_count += 1;
			}
			return $wp_load_count > 1;
		} );
		if ( ! empty( $project_config_path ) ) {
			$this->_project_config_path_debug = 'Using project config: ' . $project_config_path;
		} else {
			$this->_project_config_path_debug = 'No project config found';
		}
		return $project_config_path;
	}

	/**
	 * Get the path to the packages directory
	 *
	 * @return string
	 */
	public function get_packages_dir_path() {
		if ( getenv( 'WP_CLI_PACKAGES_DIR' ) ) {
			$packages_dir = rtrim( getenv( 'WP_CLI_PACKAGES_DIR' ), '/' ) . '/';
		} else {
			$packages_dir = getenv( 'HOME' ) . '/.wp-cli/packages/';
		}
		return $packages_dir;
	}

	/**
	 * Attempts to find the path to the WP install inside index.php
	 *
	 * @param string $index_path
	 * @return string|false
	 */
	private static function extract_subdir_path( $index_path ) {
		$index_code = file_get_contents( $index_path );

		if ( !preg_match( '|^\s*require\s*\(?\s*(.+?)/wp-blog-header\.php([\'"])|m', $index_code, $matches ) ) {
			return false;
		}

		$wp_path_src = $matches[1] . $matches[2];
		$wp_path_src = Utils\replace_path_consts( $wp_path_src, $index_path );
		$wp_path = eval( "return $wp_path_src;" );

		if ( !Utils\is_path_absolute( $wp_path ) ) {
			$wp_path = dirname( $index_path ) . "/$wp_path";
		}

		return $wp_path;
	}

	/**
	 * Find the directory that contains the WordPress files.
	 * Defaults to the current working dir.
	 *
	 * @return string An absolute path
	 */
	private function find_wp_root() {
		if ( !empty( $this->config['path'] ) ) {
			$path = $this->config['path'];
			if ( !Utils\is_path_absolute( $path ) )
				$path = getcwd() . '/' . $path;

			return $path;
		}

		if ( $this->cmd_starts_with( array( 'core', 'download' ) ) ) {
			return getcwd();
		}

		$dir = getcwd();

		while ( is_readable( $dir ) ) {
			if ( file_exists( "$dir/wp-load.php" ) ) {
				return $dir;
			}

			if ( file_exists( "$dir/index.php" ) ) {
				if ( $path = self::extract_subdir_path( "$dir/index.php" ) )
					return $path;
			}

			$parent_dir = dirname( $dir );
			if ( empty($parent_dir) || $parent_dir === $dir ) {
				break;
			}
			$dir = $parent_dir;
		}
	}

	/**
	 * Set WordPress root as a given path.
	 *
	 * @param string $path
	 */
	private static function set_wp_root( $path ) {
		define( 'ABSPATH', rtrim( $path, '/' ) . '/' );
		WP_CLI::debug( 'ABSPATH defined: ' . ABSPATH, 'bootstrap' );

		$_SERVER['DOCUMENT_ROOT'] = realpath( $path );
	}

	/**
	 * Guess which URL context WP-CLI has been invoked under.
	 *
	 * @param array $assoc_args
	 * @return string|false
	 */
	private static function guess_url( $assoc_args ) {
		if ( isset( $assoc_args['blog'] ) ) {
			$assoc_args['url'] = $assoc_args['blog'];
		}

		if ( isset( $assoc_args['url'] ) ) {
			$url = $assoc_args['url'];
			if ( true === $url ) {
				WP_CLI::warning( 'The --url parameter expects a value.' );
			}
		}

		if ( isset( $url ) ) {
			return $url;
		}

		return false;
	}

	private function cmd_starts_with( $prefix ) {
		return $prefix == array_slice( $this->arguments, 0, count( $prefix ) );
	}

	/**
	 * Given positional arguments, find the command to execute.
	 *
	 * @param array $args
	 * @return array|string Command, args, and path on success; error message on failure
	 */
	public function find_command_to_run( $args ) {
		$command = \WP_CLI::get_root_command();

		$cmd_path = array();

		while ( !empty( $args ) && $command->can_have_subcommands() ) {
			$cmd_path[] = $args[0];
			$full_name = implode( ' ', $cmd_path );

			$subcommand = $command->find_subcommand( $args );

			if ( !$subcommand ) {
				if ( count( $cmd_path ) > 1 ) {
					$child = array_pop( $cmd_path );
					$parent_name = implode( ' ', $cmd_path );
					return sprintf(
						"'%s' is not a registered subcommand of '%s'. See 'wp help %s'.",
						$child,
						$parent_name,
						$parent_name
					);
				} else {
					return sprintf(
						"'%s' is not a registered wp command. See 'wp help'.",
						$full_name
					);
				}
			}

			if ( $this->is_command_disabled( $subcommand ) ) {
				return sprintf(
					"The '%s' command has been disabled from the config file.",
					$full_name
				);
			}

			$command = $subcommand;
		}

		return array( $command, $args, $cmd_path );
	}

	/**
	 * Find the WP-CLI command to run given arguments, and invoke it.
	 *
	 * @param array $args        Positional arguments including command name
	 * @param array $assoc_args  Associative arguments for the command.
	 * @param array $options     Configuration options for the function.
	 */
	public function run_command( $args, $assoc_args = array(), $options = array() ) {
		if ( ! empty( $options['back_compat_conversions'] ) ) {
			list( $args, $assoc_args ) = self::back_compat_conversions( $args, $assoc_args );
		}
		$r = $this->find_command_to_run( $args );
		if ( is_string( $r ) ) {
			WP_CLI::error( $r );
		}

		list( $command, $final_args, $cmd_path ) = $r;

		$name = implode( ' ', $cmd_path );

		if ( isset( $this->extra_config[ $name ] ) ) {
			$extra_args = $this->extra_config[ $name ];
		} else {
			$extra_args = array();
		}

		WP_CLI::debug( 'Running command: ' . $name, 'bootstrap' );
		try {
			$command->invoke( $final_args, $assoc_args, $extra_args );
		} catch ( WP_CLI\Iterators\Exception $e ) {
			WP_CLI::error( $e->getMessage() );
		}
	}

	private function _run_command() {
		$this->run_command( $this->arguments, $this->assoc_args );
	}

	/**
	 * Perform a command against a remote server over SSH
	 */
	private function run_ssh_command( $ssh ) {

		WP_CLI::do_hook( 'before_ssh' );

		// host OR host/path/to/wordpress OR host:port/path/to/wordpress
		$bits = Utils\parse_ssh_url( $ssh );
		$host = isset( $bits['host'] ) ? $bits['host'] : null;
		$port = isset( $bits['port'] ) ? $bits['port'] : null;
		$path = isset( $bits['path'] ) ? $bits['path'] : null;

		WP_CLI::debug( 'SSH host: ' . $host, 'bootstrap' );
		WP_CLI::debug( 'SSH port: ' . $port, 'bootstrap' );
		WP_CLI::debug( 'SSH path: ' . $path, 'bootstrap' );

		$is_tty = function_exists( 'posix_isatty' ) && posix_isatty( STDOUT );

		$pre_cmd = getenv( 'WP_CLI_SSH_PRE_CMD' );
		if ( $pre_cmd ) {
			$pre_cmd = rtrim( $pre_cmd, ';' ) . '; ';
		}
		if ( $path ) {
			$pre_cmd .= "cd {$path}; ";
		}
		$wp_binary = 'wp';
		$wp_args = array_slice( $GLOBALS['argv'], 1 );

		if ( $this->alias && ! empty( $wp_args[0] ) && $this->alias === $wp_args[0] ) {
			array_shift( $wp_args );
			$runtime_alias = array();
			foreach( $this->aliases[ $this->alias ] as $key => $value ) {
				if ( 'ssh' === $key ) {
					continue;
				}
				$runtime_alias[ $key ] = $value;
			}
			if ( ! empty( $runtime_alias ) ) {
				$encoded_alias = json_encode( array( $this->alias => $runtime_alias ) );
				$wp_binary = "WP_CLI_RUNTIME_ALIAS='{$encoded_alias}' {$wp_binary} {$this->alias}";
			}
		}

		foreach( $wp_args as $k => $v ) {
			if ( preg_match( '#--ssh=#', $v ) ) {
				unset( $wp_args[ $k ] );
			}
		}

		$unescaped_command = sprintf(
			'ssh -q %s%s %s %s',
			$port ? '-p ' . (int) $port . ' ' : '',
			$host,
			$is_tty ? '-t' : '-T',
			$pre_cmd . $wp_binary . ' ' . implode( ' ', array_map( 'escapeshellarg', $wp_args ) )
		);

		WP_CLI::debug( 'Running SSH command: ' . $unescaped_command, 'bootstrap' );

		$escaped_command = sprintf(
			'ssh -q %s%s %s %s',
			$port ? '-p ' . (int) $port . ' ' : '',
			escapeshellarg( $host ),
			$is_tty ? '-t' : '-T',
			escapeshellarg( $pre_cmd . $wp_binary . ' ' . implode( ' ', array_map( 'escapeshellarg', $wp_args ) ) )
		);

		passthru( $escaped_command, $exit_code );
		if ( 255 === $exit_code ) {
			WP_CLI::error( 'Cannot connect over SSH using provided configuration.', 255 );
		} else {
			exit( $exit_code );
		}
	}

	/**
	 * Check whether a given command is disabled by the config
	 *
	 * @return bool
	 */
	public function is_command_disabled( $command ) {
		$path = implode( ' ', array_slice( \WP_CLI\Dispatcher\get_path( $command ), 1 ) );
		return in_array( $path, $this->config['disabled_commands'] );
	}

	/**
	 * Returns wp-config.php code, skipping the loading of wp-settings.php
	 *
	 * @return string
	 */
	public function get_wp_config_code() {
		$wp_config_path = Utils\locate_wp_config();

		$wp_config_code = explode( "\n", file_get_contents( $wp_config_path ) );

		$found_wp_settings = false;

		$lines_to_run = array();

		foreach ( $wp_config_code as $line ) {
			if ( preg_match( '/^\s*require.+wp-settings\.php/', $line ) ) {
				$found_wp_settings = true;
				continue;
			}

			$lines_to_run[] = $line;
		}

		if ( !$found_wp_settings ) {
			WP_CLI::error( 'Strange wp-config.php file: wp-settings.php is not loaded directly.' );
		}

		$source = implode( "\n", $lines_to_run );
		$source = Utils\replace_path_consts( $source, $wp_config_path );
		return preg_replace( '|^\s*\<\?php\s*|', '', $source );
	}

	/**
	 * Transparently convert deprecated syntaxes
	 *
	 * @param array $args
	 * @param array $assoc_args
	 * @return array
	 */
	private static function back_compat_conversions( $args, $assoc_args ) {
		$top_level_aliases = array(
			'sql' => 'db',
			'blog' => 'site'
		);
		if ( count( $args ) > 0 ) {
			foreach ( $top_level_aliases as $old => $new ) {
				if ( $old == $args[0] ) {
					$args[0] = $new;
					break;
				}
			}
		}

		// *-meta  ->  * meta
		if ( !empty( $args ) && preg_match( '/(post|comment|user|network)-meta/', $args[0], $matches ) ) {
			array_shift( $args );
			array_unshift( $args, 'meta' );
			array_unshift( $args, $matches[1] );
		}

		// core (multsite-)install --admin_name=  ->  --admin_user=
		if ( count( $args ) > 0 && 'core' == $args[0] && isset( $assoc_args['admin_name'] ) ) {
			$assoc_args['admin_user'] = $assoc_args['admin_name'];
			unset( $assoc_args['admin_name'] );
		}

		// site create --site_id=  ->  site create --network_id=
		if ( count( $args ) >= 2 && 'site' === $args[0] && 'create' === $args[1] && isset( $assoc_args['site_id'] ) ) {
			$assoc_args['network_id'] = $assoc_args['site_id'];
			unset( $assoc_args['site_id'] );
		}

		// {plugin|theme} update-all  ->  {plugin|theme} update --all
		if ( count( $args ) > 1 && in_array( $args[0], array( 'plugin', 'theme' ) )
			&& $args[1] == 'update-all'
		) {
			$args[1] = 'update';
			$assoc_args['all'] = true;
		}

		// transient delete-expired  ->  transient delete --expired
		if ( count( $args ) > 1 && 'transient' === $args[0] && 'delete-expired' === $args[1] ) {
			$args[1] = 'delete';
			$assoc_args['expired'] = true;
		}

		// transient delete-all  ->  transient delete --all
		if ( count( $args ) > 1 && 'transient' === $args[0] && 'delete-all' === $args[1] ) {
			$args[1] = 'delete';
			$assoc_args['all'] = true;
		}

		// plugin scaffold  ->  scaffold plugin
		if ( array( 'plugin', 'scaffold' ) == array_slice( $args, 0, 2 ) ) {
			list( $args[0], $args[1] ) = array( $args[1], $args[0] );
		}

		// foo --help  ->  help foo
		if ( isset( $assoc_args['help'] ) ) {
			array_unshift( $args, 'help' );
			unset( $assoc_args['help'] );
		}

		// {post|user} list --ids  ->  {post|user} list --format=ids
		if ( count( $args ) > 1 && in_array( $args[0], array( 'post', 'user' ) )
			&& $args[1] == 'list'
			&& isset( $assoc_args['ids'] )
		) {
			$assoc_args['format'] = 'ids';
			unset( $assoc_args['ids'] );
		}

		// --json  ->  --format=json
		if ( isset( $assoc_args['json'] ) ) {
			$assoc_args['format'] = 'json';
			unset( $assoc_args['json'] );
		}

		// --{version|info}  ->  cli {version|info}
		if ( empty( $args ) ) {
			$special_flags = array( 'version', 'info' );
			foreach ( $special_flags as $key ) {
				if ( isset( $assoc_args[ $key ] ) ) {
					$args = array( 'cli', $key );
					unset( $assoc_args[ $key ] );
					break;
				}
			}
		}

		// (post|comment|site|term) url  --> (post|comment|site|term) list --*__in --field=url
		if ( count( $args ) >= 2 && in_array( $args[0], array( 'post', 'comment', 'site', 'term' ) ) && 'url' === $args[1] ) {
			switch ( $args[0] ) {
				case 'post':
					$post_ids = array_slice( $args, 2 );
					$args = array( 'post', 'list' );
					$assoc_args['post__in'] = implode( ',', $post_ids );
					$assoc_args['post_type'] = 'any';
					$assoc_args['orderby'] = 'post__in';
					$assoc_args['field'] = 'url';
					break;
				case 'comment':
					$comment_ids = array_slice( $args, 2 );
					$args = array( 'comment', 'list' );
					$assoc_args['comment__in'] = implode( ',', $comment_ids );
					$assoc_args['orderby'] = 'comment__in';
					$assoc_args['field'] = 'url';
					break;
				case 'site':
					$site_ids = array_slice( $args, 2 );
					$args = array( 'site', 'list' );
					$assoc_args['site__in'] = implode( ',', $site_ids );
					$assoc_args['field'] = 'url';
					break;
				case 'term':
					$taxonomy = '';
					if ( isset( $args[2] ) ) {
						$taxonomy = $args[2];
					}
					$term_ids = array_slice( $args, 3 );
					$args = array( 'term', 'list', $taxonomy );
					$assoc_args['include'] = implode( ',', $term_ids );
					$assoc_args['orderby'] = 'include';
					$assoc_args['field'] = 'url';
					break;
			}
		}

		return array( $args, $assoc_args );
	}

	/**
	 * Whether or not the output should be rendered in color
	 *
	 * @return bool
	 */
	public function in_color() {
		return $this->colorize;
	}

	private function init_colorization() {
		if ( 'auto' === $this->config['color'] ) {
			$this->colorize = ( !\cli\Shell::isPiped() && !\WP_CLI\Utils\is_windows() );
		} else {
			$this->colorize = $this->config['color'];
		}
	}

	private function init_logger() {
		if ( $this->config['quiet'] )
			$logger = new \WP_CLI\Loggers\Quiet;
		else
			$logger = new \WP_CLI\Loggers\Regular( $this->in_color() );

		WP_CLI::set_logger( $logger );
	}

	/**
	 * Do WordPress core files exist?
	 *
	 * @return bool
	 */
	private function wp_exists() {
		return is_readable( ABSPATH . 'wp-includes/version.php' );
	}

	private function check_wp_version() {
		if ( !$this->wp_exists() ) {
			WP_CLI::error(
				"This does not seem to be a WordPress install.\n" .
				"Pass --path=`path/to/wordpress` or run `wp core download`." );
		}

		global $wp_version;
		include ABSPATH . 'wp-includes/version.php';

		$minimum_version = '3.7';

		// @codingStandardsIgnoreStart
		if ( version_compare( $wp_version, $minimum_version, '<' ) ) {
			WP_CLI::error(
				"WP-CLI needs WordPress $minimum_version or later to work properly. " .
				"The version currently installed is $wp_version.\n" .
				"Try running `wp core download --force`."
			);
		}
		// @codingStandardsIgnoreEnd
	}

	private function init_config() {
		$configurator = \WP_CLI::get_configurator();

		$argv = array_slice( $GLOBALS['argv'], 1 );

		if ( ! empty( $argv[0] ) && preg_match( '#' . Configurator::ALIAS_REGEX . '#', $argv[0], $matches ) ) {
			$this->alias = array_shift( $argv );
		} else {
			$this->alias = null;
		}

		// File config
		{
			$this->global_config_path = $this->get_global_config_path();
			$this->project_config_path = $this->get_project_config_path();

			$configurator->merge_yml( $this->global_config_path, $this->alias );
			$config = $configurator->to_array();
			$this->_required_files['global'] = $config[0]['require'];
			$configurator->merge_yml( $this->project_config_path, $this->alias );
			$config = $configurator->to_array();
			$this->_required_files['project'] = $config[0]['require'];
		}

		// Runtime config and args
		{
			list( $args, $assoc_args, $runtime_config ) = $configurator->parse_args( $argv );

			list( $this->arguments, $this->assoc_args ) = self::back_compat_conversions(
				$args, $assoc_args );

			$configurator->merge_array( $runtime_config );
		}

		list( $this->config, $this->extra_config ) = $configurator->to_array();
		$this->aliases = $configurator->get_aliases();
		if ( count( $this->aliases ) && ! isset( $this->aliases['@all'] ) ) {
			$this->aliases = array_reverse( $this->aliases );
			$this->aliases['@all'] = 'Run command against every registered alias.';
			$this->aliases = array_reverse( $this->aliases );
		}
		$this->_required_files['runtime'] = $this->config['require'];
	}

	private function check_root() {
		if ( $this->config['allow-root'] ) {
			return; # they're aware of the risks!
		}
		if ( count( $this->arguments ) >= 2 && 'cli' === $this->arguments[0] && 'update' === $this->arguments[1] ) {
			return; # make it easier to update root-owned copies
		}
		if ( !function_exists( 'posix_geteuid') ) {
			return; # posix functions not available
		}
		if ( posix_geteuid() !== 0 ) {
			return; # not root
		}

		WP_CLI::error(
			"YIKES! It looks like you're running this as root. You probably meant to " .
			"run this as the user that your WordPress install exists under.\n" .
			"\n" .
			"If you REALLY mean to run this as root, we won't stop you, but just " .
			"bear in mind that any code on this site will then have full control of " .
			"your server, making it quite DANGEROUS.\n" .
			"\n" .
			"If you'd like to continue as root, please run this again, adding this " .
			"flag:  --allow-root\n" .
			"\n" .
			"If you'd like to run it as the user that this site is under, you can " .
			"run the following to become the respective user:\n" .
			"\n" .
			"    sudo -u USER -i -- wp <command>\n" .
			"\n"
		);
	}

	private function run_alias_group( $aliases ) {
		$php_bin = WP_CLI::get_php_binary();

		$script_path = $GLOBALS['argv'][0];

		if ( getenv( 'WP_CLI_CONFIG_PATH' ) ) {
			$config_path = getenv( 'WP_CLI_CONFIG_PATH' );
		} else {
			$config_path = getenv( 'HOME' ) . '/.wp-cli/config.yml';
		}
		$config_path = escapeshellarg( $config_path );

		foreach( $aliases as $alias ) {
			WP_CLI::log( $alias );
			$args = implode( ' ', array_map( 'escapeshellarg', $this->arguments ) );
			$assoc_args = Utils\assoc_args_to_str( $this->assoc_args );
			$full_command = "WP_CLI_CONFIG_PATH={$config_path} {$php_bin} {$script_path} {$alias} {$args} {$assoc_args}";
			$proc = proc_open( $full_command, array( STDIN, STDOUT, STDERR ), $pipes );
			proc_close( $proc );
		}
	}

	private function set_alias( $alias ) {
		$orig_config = $this->config;
		$alias_config = $this->aliases[ $this->alias ];
		$this->config = array_merge( $orig_config, $alias_config );
		foreach( $alias_config as $key => $_ ) {
			if ( isset( $orig_config[ $key ] ) && ! is_null( $orig_config[ $key ] ) ) {
				$this->assoc_args[ $key ] = $orig_config[ $key ];
			}
		}
	}

	public function start() {
		$this->init_config();
		$this->init_colorization();
		$this->init_logger();

		WP_CLI::debug( $this->_global_config_path_debug, 'bootstrap' );
		WP_CLI::debug( $this->_project_config_path_debug, 'bootstrap' );

		$this->check_root();
		if ( $this->alias ) {
			if ( '@all' === $this->alias && ! isset( $this->aliases['@all'] ) ) {
				WP_CLI::error( "Cannot use '@all' when no aliases are registered." );
			}

			if ( '@all' === $this->alias && is_string( $this->aliases['@all'] ) ) {
				$aliases = array_keys( $this->aliases );
				$k = array_search( '@all', $aliases );
				unset( $aliases[ $k ] );
				$this->run_alias_group( $aliases );
				exit;
			}

			if ( ! array_key_exists( $this->alias, $this->aliases ) ) {
				WP_CLI::error( "Alias '{$this->alias}' not found." );
			}
			// Numerically indexed means a group of aliases
			if ( isset( $this->aliases[ $this->alias ][0] ) ) {
				$group_aliases = $this->aliases[ $this->alias ];
				$all_aliases = array_keys( $this->aliases );
				if ( $diff = array_diff( $group_aliases, $all_aliases ) ) {
					WP_CLI::error( "Group '{$this->alias}' contains one or more invalid aliases: " . implode( ', ', $diff ) );
				}
				$this->run_alias_group( $group_aliases );
				exit;
			} else {
				$this->set_alias( $this->alias );
			}
		}

		if ( empty( $this->arguments ) )
			$this->arguments[] = 'help';

		// Protect 'cli info' from most of the runtime,
		// except when the command will be run over SSH
		if ( 'cli' === $this->arguments[0] && ! empty( $this->arguments[1] ) && 'info' === $this->arguments[1] && ! $this->config['ssh'] ) {
			$this->_run_command();
			exit;
		}

		// Protect 'package' commands from most of the runtime too,
		// except when the command will be run over SSH
		if ( 'package' === $this->arguments[0] && ! $this->config['ssh'] ) {
			$this->_run_command();
			exit;
		}

		// Load bundled commands early, so that they're forced to use the same
		// APIs as non-bundled commands.
		Utils\load_all_commands();

		$skip_packages = \WP_CLI::get_runner()->config['skip-packages'];
		if ( true === $skip_packages ) {
			WP_CLI::debug( 'Skipped loading packages.', 'bootstrap' );
		} else {
			$package_autoload = $this->get_packages_dir_path() . 'vendor/autoload.php';
			if ( file_exists( $package_autoload ) ) {
				WP_CLI::debug( 'Loading packages from: ' . $package_autoload, 'bootstrap' );
				require_once $package_autoload;
			} else {
				WP_CLI::debug( 'No package autoload found to load.', 'bootstrap' );
			}
		}

		if ( isset( $this->config['http'] ) && ! class_exists( '\WP_REST_CLI\Runner' ) ) {
			WP_CLI::error( "RESTful WP-CLI needs to be installed. Try 'wp package install wp-cli/restful'." );
		}

		if ( isset( $this->config['require'] ) ) {
			foreach ( $this->config['require'] as $path ) {
				if ( ! file_exists( $path ) ) {
					$context = '';
					foreach( array( 'global', 'project', 'runtime' ) as $scope ) {
						if ( in_array( $path, $this->_required_files[ $scope ] ) ) {
							switch ( $scope ) {
								case 'global':
									$context = ' (from global ' . basename( $this->global_config_path ) . ')';
									break;
								case 'project':
									$context = ' (from project\'s ' . basename( $this->project_config_path ) . ')';
									break;
								case 'runtime':
									$context = ' (from runtime argument)';
									break;
							}
							break;
						}
					}
					WP_CLI::error( sprintf( "Required file '%s' doesn't exist%s.", basename( $path ), $context ) );
				}
				Utils\load_file( $path );
				WP_CLI::debug( 'Required file from config: ' . $path, 'bootstrap' );
			}
		}

		if ( $this->config['ssh'] ) {
			$this->run_ssh_command( $this->config['ssh'] );
			return;
		}

		// Show synopsis if it's a composite command.
		$r = $this->find_command_to_run( $this->arguments );
		if ( is_array( $r ) ) {
			list( $command ) = $r;

			if ( $command->can_have_subcommands() ) {
				$command->show_usage();
				exit;
			}
		}

		// Handle --path parameter
		self::set_wp_root( $this->find_wp_root() );

		// First try at showing man page
		if ( ! empty( $this->arguments[0] ) && 'help' === $this->arguments[0] && ( ! $this->wp_exists() || ! Utils\locate_wp_config() || ( ! empty( $this->arguments[1] ) && ! empty( $this->arguments[2] ) && 'core' === $this->arguments[1] && in_array( $this->arguments[2], array( 'config', 'install', 'multisite-install', 'verify-checksums', 'version' ) ) ) ) ) {
			$this->auto_check_update();
			$this->_run_command();
		}

		// Handle --url parameter
		$url = self::guess_url( $this->config );
		if ( $url )
			\WP_CLI::set_url( $url );

		$this->do_early_invoke( 'before_wp_load' );

		$this->check_wp_version();

		if ( $this->cmd_starts_with( array( 'core', 'config' ) ) ) {
			$this->_run_command();
			exit;
		}

		if ( !Utils\locate_wp_config() ) {
			WP_CLI::error(
				"'wp-config.php' not found.\n" .
				"Either create one manually or use `wp core config`." );
		}

		if ( ( $this->cmd_starts_with( array( 'db' ) ) || $this->cmd_starts_with( array( 'help', 'db' ) ) )
			&& ! $this->cmd_starts_with( array( 'db', 'tables' ) ) ) {
			eval( $this->get_wp_config_code() );
			$this->_run_command();
			exit;
		}

		if ( $this->cmd_starts_with( array( 'core', 'is-installed' ) )
			|| $this->cmd_starts_with( array( 'core', 'update-db' ) ) ) {
			define( 'WP_INSTALLING', true );
		}

		if (
			count( $this->arguments ) >= 2 &&
			$this->arguments[0] == 'core' &&
			in_array( $this->arguments[1], array( 'install', 'multisite-install' ) )
		) {
			define( 'WP_INSTALLING', true );

			// We really need a URL here
			if ( !isset( $_SERVER['HTTP_HOST'] ) ) {
				$url = 'http://example.com';
				\WP_CLI::set_url( $url );
			}

			if ( 'multisite-install' == $this->arguments[1] ) {
				// need to fake some globals to skip the checks in wp-includes/ms-settings.php
				$url_parts = Utils\parse_url( $url );
				self::fake_current_site_blog( $url_parts );

				if ( !defined( 'COOKIEHASH' ) ) {
					define( 'COOKIEHASH', md5( $url_parts['host'] ) );
				}
			}
		}

		if ( $this->cmd_starts_with( array( 'import') ) ) {
			define( 'WP_LOAD_IMPORTERS', true );
			define( 'WP_IMPORTING', true );
		}

		if ( $this->cmd_starts_with( array( 'cron', 'event', 'run' ) ) ) {
			define( 'DOING_CRON', true );
		}

		$this->load_wordpress();

		$this->_run_command();

	}

	/**
	 * Load WordPress, if it hasn't already been loaded
	 */
	public function load_wordpress() {
		static $wp_cli_is_loaded;
		// Globals not explicitly globalized in WordPress
		global $site_id, $wpdb, $public, $current_site, $current_blog, $path, $shortcode_tags;

		if ( ! empty( $wp_cli_is_loaded ) ) {
			return;
		}

		$wp_cli_is_loaded = true;

		WP_CLI::debug( 'Begin WordPress load', 'bootstrap' );
		WP_CLI::do_hook( 'before_wp_load' );

		$this->check_wp_version();

		$wp_config_path = Utils\locate_wp_config();
		if ( ! $wp_config_path ) {
			WP_CLI::error(
				"'wp-config.php' not found.\n" .
				"Either create one manually or use `wp core config`." );
		}

		WP_CLI::debug( 'wp-config.php path: ' . $wp_config_path, 'bootstrap' );
		WP_CLI::do_hook( 'before_wp_config_load' );

		// Load wp-config.php code, in the global scope
		$wp_cli_original_defined_vars = get_defined_vars();
		eval( $this->get_wp_config_code() );
		foreach( get_defined_vars() as $key => $var ) {
			if ( array_key_exists( $key, $wp_cli_original_defined_vars ) || 'wp_cli_original_defined_vars' === $key ) {
				continue;
			}
			global $$key;
			$$key = $var;
		}

		$this->maybe_update_url_from_domain_constant();
		WP_CLI::do_hook( 'after_wp_config_load' );

		// Prevent error notice from wp_guess_url() when core isn't installed
		if ( $this->cmd_starts_with( array( 'core', 'is-installed' ) )
			&& ! defined( 'COOKIEHASH' ) ) {
			define( 'COOKIEHASH', md5( 'wp-cli' ) );
		}

		// Load WP-CLI utilities
		require WP_CLI_ROOT . '/php/utils-wp.php';

		// Set up WordPress bootstrap actions and filters
		$this->setup_bootstrap_hooks();

		// Load Core, mu-plugins, plugins, themes etc.
		if ( Utils\wp_version_compare( '4.6-alpha-37575', '>=' ) ) {
			require ABSPATH . 'wp-settings.php';
		} else {
			require WP_CLI_ROOT . '/php/wp-settings-cli.php';
		}

		// Fix memory limit. See http://core.trac.wordpress.org/ticket/14889
		@ini_set( 'memory_limit', -1 );

		// Load all the admin APIs, for convenience
		require ABSPATH . 'wp-admin/includes/admin.php';

		add_filter( 'filesystem_method', function() { return 'direct'; }, 99 );

		WP_CLI::debug( 'Loaded WordPress', 'bootstrap' );
		WP_CLI::do_hook( 'after_wp_load' );

	}

	private static function fake_current_site_blog( $url_parts ) {
		global $current_site, $current_blog;

		if ( !isset( $url_parts['path'] ) ) {
			$url_parts['path'] = '/';
		}

		$current_site = (object) array(
			'id' => 1,
			'blog_id' => 1,
			'domain' => $url_parts['host'],
			'path' => $url_parts['path'],
			'cookie_domain' => $url_parts['host'],
			'site_name' => 'Fake Site',
		);

		$current_blog = (object) array(
			'blog_id' => 1,
			'site_id' => 1,
			'domain' => $url_parts['host'],
			'path' => $url_parts['path'],
			'public' => '1',
			'archived' => '0',
			'mature' => '0',
			'spam' => '0',
			'deleted' => '0',
			'lang_id' => '0',
		);
	}

	/**
	 * Called after wp-config.php is eval'd, to potentially reset `--url`
	 */
	private function maybe_update_url_from_domain_constant() {
		if ( ! empty( $this->config['url'] ) || ! empty( $this->config['blog'] ) ) {
			return;
		}

		if ( defined( 'DOMAIN_CURRENT_SITE' ) ) {
			$url = DOMAIN_CURRENT_SITE;
			if ( defined( 'PATH_CURRENT_SITE' ) ) {
				$url .= PATH_CURRENT_SITE;
			}
			\WP_CLI::set_url( $url );
		}
	}

	/**
	 * Set up hooks meant to run during the WordPress bootstrap process
	 */
	private function setup_bootstrap_hooks() {

		if ( $this->config['skip-plugins'] ) {
			$this->setup_skip_plugins_filters();
		}

		if ( $this->config['skip-themes'] ) {
			WP_CLI::add_wp_hook( 'setup_theme', array( $this, 'action_setup_theme_wp_cli_skip_themes' ), 999 );
		}

		WP_CLI::add_wp_hook( 'wp_die_handler', function() { return '\WP_CLI\Utils\wp_die_handler'; } );

		// Prevent code from performing a redirect
		WP_CLI::add_wp_hook( 'wp_redirect', 'WP_CLI\\Utils\\wp_redirect_handler' );

		WP_CLI::add_wp_hook( 'nocache_headers', function( $headers ){
			// WordPress might be calling nocache_headers() because of a dead db
			global $wpdb;
			if ( ! empty( $wpdb->error ) ) {
				Utils\wp_die_handler( $wpdb->error );
			}
			// Otherwise, WP might be calling nocache_headers() because WP isn't installed
			Utils\wp_not_installed();
			return $headers;
		});

		// ALTERNATE_WP_CRON might trigger a redirect, which we can't handle
		if ( defined( 'ALTERNATE_WP_CRON' ) && ALTERNATE_WP_CRON ) {
			WP_CLI::add_wp_hook( 'muplugins_loaded', function() {
				remove_action( 'init', 'wp_cron' );
			});
		}

		// Get rid of warnings when converting single site to multisite
		if ( defined( 'WP_INSTALLING' ) && $this->is_multisite() ) {
			$values = array(
				'ms_files_rewriting' => null,
				'active_sitewide_plugins' => array(),
				'_site_transient_update_core' => null,
				'_site_transient_update_themes' => null,
				'_site_transient_update_plugins' => null,
				'WPLANG' => '',
			);
			foreach ( $values as $key => $value ) {
				WP_CLI::add_wp_hook( "pre_site_option_$key", function () use ( $values, $key ) {
					return $values[ $key ];
				} );
			}
		}

		// Always permit operations against sites, regardless of status
		WP_CLI::add_wp_hook( 'ms_site_check', '__return_true' );

		// Always permit operations against WordPress, regardless of maintenance mode
		WP_CLI::add_wp_hook( 'enable_maintenance_mode', function() {
			return false;
		});

		// Use our own debug mode handling instead of WP core
		WP_CLI::add_wp_hook( 'enable_wp_debug_mode_checks', function( $ret ) {
			Utils\wp_debug_mode();
			return false;
		});

		// Never load advanced-cache.php drop-in when WP-CLI is operating
		WP_CLI::add_wp_hook( 'enable_loading_advanced_cache_dropin', function() {
			return false;
		});

		// In a multisite install, die if unable to find site given in --url parameter
		if ( $this->is_multisite() ) {
			WP_CLI::add_wp_hook( 'ms_site_not_found', function( $current_site, $domain, $path ) {
				WP_CLI::error( "Site {$domain}{$path} not found." );
			}, 10, 3 );
		}

		// The APC cache is not available on the command-line, so bail, to prevent cache poisoning
		WP_CLI::add_wp_hook( 'muplugins_loaded', function() {
			if ( $GLOBALS['_wp_using_ext_object_cache'] && class_exists( 'APC_Object_Cache' ) ) {
				WP_CLI::warning( 'Running WP-CLI while the APC object cache is activated can result in cache corruption.' );
				WP_CLI::confirm( 'Given the consequences, do you wish to continue?' );
			}
		}, 0 );

		// Handle --user parameter
		if ( ! defined( 'WP_INSTALLING' ) ) {
			$config = $this->config;
			WP_CLI::add_wp_hook( 'init', function() use ( $config ) {
				if ( isset( $config['user'] ) ) {
					$fetcher = new \WP_CLI\Fetchers\User;
					$user = $fetcher->get_check( $config['user']  );
					wp_set_current_user( $user->ID );
				} else {
					add_action( 'init', 'kses_remove_filters', 11 );
				}
			}, 0 );
		}

		// Avoid uncaught exception when using wp_mail() without defined $_SERVER['SERVER_NAME']
		WP_CLI::add_wp_hook( 'wp_mail_from', function( $from_email ){
			if ( 'wordpress@' === $from_email ) {
				$sitename = strtolower( parse_url( site_url(), PHP_URL_HOST ) );
				if ( substr( $sitename, 0, 4 ) == 'www.' ) {
					$sitename = substr( $sitename, 4 );
				}
				$from_email = 'wordpress@' . $sitename;
			}
			return $from_email;
		});

	}

	/**
	 * Set up the filters to skip the loaded plugins
	 */
	private function setup_skip_plugins_filters() {
		$wp_cli_filter_active_plugins = function( $plugins ) {
			$skipped_plugins = WP_CLI::get_runner()->config['skip-plugins'];
			if ( true === $skipped_plugins ) {
				return array();
			}
			if ( ! is_array( $plugins ) ) {
				return $plugins;
			}
			foreach( $plugins as $a => $b ) {
				// active_sitewide_plugins stores plugin name as the key
				if ( false !== strpos( current_filter(), 'active_sitewide_plugins' ) && Utils\is_plugin_skipped( $a ) ) {
					unset( $plugins[ $a ] );
				// active_plugins stores plugin name as the value
				} else if ( false !== strpos( current_filter(), 'active_plugins' ) && Utils\is_plugin_skipped( $b ) ) {
					unset( $plugins[ $a ] );
				}
			}
			// Reindex because active_plugins expects a numeric index
			if ( false !== strpos( current_filter(), 'active_plugins' ) ) {
				$plugins = array_values( $plugins );
			}
			return $plugins;
		};

		$hooks = array(
			'pre_site_option_active_sitewide_plugins',
			'site_option_active_sitewide_plugins',
			'pre_option_active_plugins',
			'option_active_plugins',
		);
		foreach( $hooks as $hook ) {
			WP_CLI::add_wp_hook( $hook, $wp_cli_filter_active_plugins, 999 );
		}
		WP_CLI::add_wp_hook( 'plugins_loaded', function() use ( $hooks, $wp_cli_filter_active_plugins ) {
			foreach( $hooks as $hook ) {
				remove_filter( $hook, $wp_cli_filter_active_plugins, 999 );
			}
		}, 0 );
	}

	/**
	 * Set up the filters to skip the loaded theme
	 */
	public function action_setup_theme_wp_cli_skip_themes() {
		$wp_cli_filter_active_theme = function( $value ) {
			$skipped_themes = WP_CLI::get_runner()->config['skip-themes'];
			if ( true === $skipped_themes ) {
				return '';
			}
			if ( ! is_array( $skipped_themes ) ) {
				$skipped_themes = explode( ',', $skipped_themes );
			}
			// Always check against the stylesheet value
			// This ensures a child theme can be skipped when template differs
			if ( false !== stripos( current_filter(), 'option_template' ) ) {
				$checked_value = get_option( 'stylesheet' );
			} else {
				$checked_value = $value;
			}
			if ( '' === $checked_value || in_array( $checked_value, $skipped_themes ) ) {
				return '';
			}
			return $value;
		};
		$hooks = array(
			'pre_option_template',
			'option_template',
			'pre_option_stylesheet',
			'option_stylesheet',
		);
		foreach( $hooks as $hook ) {
			add_filter( $hook, $wp_cli_filter_active_theme, 999 );
		}
		// Clean up after the TEMPLATEPATH and STYLESHEETPATH constants are defined
		WP_CLI::add_wp_hook( 'after_setup_theme', function() use ( $hooks, $wp_cli_filter_active_theme ) {
			foreach( $hooks as $hook ) {
				remove_filter( $hook, $wp_cli_filter_active_theme, 999 );
			}
		}, 0 );
	}

	/**
	 * Whether or not this WordPress install is multisite.
	 *
	 * For use after wp-config.php has loaded, but before the rest of WordPress
	 * is loaded.
	 */
	private function is_multisite() {
		if ( defined( 'MULTISITE' ) ) {
			return MULTISITE;
		}

		if ( defined( 'SUBDOMAIN_INSTALL' ) || defined( 'VHOST' ) || defined( 'SUNRISE' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Check whether there's a WP-CLI update available, and suggest update if so.
	 */
	private function auto_check_update() {

		// `wp cli update` only works with Phars at this time.
		if ( ! Utils\inside_phar() ) {
			return;
		}

		$existing_phar = realpath( $_SERVER['argv'][0] );
		// Phar needs to be writable to be easily updateable.
		if ( ! is_writable( $existing_phar ) || ! is_writeable( dirname( $existing_phar ) ) ) {
			return;
		}

		// Only check for update when a human is operating.
		if ( ! function_exists( 'posix_isatty' ) || ! posix_isatty( STDOUT ) ) {
			return;
		}

		// Allow hosts and other providers to disable automatic check update.
		if ( getenv( 'WP_CLI_DISABLE_AUTO_CHECK_UPDATE' ) ) {
			return;
		}

		// Permit configuration of number of days between checks.
		$days_between_checks = getenv( 'WP_CLI_AUTO_CHECK_UPDATE_DAYS' );
		if ( false === $days_between_checks ) {
			$days_between_checks = 1;
		}

		$cache = WP_CLI::get_cache();
		$cache_key = 'wp-cli-update-check';
		// Bail early on the first check, so we don't always check on an unwritable cache.
		if ( ! $cache->has( $cache_key ) ) {
			$cache->write( $cache_key, time() );
			return;
		}

		// Bail if last check is still within our update check time period.
		$last_check = (int) $cache->read( $cache_key );
		if ( time() - ( 24 * 60 * 60 * $days_between_checks ) < $last_check ) {
			return;
		}

		// In case the operation fails, ensure the timestamp has been updated.
		$cache->write( $cache_key, time() );

		// Check whether any updates are available.
		ob_start();
		WP_CLI::run_command( array( 'cli', 'check-update' ), array( 'format' => 'count' ) );
		$count = ob_get_clean();
		if ( ! $count ) {
			return;
		}

		// Looks like an update is available, so let's prompt to update.
		WP_CLI::run_command( array( 'cli', 'update' ) );
		// If the Phar was replaced, we can't proceed with the original process.
		exit;
	}

}

