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
			} elseif ( preg_match( '|^--([^=]+)=(.+)|', $arg, $matches ) ) {
				$mixed_args[] = array( $matches[1], $matches[2] );
			} else {
				$regular_args[] = $arg;
			}
		}

		$assoc_args = $runtime_config = array();

		foreach ( $mixed_args as $tmp ) {
			list( $key, $value ) = $tmp;

			$enabled = isset( $this->spec[ $key ] ) ? $this->spec[ $key ]['runtime'] : false;

			if ( false === $enabled ) {
				$assoc_args[ $key ] = $value;
			} else {
				if ( $this->spec[ $key ]['multiple'] ) {
					$runtime_config[ $key ][] = $value;
				} else {
					$runtime_config[ $key ] = $value;
				}
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
			} else {
				 $value = $details['default'];
			}

			$sanitized_config[ $key ] = $value;
		}

		// Make sure a config-relative 'path' is made absolute
		self::absolutize( $sanitized_config['path'], dirname( $yml_file ) );

		return $sanitized_config;
	}

	private static function absolutize( &$path, $base ) {
		if ( !empty( $path ) && !\WP_CLI\Utils\is_path_absolute( $path ) ) {
			$path = $base . DIRECTORY_SEPARATOR . $path;
		}
	}
}

