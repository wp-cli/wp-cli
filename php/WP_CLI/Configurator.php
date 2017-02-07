<?php

namespace WP_CLI;

/**
 * Handles file- and runtime-based configuration values.
 *
 * @package WP_CLI
 */
class Configurator {

	/**
	 * @var array $spec Configurator argument specification.
	 */
	private $spec;

	/**
	 * @var array $config Values for keys defined in Configurator spec.
	 */
	private $config = array();

	/**
	 * @var array $extra_config Extra config values not specified in spec.
	 */
	private $extra_config = array();

	/**
	 * @var array $aliases Any aliases defined in config files.
	 */
	private $aliases = array();

	/**
	 * @var string ALIAS_REGEX Regex pattern used to define an alias
	 */
	const ALIAS_REGEX = '^@[A-Za-z0-9-_\.\-]+$';

	/**
	 * @var array ALIAS_SPEC Arguments that can be used in an alias
	 */
	private static $alias_spec = array(
		'user',
		'url',
		'path',
		'ssh',
		'http',
	);

	/**
	 * @param string $path Path to config spec file.
	 */
	function __construct( $path ) {
		$this->spec = include $path;

		$defaults = array(
			'runtime' => false,
			'file' => false,
			'synopsis' => '',
			'default' => null,
			'multiple' => false,
		);

		foreach ( $this->spec as $key => &$details ) {
			$details = array_merge( $defaults, $details );

			$this->config[ $key ] = $details['default'];
		}
	}

	/**
	 * Get declared configuration values as an array.
	 *
	 * @return array
	 */
	function to_array() {
		return array( $this->config, $this->extra_config );
	}

	/**
	 * Get configuration specification, i.e. list of accepted keys.
	 *
	 * @return array
	 */
	function get_spec() {
		return $this->spec;
	}

	/**
	 * Get any aliases defined in config files.
	 *
	 * @return array
	 */
	function get_aliases() {
		if ( $runtime_alias = getenv( 'WP_CLI_RUNTIME_ALIAS' ) ) {
			$returned_aliases = array();
			foreach( json_decode( $runtime_alias, true ) as $key => $value ) {
				if ( preg_match( '#' . self::ALIAS_REGEX . '#', $key ) ) {
					$returned_aliases[ $key ] = array();
					foreach( self::$alias_spec as $i ) {
						if ( isset( $value[ $i ] ) ) {
							$returned_aliases[ $key ][ $i ] = $value[ $i ];
						}
					}
				}
			}
			return $returned_aliases;
		} else {
			return $this->aliases;
		}
	}

	/**
	 * Splits a list of arguments into positional, associative and config.
	 *
	 * @param array(string)
	 * @return array(array)
	 */
	public function parse_args( $arguments ) {
		list( $positional_args, $mixed_args, $global_assoc, $local_assoc ) = self::extract_assoc( $arguments );
		list( $assoc_args, $runtime_config ) = $this->unmix_assoc_args( $mixed_args, $global_assoc, $local_assoc );
		return array( $positional_args, $assoc_args, $runtime_config );
	}

	/**
	 * Splits positional args from associative args.
	 *
	 * @param array
	 * @return array(array)
	 */
	public static function extract_assoc( $arguments ) {
		$positional_args = $assoc_args = $global_assoc = $local_assoc = array();

		foreach ( $arguments as $arg ) {
			$positional_arg = $assoc_arg = null;

			if ( preg_match( '|^--no-([^=]+)$|', $arg, $matches ) ) {
				$assoc_arg = array( $matches[1], false );
			} elseif ( preg_match( '|^--([^=]+)$|', $arg, $matches ) ) {
				$assoc_arg = array( $matches[1], true );
			} elseif ( preg_match( '|^--([^=]+)=(.*)|s', $arg, $matches ) ) {
				$assoc_arg = array( $matches[1], $matches[2] );
			} else {
				$positional = $arg;
			}

			if ( ! is_null( $assoc_arg ) ) {
				$assoc_args[] = $assoc_arg;
				if ( count( $positional_args ) ) {
					$local_assoc[] = $assoc_arg;
				} else {
					$global_assoc[] = $assoc_arg;
				}
			} else if ( ! is_null( $positional ) ) {
				$positional_args[] = $positional;
			}

		}

		return array( $positional_args, $assoc_args, $global_assoc, $local_assoc );
	}

	/**
	 * Separate runtime parameters from command-specific parameters.
	 *
	 * @param array $mixed_args
	 * @return array
	 */
	private function unmix_assoc_args( $mixed_args, $global_assoc = array(), $local_assoc = array() ) {
		$assoc_args = $runtime_config = array();

		if ( getenv( 'WP_CLI_STRICT_ARGS_MODE' ) ) {
			foreach( $global_assoc as $tmp ) {
				list( $key, $value ) = $tmp;
				if ( isset( $this->spec[ $key ] ) && $this->spec[ $key ]['runtime'] !== false ) {
					$this->assoc_arg_to_runtime_config( $key, $value, $runtime_config );
				}
			}
			foreach( $local_assoc as $tmp ) {
				$assoc_args[ $tmp[0] ] = $tmp[1];
			}
		} else {
			foreach ( $mixed_args as $tmp ) {
				list( $key, $value ) = $tmp;

				if ( isset( $this->spec[ $key ] ) && $this->spec[ $key ]['runtime'] !== false ) {
					$this->assoc_arg_to_runtime_config( $key, $value, $runtime_config );
				} else {
					$assoc_args[ $key ] = $value;
				}
			}
		}

		return array( $assoc_args, $runtime_config );
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
			$this->merge_yml( $yaml['_']['inherit'], $current_alias );
		}
		foreach ( $yaml as $key => $value ) {
			if ( preg_match( '#' . self::ALIAS_REGEX . '#', $key ) ) {
				$this->aliases[ $key ] = array();
				$is_alias = false;
				foreach( self::$alias_spec as $i ) {
					if ( isset( $value[ $i ] ) ) {
						$this->aliases[ $key ][ $i ] = $value[ $i ];
						$is_alias = true;
					}
				}
				// If it's not an alias, it might be a group of aliases
				if ( ! $is_alias && is_array( $value ) ) {
					$alias_group = array();
					foreach( $value as $i => $k ) {
						if ( preg_match( '#' . self::ALIAS_REGEX . '#', $k ) ) {
							$alias_group[] = $k;
						}
					}
					$this->aliases[ $key ] = $alias_group;
				}
			} elseif ( !isset( $this->spec[ $key ] ) || false === $this->spec[ $key ]['file'] ) {
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
	 * @return array $config Declared configuration values
	 */
	private static function load_yml( $yml_file ) {
		if ( !$yml_file )
			return array();

		$config = spyc_load_file( $yml_file );

		// Make sure config-file-relative paths are made absolute.
		$yml_file_dir = dirname( $yml_file );

		if ( isset( $config['path'] ) )
			self::absolutize( $config['path'], $yml_file_dir );

		if ( isset( $config['require'] ) ) {
			self::arrayify( $config['require'] );
			foreach ( $config['require'] as &$path ) {
				self::absolutize( $path, $yml_file_dir );
			}
		}

		return $config;
	}

	/**
	 * Conform a variable to an array.
	 *
	 * @param mixed $val A string or an array
	 */
	private static function arrayify( &$val ) {
		if ( !is_array( $val ) ) {
			$val = array( $val );
		}
	}

	/**
	 * Make a path absolute.
	 *
	 * @param string $path Path to file.
	 * @param string $base Base path to prepend.
	 */
	private static function absolutize( &$path, $base ) {
		if ( !empty( $path ) && !\WP_CLI\Utils\is_path_absolute( $path ) ) {
			$path = $base . DIRECTORY_SEPARATOR . $path;
		}
	}

}
