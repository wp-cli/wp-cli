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
			$this->show_single_field( $items, $this->args['field'] );
		} else {
			\WP_CLI\Utils\format_items( $this->args['format'], $items, $this->args['fields'] );
		}
	}

	public function display_item( $item ) {
		if ( isset( $this->args['field'] ) ) {
			$this->show_single_field( array( (object) $item ), $this->args['field'] );
		} else {
			self::show_multiple_fields( $item, $this->args['format'] );
		}
	}

	/**
	 * Show a single field from a list of items.
	 *
	 * @param array Array of objects to show fields from
	 * @param string The field to show
	 */
	private function show_single_field( $items, $field ) {
		foreach ( $items as $item ) {
			if ( ! isset( $key ) ) {
				$key = $this->find_item_key( $item, $field );
			}

			\WP_CLI::print_value( $item->$key, array( 'format' => $this->args['format'] ) );
		}
	}

	private function find_item_key( $item, $field ) {
		foreach ( array( $field, $this->prefix . '_' . $field ) as $maybe_key ) {
			if ( isset( $item->$maybe_key ) ) {
				$key = $maybe_key;
				break;
			}
		}

		if ( ! $key ) {
			\WP_CLI::error( "Invalid field: $field." );
		}

		return $key;
	}

	/**
	 * Show multiple fields of an object.
	 *
	 * @param object|array Data to display
	 * @param string Format to display the data in
	 */
	private static function show_multiple_fields( $data, $format ) {

		switch ( $format ) {

		case 'table':
			self::assoc_array_to_table( $data );
			break;

		case 'json':
			\WP_CLI::print_value( $data, array( 'format' => $format ) );
			break;

		default:
			\WP_CLI::error( "Invalid format: " . $format );
			break;

		}

	}

	/**
	 * Format an associative array as a table
	 *
	 * @param array     $fields    Fields and values to format
	 */
	private static function assoc_array_to_table( $fields ) {
		$rows = array();

		foreach ( $fields as $field => $value ) {
			if ( ! is_string( $value ) ) {
				$value = json_encode( $value );
			}

			$rows[] = (object) array(
				'Field' => $field,
				'Value' => $value
			);
		}

		\WP_CLI\Utils\format_items( 'table', $rows, array( 'Field', 'Value' ) );
	}
}

