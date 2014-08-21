<?php

namespace WP_CLI;

/**
 * Output one or more items in a given format (e.g. table, JSON).
 */
class Formatter {

	/**
	 * @var array $args How the items should be output.
	 */
	private $args;

	/**
	 * @var string $prefix Standard prefix for object fields.
	 */
	private $prefix;

	/**
	 * @param array $assoc_args Output format arguments.
	 * @param array $fields Fields to display of each item.
	 * @param string $prefix Check if fields have a standard prefix.
	 */
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

		if ( ! is_array( $format_args['fields'] ) ) {
			$format_args['fields'] = explode( ',', $format_args['fields'] );
		}

		$this->args = $format_args;
		$this->prefix = $prefix;
	}

	/**
	 * Magic getter for arguments.
	 *
	 * @param string $key
	 * @return mixed
	 */
	public function __get( $key ) {
		return $this->args[ $key ];
	}

	/**
	 * Display multiple items according to the output arguments.
	 *
	 * @param array $items
	 */
	public function display_items( $items ) {
		if ( $this->args['field'] ) {
			$this->show_single_field( $items, $this->args['field'] );
		} else {
			if ( in_array( $this->args['format'], array( 'csv', 'json', 'table' ) ) ) {
				$item = is_array( $items ) && ! empty( $items ) ? array_shift( $items ) : false;
				if ( $item && ! empty( $this->args['fields'] ) ) {
					foreach( $this->args['fields'] as &$field ) {
						$field = $this->find_item_key( $item, $field );
					}
					array_unshift( $items, $item );
				}
			}
			$this->format( $items );
		}
	}

	/**
	 * Display a single item according to the output arguments.
	 *
	 * @param mixed $item
	 */
	public function display_item( $item ) {
		if ( isset( $this->args['field'] ) ) {
			$item = (object) $item;
			$key = $this->find_item_key( $item, $this->args['field'] );
			\WP_CLI::print_value( $item->$key, array( 'format' => $this->args['format'] ) );
		} else {
			self::show_multiple_fields( $item, $this->args['format'] );
		}
	}

	/**
	 * Format items according to arguments.
	 *
	 * @param array $items
	 */
	private function format( $items ) {
		$fields = $this->args['fields'];

		switch ( $this->args['format'] ) {
		case 'count':
			if ( !is_array( $items ) ) {
				$items = iterator_to_array( $items );
			}
			echo count( $items );
			break;

		case 'ids':
			if ( !is_array( $items ) ) {
				$items = iterator_to_array( $items );
			}
			echo implode( ' ', $items );
			break;

		case 'table':
			self::show_table( $items, $fields );
			break;

		case 'csv':
			\WP_CLI\Utils\write_csv( STDOUT, $items, $fields );
			break;

		case 'json':
			$out = array();
			foreach ( $items as $item ) {
				$out[] = \WP_CLI\Utils\pick_fields( $item, $fields );
			}

			echo json_encode( $out );
			break;

		default:
			\WP_CLI::error( 'Invalid format: ' . $this->args['format'] );
		}
	}

	/**
	 * Show a single field from a list of items.
	 *
	 * @param array Array of objects to show fields from
	 * @param string The field to show
	 */
	private function show_single_field( $items, $field ) {
		$key = null;
		$values = array();

		foreach ( $items as $item ) {
			$item = (object) $item;

			if ( null === $key ) {
				$key = $this->find_item_key( $item, $field );
			}

			if ( 'json' == $this->args['format'] ) {
				$values[] = $item->$key;
			} else {
				\WP_CLI::print_value( $item->$key, array( 'format' => $this->args['format'] ) );
			}
		}

		if ( 'json' == $this->args['format'] ) {
			echo json_encode( $values );
		}
	}

	/**
	 * Find an object's key.
	 * If $prefix is set, a key with that prefix will be prioritized.
	 *
	 * @param object $item
	 * @param string $field
	 * @return string $key
	 */
	private function find_item_key( $item, $field ) {
		foreach ( array( $field, $this->prefix . '_' . $field ) as $maybe_key ) {
			if ( ( is_object( $item ) && property_exists( $item, $maybe_key ) ) || ( is_array( $item ) && array_key_exists( $maybe_key, $item ) ) ) {
				$key = $maybe_key;
				break;
			}
		}

		if ( ! isset( $key ) ) {
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
	 * Show items in a \cli\Table.
	 *
	 * @param array $items
	 * @param array $fields
	 */
	private static function show_table( $items, $fields ) {
		$table = new \cli\Table();

		$table->setHeaders( $fields );

		foreach ( $items as $item ) {
			$table->addRow( array_values( \WP_CLI\Utils\pick_fields( $item, $fields ) ) );
		}

		$table->display();
	}

	/**
	 * Format an associative array as a table.
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

		self::show_table( $rows, array( 'Field', 'Value' ) );
	}

}
