<?php

namespace WP_CLI;

use Mustangostang\Spyc;
use SplFileInfo;

use function WP_CLI\Utils\is_path_absolute;
use function WP_CLI\Utils\normalize_path;

/**
 * Handles file- and runtime-based configuration values.
 *
 * @package WP_CLI
 */
class Configurator {

	/**
	 * Configurator argument specification.
	 *
	 * @var array
	 */
	private $spec;

	/**
	 * Values for keys defined in Configurator spec.
	 *
	 * @var array
	 */
	private $config = [];

	/**
	 * Extra config values not specified in spec.
	 *
	 * @var array
	 */
	private $extra_config = [];

	/**
	 * Any aliases defined in config files.
	 *
	 * @var array
	 */
	private $aliases = [];

	/**
	 * Regex pattern used to define an alias.
	 *
	 * @var string
	 */
	const ALIAS_REGEX = '^@[A-Za-z0-9-_\.\-]+$';

	/**
	 * Arguments that can be used in an alias.
	 *
	 * @var array
	 */
	private static $alias_spec = [
		'user',
		'url',
		'path',
		'ssh',
		'http',
		'proxyjump',
		'key',
	];

	/**
	 * @param string $path Path to config spec file.
	 */
	public function __construct( $path ) {
		$this->load_config_spec( $path );

		$defaults = [
			'runtime'  => false,
			'file'     => false,
			'synopsis' => '',
			'default'  => null,
			'multiple' => false,
		];

		foreach ( $this->spec as $key => &$details ) {
			$details = array_merge( $defaults, $details );

			$this->config[ $key ] = $details['default'];
		}

		$env_files = getenv( 'WP_CLI_REQUIRE' )
		? array_filter( array_map( 'trim', explode( ',', (string) getenv( 'WP_CLI_REQUIRE' ) ) ) )
		: [];

		if ( ! empty( $env_files ) ) {
			if ( ! isset( $this->config['require'] ) ) {
				$this->config['require'] = [];
			}
			$this->config['require'] = array_unique( array_merge( $env_files, $this->config['require'] ) );
		}
	}

	/**
	 * Loads the config spec file.
	 *
	 * @param string $path Path to the config spec file.
	 */
	private function load_config_spec( $path ) {
		$config_spec = include $path;
		// A way for platforms to modify $config_spec.
		// Use with caution!
		$config_spec_filter_callback = defined( 'WP_CLI_CONFIG_SPEC_FILTER_CALLBACK' ) ? constant( 'WP_CLI_CONFIG_SPEC_FILTER_CALLBACK' ) : false;
		if ( $config_spec_filter_callback && is_callable( $config_spec_filter_callback ) ) {
			$config_spec = $config_spec_filter_callback( $config_spec );
		}
		$this->spec = $config_spec;
	}

	/**
	 * Get declared configuration values as an array.
	 *
	 * @return array
	 */
	public function to_array() {
		return [ $this->config, $this->extra_config ];
	}

	/**
	 * Get configuration specification, i.e. list of accepted keys.
	 *
	 * @return array
	 */
	public function get_spec() {
		return $this->spec;
	}

	/**
	 * Get any aliases defined in config files.
	 *
	 * @return array
	 */
	public function get_aliases() {
		$runtime_alias = getenv( 'WP_CLI_RUNTIME_ALIAS' );
		if ( false !== $runtime_alias ) {
			$returned_aliases = [];

			/**
			 * @var string $key
			 * @var array<string, string> $value
			 */
			foreach ( (array) json_decode( $runtime_alias, true ) as $key => $value ) {
				if ( preg_match( '#' . self::ALIAS_REGEX . '#', $key ) ) {
					$returned_aliases[ $key ] = [];
					foreach ( self::$alias_spec as $i ) {
						if ( isset( $value[ $i ] ) ) {
							$returned_aliases[ $key ][ $i ] = $value[ $i ];
						}
					}
				}
			}
			return $returned_aliases;
		}

		return $this->aliases;
	}

	/**
	 * Splits a list of arguments into positional, associative and config.
	 *
	 * @param array<string> $arguments
	 * @return array<array<string>>
	 */
	public function parse_args( $arguments ) {
		list( $positional_args, $mixed_args, $global_assoc, $local_assoc ) = self::extract_assoc( $arguments );
		list( $assoc_args, $runtime_config )                               = $this->unmix_assoc_args( $mixed_args, $global_assoc, $local_assoc );
		return [ $positional_args, $assoc_args, $runtime_config ];
	}

	/**
	 * Splits positional args from associative args.
	 *
	 * @param array<string> $arguments
	 * @return array{0: array<string>, 1: array<array{0: string, 1: string|bool}>, 2: array<array{0: string, 1: string|bool}>, 3: array<array{0: string, 1: string|bool}>}
	 */
	public static function extract_assoc( $arguments ) {
		$positional_args = [];
		$assoc_args      = [];
		$global_assoc    = [];
		$local_assoc     = [];
		$end_of_options  = false;

		$delimiter_index = array_search( '--', $arguments, true );

		foreach ( $arguments as $i => $arg ) {
			$positional = null;
			$assoc_arg  = null;

			if ( ! $end_of_options && '--' === $arg ) {
				$end_of_options = true;
				continue;
			}

			if ( $end_of_options ) {
				$positional = $arg;
			} elseif ( preg_match( '|^--no-([^=]+)$|', $arg, $matches ) ) {
				$assoc_arg = [ $matches[1], false ];
			} elseif ( preg_match( '|^--([^=]+)$|', $arg, $matches ) ) {
				$assoc_arg = [ $matches[1], true ];
			} elseif ( preg_match( '|^--([^=]+)=(.*)|s', $arg, $matches ) ) {
				$assoc_arg = [ $matches[1], $matches[2] ];
			} else {
				$positional = $arg;
			}

			if ( ! is_null( $assoc_arg ) ) {
				$assoc_args[] = $assoc_arg;
				if ( false !== $delimiter_index ) {
					if ( $i < $delimiter_index ) {
						$global_assoc[] = $assoc_arg;
					}
				} else {
					if ( count( $positional_args ) ) {
						$local_assoc[] = $assoc_arg;
					} else {
						$global_assoc[] = $assoc_arg;
					}
				}
			} elseif ( ! is_null( $positional ) ) {
				$positional_args[] = $positional;
			}
		}

		return [ $positional_args, $assoc_args, $global_assoc, $local_assoc ];
	}

	/**
	 * Separate runtime parameters from command-specific parameters.
	 *
	 * @param array $mixed_args
	 * @return array
	 */
	private function unmix_assoc_args( $mixed_args, $global_assoc = [], $local_assoc = [] ) {
		$assoc_args     = [];
		$runtime_config = [];

		if ( getenv( 'WP_CLI_STRICT_ARGS_MODE' ) ) {
			foreach ( $global_assoc as $tmp ) {
				list( $key, $value ) = $tmp;
				if ( isset( $this->spec[ $key ] ) && false !== $this->spec[ $key ]['runtime'] ) {
					$this->assoc_arg_to_runtime_config( $key, $value, $runtime_config );
				}
			}
			foreach ( $local_assoc as $tmp ) {
				$assoc_args[ $tmp[0] ] = $tmp[1];
			}
		} else {
			foreach ( $mixed_args as $tmp ) {
				list( $key, $value ) = $tmp;

				if ( isset( $this->spec[ $key ] ) && false !== $this->spec[ $key ]['runtime'] ) {
					$this->assoc_arg_to_runtime_config( $key, $value, $runtime_config );
				} else {
					$assoc_args[ $key ] = $value;
				}
			}
		}

		return [ $assoc_args, $runtime_config ];
	}

	/**
	 * Handle turning an $assoc_arg into a runtime arg.
	 */
	private function assoc_arg_to_runtime_config( $key, $value, &$runtime_config ) {
		$details = $this->spec[ $key ];
		if ( isset( $details['deprecated'] ) ) {
			fwrite( STDERR, "WP-CLI: The --{$key} global parameter is deprecated. {$details['deprecated']}\n" );
		}

		if ( $details['multiple'] ) {
			$runtime_config[ $key ][] = $value;
		} else {
			$runtime_config[ $key ] = $value;
		}
	}

	/**
	 * Load a YAML file of parameters into scope.
	 *
	 * @param string $path Path to YAML file.
	 */
	public function merge_yml( $path, $current_alias = null ) {
		$yaml = self::load_yml( $path );
		if ( ! empty( $yaml['_']['inherit'] ) ) {
			// Refactor with the WP-CLI `Path` class, once it's available.
			// See: https://github.com/wp-cli/wp-cli/issues/5007
			$inherit_path = is_path_absolute( $yaml['_']['inherit'] )
				? $yaml['_']['inherit']
				: ( new SplFileInfo( normalize_path( dirname( $path ) . '/' . $yaml['_']['inherit'] ) ) )->getRealPath();

			$this->merge_yml( $inherit_path, $current_alias );
		}
		// Prepare the base path for absolutized alias paths.
		$yml_file_dir = $path ? dirname( $path ) : '';
		foreach ( $yaml as $key => $value ) {
			if ( preg_match( '#' . self::ALIAS_REGEX . '#', $key ) ) {
				$this->aliases[ $key ] = [];
				$is_alias              = false;
				foreach ( self::$alias_spec as $i ) {
					if ( isset( $value[ $i ] ) ) {
						if ( 'path' === $i && ! isset( $value['ssh'] ) ) {
							self::absolutize( $value[ $i ], $yml_file_dir );
						}
						$this->aliases[ $key ][ $i ] = $value[ $i ];
						$is_alias                    = true;
					}
				}
				// If it's not an alias, it might be a group of aliases.
				if ( ! $is_alias && is_array( $value ) ) {
					$alias_group = [];
					foreach ( $value as $k ) {
						if ( preg_match( '#' . self::ALIAS_REGEX . '#', $k ) ) {
							$alias_group[] = $k;
						}
					}
					$this->aliases[ $key ] = $alias_group;
				}
			} elseif ( ! isset( $this->spec[ $key ] ) || false === $this->spec[ $key ]['file'] ) {
				if ( isset( $this->extra_config[ $key ] )
					&& ! empty( $yaml['_']['merge'] )
					&& is_array( $this->extra_config[ $key ] )
					&& is_array( $value ) ) {
					$this->extra_config[ $key ] = array_merge( $this->extra_config[ $key ], $value );
				} else {
					$this->extra_config[ $key ] = $value;
				}
			} elseif ( $this->spec[ $key ]['multiple'] ) {
				self::arrayify( $value );
				$this->config[ $key ] = array_merge( $this->config[ $key ], $value );
			} else {
				if ( $current_alias && in_array( $key, self::$alias_spec, true ) ) {
					continue;
				}
				$this->config[ $key ] = $value;
			}
		}
	}

	/**
	 * Merge an array of values into the configurator config.
	 *
	 * @param array $config
	 */
	public function merge_array( $config ) {
		foreach ( $this->spec as $key => $details ) {
			if ( false !== $details['runtime'] && isset( $config[ $key ] ) ) {
				$value = $config[ $key ];

				if ( 'require' === $key ) {
					$value = Utils\expand_globs( $value );
				}

				if ( $details['multiple'] ) {
					self::arrayify( $value );
					$this->config[ $key ] = array_merge( $this->config[ $key ], $value );
				} else {
					$this->config[ $key ] = $value;
				}
			}
		}
	}

	/**
	 * Load values from a YAML file.
	 *
	 * @param string $yml_file Path to the YAML file
	 * @return array Declared configuration values
	 */
	private static function load_yml( $yml_file ) {
		if ( ! $yml_file ) {
			return [];
		}

		$config = Spyc::YAMLLoad( $yml_file );

		// Make sure config-file-relative paths are made absolute.
		$yml_file_dir = dirname( $yml_file );

		if ( isset( $config['path'] ) ) {
			self::absolutize( $config['path'], $yml_file_dir );
		}

		if ( isset( $config['require'] ) ) {
			self::arrayify( $config['require'] );
			$config['require'] = Utils\expand_globs( $config['require'] );
			foreach ( $config['require'] as &$path ) {
				self::absolutize( $path, $yml_file_dir );
			}
		}

		// Backwards compat
		// Command 'core config' was moved to 'config create'.
		if ( isset( $config['core config'] ) ) {
			$config['config create'] = $config['core config'];
			unset( $config['core config'] );
		}
		// Command 'checksum core' was moved to 'core verify-checksums'.
		if ( isset( $config['checksum core'] ) ) {
			$config['core verify-checksums'] = $config['checksum core'];
			unset( $config['checksum core'] );
		}
		// Command 'checksum plugin' was moved to 'plugin verify-checksums'.
		if ( isset( $config['checksum plugin'] ) ) {
			$config['plugin verify-checksums'] = $config['checksum plugin'];
			unset( $config['checksum plugin'] );
		}

		return $config;
	}

	/**
	 * Conform a variable to an array.
	 *
	 * @param mixed $val A string or an array
	 */
	private static function arrayify( &$val ) {
		$val = (array) $val;
	}

	/**
	 * Make a path absolute.
	 *
	 * @param string $path Path to file.
	 * @param string $base Base path to prepend.
	 */
	private static function absolutize( &$path, $base ) {
		if ( ! empty( $path ) && ! Utils\is_path_absolute( $path ) ) {
			$path = $base . DIRECTORY_SEPARATOR . $path;
		}
	}
}
