<?php

namespace WP_CLI;

class Configurator {

	private $spec;
	private $config = array();

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

	function to_array() {
		return $this->config;
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

			if ( isset( $this->spec[ $key ] ) && $this->spec[ $key ]['runtime'] !== false ) {
				$details = $this->spec[ $key ];

				if ( isset( $details['deprecated'] ) ) {
					fwrite( STDERR, "WP-CLI: The --{$key} global parameter is deprecated. {$details['deprecated']}\n" );
				}

				if ( $details['multiple'] ) {
					$runtime_config[ $key ][] = $value;
				} else {
					$runtime_config[ $key ] = $value;
				}
			} else {
				$assoc_args[ $key ] = $value;
			}
		}

		return array( $regular_args, $assoc_args, $runtime_config );
	}

	function merge_config( $config, $type ) {
		foreach ( $this->spec as $key => $details ) {
			if ( false !== $details[ $type ] && isset( $config[ $key ] ) ) {
				$value = $config[ $key ];

				if ( $details['multiple'] ) {
					if ( !is_array( $value ) ) {
						$value = array( $value );
					}
					$this->config[ $key ] = array_merge( $this->config[ $key ], $value );
				} else {
					$this->config[ $key ] = $value;
				}
			}
		}
	}
}

