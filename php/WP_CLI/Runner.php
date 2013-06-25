<?php

namespace WP_CLI;

use WP_CLI;
use WP_CLI\Utils;


class Runner {

	private $config_path, $config;

	private $arguments, $assoc_args;

	private $_early_invoke = array();

	public function __get( $key ) {
		if ( '_' === $key[0] )
			return null;

		return $this->$key;
	}

	public function register_early_invoke( $when, $command ) {
		$this->_early_invoke[ $when ][] = array_slice( Dispatcher\get_path( $command ), 1 );
	}

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

	private static function get_config_path( $runtime_config ) {
		if ( isset( $runtime_config['config'] ) && file_exists( $runtime_config['config'] ) ) {
			return $runtime_config['config'];
		}

		$config_files = array(
			'wp-cli.local.yml',
			'wp-cli.yml'
		);

		// Stop looking upward when we find we have emerged from a subdirectory
		// install into a parent install
		$path = Utils\find_file_upward( $config_files, getcwd(), function ( $dir ) {
			static $wp_load_count = 0;
			$wp_load_path = $dir . DIRECTORY_SEPARATOR . 'wp-load.php';
			if ( file_exists( $wp_load_path ) ) {
				$wp_load_count += 1;
			}
			return $wp_load_count > 1;
		} );

		if ( $path ) {
			return $path;
		}

		return false;
	}

	private static function set_wp_root( $config ) {
		$path = getcwd();

		if ( !empty( $config['path'] ) ) {
			if ( Utils\is_path_absolute( $config['path'] ) )
				$path = $config['path'];
			else
				$path .= '/' . $config['path'];
		}

		define( 'ABSPATH', rtrim( $path, '/' ) . '/' );

		$_SERVER['DOCUMENT_ROOT'] = realpath( $path );
	}

	private static function set_user( $assoc_args ) {
		if ( !isset( $assoc_args['user'] ) )
			return;

		$user = $assoc_args['user'];

		if ( is_numeric( $user ) ) {
			$user_id = (int) $user;
		} else {
			$user_id = (int) username_exists( $user );
		}

		if ( !$user_id || !wp_set_current_user( $user_id ) ) {
			\WP_CLI::error( sprintf( 'Could not get a user_id for this user: %s', var_export( $user, true ) ) );
		}
	}

	private static function set_url( $assoc_args ) {
		if ( isset( $assoc_args['blog'] ) ) {
			$assoc_args['url'] = $assoc_args['blog'];
			unset( $assoc_args['blog'] );
			WP_CLI::warning( 'The --blog parameter is deprecated. Use --url instead.' );
		}

		if ( isset( $assoc_args['url'] ) ) {
			$url = $assoc_args['url'];
			if ( true === $url ) {
				WP_CLI::warning( 'The --url parameter expects a value.' );
			}
		} elseif ( is_readable( ABSPATH . 'wp-cli-blog' ) ) {
			WP_CLI::warning( 'The wp-cli-blog file is deprecated. Use wp-cli.yml instead.' );

			$url = trim( file_get_contents( ABSPATH . 'wp-cli-blog' ) );
		} elseif ( $wp_config_path = Utils\locate_wp_config() ) {
			// Try to find the blog parameter in the wp-config file
			$wp_config_file = file_get_contents( $wp_config_path );
			$hit = array();

			$re_define = "#.*define\s*\(\s*(['|\"]{1})(.+)(['|\"]{1})\s*,\s*(['|\"]{1})(.+)(['|\"]{1})\s*\)\s*;#iU";

			if ( preg_match_all( $re_define, $wp_config_file, $matches ) ) {
				foreach ( $matches[2] as $def_key => $def_name ) {
					if ( 'DOMAIN_CURRENT_SITE' == $def_name )
						$hit['domain'] = $matches[5][$def_key];
					if ( 'PATH_CURRENT_SITE' == $def_name )
						$hit['path'] = $matches[5][$def_key];
				}
			}

			if ( !empty( $hit ) && isset( $hit['domain'] ) )
				$url = $hit['domain'];
			if ( !empty( $hit ) && isset( $hit['path'] ) )
				$url .= $hit['path'];
		}

		if ( isset( $url ) ) {
			Utils\set_url_params( $url );
		}
	}

	private function cmd_starts_with( $prefix ) {
		return $prefix == array_slice( $this->arguments, 0, count( $prefix ) );
	}

	private function _run_command() {
		WP_CLI::run_command( $this->arguments, $this->assoc_args );
	}

	/**
	 * Returns wp-config.php code, skipping the loading of wp-settings.php
	 *
	 * @return string
	 */
	public function get_wp_config_code() {
		$wp_config_path = Utils\locate_wp_config();

		$replacements = array(
			'__FILE__' => "'$wp_config_path'",
			'__DIR__'  => "'" . dirname( $wp_config_path ) . "'"
		);

		$old = array_keys( $replacements );
		$new = array_values( $replacements );

		$wp_config_code = explode( "\n", file_get_contents( $wp_config_path ) );

		$lines_to_run = array();

		foreach ( $wp_config_code as $line ) {
			if ( preg_match( '/^\s*require.+wp-settings\.php/', $line ) )
				continue;

			$lines_to_run[] = str_replace( $old, $new, $line );
		}

		return preg_replace( '|^\s*\<\?php\s*|', '', implode( "\n", $lines_to_run ) );
	}

	// Transparently convert old syntaxes
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

		// site --site_id=  ->  site --network_id=
		if ( count( $args ) > 0 && 'site' == $args[0] && isset( $assoc_args['site_id'] ) ) {
			$assoc_args['network_id'] = $assoc_args['site_id'];
			unset( $assoc_args['site_id'] );
		}

		// {plugin|theme} update --all  ->  {plugin|theme} update-all
		if ( count( $args ) > 1 && in_array( $args[0], array( 'plugin', 'theme' ) )
			&& $args[1] == 'update' && isset( $assoc_args['all'] )
		) {
			$args[1] = 'update-all';
			unset( $assoc_args['all'] );
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

		// --{version|info|completions}  ->  cli {version|info|completions}
		if ( empty( $args ) ) {
			$special_flags = array( 'version', 'info', 'completions' );
			foreach ( $special_flags as $key ) {
				if ( isset( $assoc_args[ $key ] ) ) {
					$args = array( 'cli', $key );
					break;
				}
			}
		}

		return array( $args, $assoc_args );
	}

	public function in_color() {
		return $this->colorize;
	}

	private function init_colorization() {
		if ( 'auto' === $this->config['color'] ) {
			$this->colorize = !\cli\Shell::isPiped();
		} else {
			$this->colorize = $this->config['color'];
		}
	}

	private function init_logger() {
		if ( $this->config['quiet'] )
			$logger = new \WP_CLI\Loggers\Quiet;
		else
			$logger = new \WP_CLI\Loggers\Regular;

		WP_CLI::set_logger( $logger );
	}

	public function before_wp_load() {
		list( $args, $assoc_args, $runtime_config ) = \WP_CLI::$configurator->parse_args(
			array_slice( $GLOBALS['argv'], 1 ) );

		list( $this->arguments, $this->assoc_args ) = self::back_compat_conversions(
			$args, $assoc_args );

		$this->config_path = self::get_config_path( $runtime_config );

		$local_config = \WP_CLI::$configurator->load_config( $this->config_path );

		$this->config = $local_config;

		foreach ( $runtime_config as $key => $value ) {
			if ( isset( $this->config[ $key ] ) && is_array( $this->config[ $key ] ) ) {
				$this->config[ $key ] = array_merge( $this->config[ $key ], $value );
			} else {
				$this->config[ $key ] = $value;
			}
		}

		if ( !isset( $this->config['path'] ) ) {
			$this->config['path'] = dirname( Utils\find_file_upward( 'wp-load.php' ) );
		}

		$this->init_colorization();
		$this->init_logger();

		if ( empty( $this->arguments ) )
			$this->arguments[] = 'help';

		Utils\load_command( $this->arguments[0] );

		if ( isset( $this->config['require'] ) ) {
			foreach ( $this->config['require'] as $path ) {
				require $path;
			}
		}

		// First try at showing man page
		if ( $this->cmd_starts_with( array( 'help' ) ) ) {
			$this->_run_command();
		}

		// Handle --path parameter
		self::set_wp_root( $this->config );

		// Handle --url and --blog parameters
		self::set_url( $this->config );

		$this->do_early_invoke( 'before_wp_load' );

		if ( !is_readable( ABSPATH . 'wp-load.php' ) ) {
			WP_CLI::error(
				"This does not seem to be a WordPress install.\n" .
				"Pass --path=`path/to/wordpress` or run `wp core download`." );
		}

		if ( array( 'core', 'config' ) == $this->arguments ) {
			$this->_run_command();
			exit;
		}

		if ( !Utils\locate_wp_config() ) {
			WP_CLI::error(
				"wp-config.php not found.\n" .
				"Either create one manually or use `wp core config`." );
		}

		if ( $this->cmd_starts_with( array( 'db' ) ) ) {
			eval( $this->get_wp_config_code() );
			$this->_run_command();
			exit;
		}

		if (
			$this->cmd_starts_with( array( 'core', 'install' ) )
		) {
			define( 'WP_INSTALLING', true );

			if ( !isset( $_SERVER['HTTP_HOST'] ) ) {
				Utils\set_url_params( 'http://example.com' );
			}
		}

		if ( $this->cmd_starts_with( array( 'import') ) ) {
			define( 'WP_LOAD_IMPORTERS', true );
			define( 'WP_IMPORTING', true );
		}

	}

	public function after_wp_load() {
		add_filter( 'filesystem_method', function() { return 'direct'; }, 99 );

		// Handle --user parameter
		self::set_user( $this->config );

		$this->_run_command();
	}
}
