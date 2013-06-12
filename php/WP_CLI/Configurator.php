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
	 * Set default value for a particular configuration key.
	 */
	function set_default( $key, $value ) {
		$this->spec[ $key ]['default'] = $value;
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
		if ( ! empty( $sanitized_config['path'] ) && ! \WP_CLI\Utils\is_absolute_path( $sanitized_config['path'] ) ) {
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

