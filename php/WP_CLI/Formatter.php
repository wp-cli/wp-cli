<?php

namespace WP_CLI;

class Formatter {

	private $args;
	private $prefix;

	public function __construct( &$assoc_args, $fields = null, $prefix = false ) {
		$format_args = array(
			'format' => 'table',
			'fields' => $fields,
			'field' => null
		);

		foreach ( array( 'format', 'fields', 'field' ) as $key ) {
			if ( isset( $assoc_args[ $key ] ) ) {
				$format_args[ $key ] = $assoc_args[ $key ];
				unset( $assoc_args[ $key ] );
			}
		}

		$this->args = $format_args;
		$this->prefix = $prefix;
	}

	public function __get( $key ) {
		return $this->args[ $key ];
	}

	public function display_items( $items ) {
		if ( $this->args['field'] ) {
			\WP_CLI\Utils\show_single_field( $items, $this->args['field'], $this->args['format'], $this->prefix );
		} elseif ( $this->args['fields'] ) {
			\WP_CLI\Utils\format_items( $this->args['format'], $items, $this->args['fields'] );
		} else {
			trigger_error( 'Both --field= and --fields= parameters are missing.', E_USER_ERROR );
		}
	}

	public function display_item( $item ) {
		if ( isset( $this->args['field'] ) ) {
			\WP_CLI\Utils\show_single_field( array( (object) $item ), $this->args['field'], $this->args['format'], $this->prefix );
		} else {
			\WP_CLI\Utils\show_multiple_fields( $item, $this->args['format'] );
		}
	}
}

