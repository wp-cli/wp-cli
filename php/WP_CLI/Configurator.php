<?php

namespace WP_CLI;

class Configurator {

	private $spec;

	function __construct( $path ) {
		$this->spec = include $path;

		$defaults = array(
			'runtime' => false,
			'file' => false,
			'synopsis' => '',
			'default' => null,
			'multiple' => false,
		);

		foreach ( $this->spec as &$option ) {
			$option = array_merge( $defaults, $option );
		}
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
	 * Splits a list of arguments into positional, associative and config.
	 *
	 * @param string
	 * @return array
	 */
	function parse_args( $arguments ) {
		$regular_args = $mixed_args = array();

		foreach ( $arguments as $arg ) {
			if ( preg_match( '|^--no-([^=]+)$|', $arg, $matches ) ) {
				$mixed_args[] = array( $matches[1], false );
			} elseif ( preg_match( '|^--([^=]+)$|', $arg, $matches ) ) {
				$mixed_args[] = array( $matches[1], true );
			} elseif ( preg_match( '|^--([^=]+)=(.+)|s', $arg, $matches ) ) {
				$mixed_args[] = array( $matches[1], $matches[2] );
			} else {
				$regular_args[] = $arg;
			}
		}

		$assoc_args = $runtime_config = array();

		foreach ( $mixed_args as $tmp ) {
			list( $key, $value ) = $tmp;

			if ( isset( $this->spec[ $key ] ) ) {
				$details = $this->spec[ $key ];
				if ( $details['runtime'] ) {
					if ( isset( $details['deprecated'] ) ) {
						fwrite( STDERR, "WP-CLI: The --{$key} global parameter is deprecated. {$details['deprecated']}\n" );
					}

					if ( $details['multiple'] ) {
						$runtime_config[ $key ][] = $value;
					} else {
						$runtime_config[ $key ] = $value;
					}
				}
			} else {
				$assoc_args[ $key ] = $value;
			}
		}

		return array( $regular_args, $assoc_args, $runtime_config );
	}

	/**
	 * Load values from a YML file and sanitize them according to the spec.
	 *
	 * @return array
	 */
	function load_config( $yml_file ) {
		if ( $yml_file )
			$config = spyc_load_file( $yml_file );
		else
			$config = array();

		$sanitized_config = array();

		foreach ( $this->spec as $key => $details ) {
			if ( $details['file'] && isset( $config[ $key ] ) ) {
				$value = $config[ $key ];
				if ( $details['multiple'] && !is_array( $value ) ) {
					$value = array( $value );
				}

				$sanitized_config[ $key ] = $value;
			}
		}

		// Make sure config-file-relative paths are made absolute.
		$yml_file_dir = dirname( $yml_file );

		if ( isset( $sanitized_config['path'] ) )
			self::absolutize( $sanitized_config['path'], $yml_file_dir );

		if ( isset( $sanitized_config['require'] ) ) {
			foreach ( $sanitized_config['require'] as &$path ) {
				self::absolutize( $path, $yml_file_dir );
			}
		}

		return $sanitized_config;
	}

	public function get_defaults() {
		$defaults = array();
		foreach ( $this->spec as $key => $details ) {
			$defaults[ $key ] = $details['default'];
		}
		return $defaults;
	}

	private static function absolutize( &$path, $base ) {
		if ( !empty( $path ) && !\WP_CLI\Utils\is_path_absolute( $path ) ) {
			$path = $base . DIRECTORY_SEPARATOR . $path;
		}
	}
}

