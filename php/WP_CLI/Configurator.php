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
	 * Splits a list of arguments into positional and associative.
	 *
	 * @param string
	 * @return array
	 */
	function parse_args( $arguments ) {
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
	 * Load values from a YML file and sanitize them according to the spec.
	 *
	 * @return array
	 */
	function load_config( $path ) {
		if ( $path )
			$config = spyc_load_file( $path );
		else
			$config = array();

		$sanitized_config = array();

		foreach ( $this->spec as $key => $details ) {
			if ( $details['file'] && isset( $config[ $key ] ) )
				$sanitized_config[ $key ] = $config[ $key ];
			else
				$sanitized_config[ $key ] = $details['default'];
		}

		// When invoking from a subdirectory in the project,
		// make sure a config-relative 'path' is made absolute
		if ( ! empty( $sanitized_config['path'] ) && ! \WP_CLI\Utils\is_path_absolute( $sanitized_config['path'] ) ) {
			$sanitized_config['path'] = dirname( $path ) . DIRECTORY_SEPARATOR . $sanitized_config['path'];
		}

		return $sanitized_config;
	}

	/**
	 * Extract values from an associative array, according to the spec.
	 */
	function split_special( &$assoc_args, &$config ) {
		foreach ( $this->spec as $key => $details ) {
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
}

