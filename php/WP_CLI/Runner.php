<?php

namespace WP_CLI;

use WP_CLI;
use WP_CLI\Utils;


class Runner {

	private $config_path, $config;

	private $arguments, $assoc_args;

	public function __get( $key ) {
		return $this->$key;
	}

	private static function get_config_path( &$assoc_args ) {
		if ( isset( $assoc_args['config'] ) ) {
			$paths = array( $assoc_args['config'] );
			unset( $assoc_args['config'] );
		} else {
			$paths = array(
				getcwd() . '/wp-cli.local.yml',
				getcwd() . '/wp-cli.yml'
			);
		}

		foreach ( $paths as $path ) {
			if ( file_exists( $path ) )
				return $path;
		}

		return false;
	}

	private static function load_config( $path, $spec ) {
		if ( $path )
			$config = spyc_load_file( $path );
		else
			$config = array();

		$sanitized_config = array();

		foreach ( $spec as $key => $details ) {
			if ( $details['file'] && isset( $config[ $key ] ) )
				$sanitized_config[ $key ] = $config[ $key ];
			else
				$sanitized_config[ $key ] = $details['default'];
		}

		return $sanitized_config;
	}

	private static function handle_boolean_param( &$assoc_args, &$config, $param ) {
		$subkeys = array(
			"$param" => true,
			"no-$param" => false
		);

		foreach ( $subkeys as $key => $value ) {
			if ( isset( $assoc_args[ $key ] ) ) {
				$config[ $param ] = $value;
			}

			unset( $assoc_args[ $key ] );
		}
	}

	private static function split_special( &$assoc_args, &$config, $spec ) {
		foreach ( $spec as $key => $details ) {
			if ( true === $details['runtime'] ) {
				self::handle_boolean_param( $assoc_args, $config, $key );
			} elseif ( false !== $details['runtime'] ) {
				if ( isset( $assoc_args[ $key ] ) ) {
					$config[ $key ] = $assoc_args[ $key ];
					unset( $assoc_args[ $key ] );
				}
			}
		}
	}

	private static function set_wp_root( $config ) {
		if ( !empty( $config['path'] ) ) {
			define( 'ABSPATH', rtrim( $config['path'], '/' ) . '/' );
		} else {
			define( 'ABSPATH', getcwd() . '/' );
		}
	}

	private static function set_url( $assoc_args ) {
		if ( isset( $assoc_args['url'] ) ) {
			$url = $assoc_args['url'];
		} elseif ( isset( $assoc_args['blog'] ) ) {
			WP_CLI::warning( 'The --blog parameter is deprecated. Use --url instead.' );

			$url = $assoc_args['blog'];
			if ( true === $url ) {
				WP_CLI::line( 'usage: wp --blog=example.com' );
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

	/**
	 * Returns wp-config.php code, skipping the loading of wp-settings.php
	 *
	 * @return string
	 */
	function get_wp_config_code() {
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
			if ( preg_match( '/^require.+wp-settings\.php/', $line ) )
				continue;

			$lines_to_run[] = str_replace( $old, $new, $line );
		}

		return preg_replace( '|^\s*\<\?php\s*|', '', implode( "\n", $lines_to_run ) );
	}

	public function before_wp_load() {
		$r = Utils\parse_args( array_slice( $GLOBALS['argv'], 1 ) );

		list( $this->arguments, $this->assoc_args ) = $r;

		// foo --help  ->  help foo
		if ( isset( $this->assoc_args['help'] ) ) {
			array_unshift( $this->arguments, 'help' );
			unset( $this->assoc_args['help'] );
		}

		// {plugin|theme} update --all  ->  {plugin|theme} update-all
		if ( count( $this->arguments ) > 1 && in_array( $this->arguments[0], array( 'plugin', 'theme' ) )
			&& $this->arguments[1] == 'update'
			&& isset( $this->assoc_args['all'] )
		) {
			$this->arguments[1] = 'update-all';
			unset( $this->assoc_args['all'] );
		}

		$config_spec = Utils\get_config_spec();

		$this->config_path = self::get_config_path( $this->assoc_args );

		$this->config = self::load_config( $this->config_path, $config_spec );

		self::split_special( $this->assoc_args, $this->config, $config_spec );

		if ( isset( $this->assoc_args['no-color'] ) ) {
			$this->config['color'] = false;
			unset( $this->assoc_args['no-color'] );
		} elseif ( 'auto' == $this->config['color'] ) {
			$this->config['color'] = ! \cli\Shell::isPiped();
		}

		// Handle --version parameter
		if ( isset( $this->assoc_args['version'] ) && empty( $this->arguments ) ) {
			\WP_CLI\InternalAssoc::version();
			exit;
		}

		// Handle --info parameter
		if ( isset( $this->assoc_args['info'] ) && empty( $this->arguments ) ) {
			\WP_CLI\InternalAssoc::info();
			exit;
		}

		// Handle --cmd-dump parameter
		if ( isset( $this->assoc_args['param-dump'] ) ) {
			\WP_CLI\InternalAssoc::param_dump();
			exit;
		}

		// Handle --cmd-dump parameter
		if ( isset( $this->assoc_args['cmd-dump'] ) ) {
			\WP_CLI\InternalAssoc::cmd_dump();
			exit;
		}

		$_SERVER['DOCUMENT_ROOT'] = getcwd();

		// Handle --path
		self::set_wp_root( $this->config );

		// Handle --url and --blog parameters
		self::set_url( $this->config );

		if ( array( 'core', 'download' ) == $this->arguments ) {
			$this->_run_command();
			exit;
		}

		if ( !is_readable( ABSPATH . 'wp-load.php' ) ) {
			WP_CLI::error( "This does not seem to be a WordPress install.", false );
			WP_CLI::line( "Pass --path=`path/to/wordpress` or run `wp core download`." );
			exit(1);
		}

		if ( array( 'core', 'config' ) == $this->arguments ) {
			$this->_run_command();
			exit;
		}

		if ( !Utils\locate_wp_config() ) {
			WP_CLI::error( "wp-config.php not found.", false );
			WP_CLI::line( "Either create one manually or use `wp core config`." );
			exit(1);
		}

		if ( $this->cmd_starts_with( array( 'db' ) ) ) {
			eval( $this->get_wp_config_code() );
			$this->_run_command();
			exit;
		}

		if (
			$this->cmd_starts_with( array( 'core', 'install' ) ) ||
			$this->cmd_starts_with( array( 'core', 'is-installed' ) )
		) {
			define( 'WP_INSTALLING', true );

			if ( !isset( $_SERVER['HTTP_HOST'] ) ) {
				Utils\set_url_params( 'http://example.com' );
			}
		}

		// Pretend we're in WP_ADMIN
		define( 'WP_ADMIN', true );
		$_SERVER['PHP_SELF'] = '/wp-admin/index.php';
	}

	private function cmd_starts_with( $prefix ) {
		return $prefix == array_slice( $this->arguments, 0, count( $prefix  ) );
	}

	function after_wp_config_load() {
		if ( isset( $this->config['debug'] ) ) {
			if ( !defined( 'WP_DEBUG' ) )
				define( 'WP_DEBUG', true );
		}
	}

	function after_wp_load() {
		add_filter( 'filesystem_method', function() { return 'direct'; }, 99 );

		Utils\set_user( $this->config );

		if ( !defined( 'WP_INSTALLING' ) && isset( $this->config['url'] ) )
			Utils\set_wp_query();

		if ( isset( $this->config['require'] ) )
			require $this->config['require'];

		if ( isset( $this->assoc_args['man'] ) ) {
			\WP_CLI\InternalAssoc::man( $this->arguments );
			exit;
		}

		if ( isset( $this->assoc_args['completions'] ) ) {
			\WP_CLI\InternalAssoc::completions();
			exit;
		}

		$this->_run_command();
	}

	private function _run_command() {
		WP_CLI::run_command( $this->arguments, $this->assoc_args );
	}
}

