<?php

namespace WP_CLI;

use cli\Colors;
use cli\Table;
use Iterator;
use Mustangostang\Spyc;
use WP_CLI;

/**
 * Output one or more items in a given format (e.g. table, JSON).
 *
 * Supports built-in formats (table, json, csv, yaml, count, ids) and allows
 * extensions to register custom formats via Formatter::add_format().
 *
 * @property-read string             $format
 * @property-read string[]           $fields
 * @property-read string|null        $field
 * @property-read array<string, int> $alignments
 */
class Formatter {

	/**
	 * Maximum width for a table cell value.
	 * Values longer than this will be truncated to improve performance.
	 *
	 * @var int
	 */
	const MAX_CELL_WIDTH = 2048;

	/**
	 * Custom format handlers registered by extensions.
	 *
	 * @var array<string, callable>
	 */
	private static $custom_formatters = [];

	/**
	 * How the items should be output.
	 *
	 * @var array{format: string, fields: string[], field: string|null, alignments: array<string, int>}
	 */
	private $args;

	/**
	 * Standard prefix for object fields.
	 *
	 * @var string|false
	 */
	private $prefix;

	/**
	 * @param array $assoc_args Output format arguments.
	 * @param array $fields Fields to display of each item.
	 * @param string|false $prefix Check if fields have a standard prefix.
	 * False indicates empty prefix.
	 */
	public function __construct( &$assoc_args, $fields = null, $prefix = false ) {
		$format_args = [
			'format'     => 'table',
			'fields'     => $fields,
			'field'      => null,
			'alignments' => [],
		];

		foreach ( array_keys( $format_args ) as $key ) {
			if ( isset( $assoc_args[ $key ] ) ) {
				$format_args[ $key ] = $assoc_args[ $key ];
				unset( $assoc_args[ $key ] );
			}
		}

		if ( ! is_array( $format_args['fields'] ) ) {
			$format_args['fields'] = explode( ',', $format_args['fields'] );
		}

		/** @var callable(string): string $trim */
		$trim = 'trim';
		// @phpstan-ignore argument.type
		$format_args['fields'] = array_map( $trim, $format_args['fields'] );

		$this->args   = $format_args;
		$this->prefix = $prefix;
	}

	/**
	 * Register a custom format handler.
	 *
	 * Allows extensions to add custom output formats. The handler receives an array
	 * of items (each item is an array of field => value pairs) and an array of field
	 * names, and should output the formatted data directly.
	 *
	 * Built-in formats can be overridden by registering a handler with the same name.
	 *
	 * ## EXAMPLE
	 *
	 *     // Register a custom XML format
	 *     WP_CLI\Formatter::add_format( 'xml', function( $items, $fields ) {
	 *         echo "<?xml version=\"1.0\"?>\n<items>\n";
	 *         foreach ( $items as $item ) {
	 *             echo "  <item>\n";
	 *             foreach ( $item as $key => $value ) {
	 *                 echo "    <{$key}>" . htmlspecialchars( $value ) . "</{$key}>\n";
	 *             }
	 *             echo "  </item>\n";
	 *         }
	 *         echo "</items>\n";
	 *     });
	 *
	 * @param string   $format_name Name of the format (e.g. 'xml', 'nagios').
	 * @param callable $handler     Callback to handle formatting. Receives ($items, $fields) and should output directly.
	 */
	public static function add_format( $format_name, $handler ) {
		if ( ! is_callable( $handler ) ) {
			WP_CLI::error( 'Format handler must be callable.' );
		}
		self::$custom_formatters[ $format_name ] = $handler;
	}

	/**
	 * Register built-in format handlers.
	 *
	 * This method registers the default format handlers (table, json, csv, yaml, count, ids)
	 * using the add_format() API, allowing them to be overridden like custom formats.
	 */
	public static function register_builtin_formats() {
		// Register 'count' format
		self::add_format(
			'count',
			static function ( $items, $fields ) {
				echo count( $items );
			}
		);

		// Register 'ids' format
		self::add_format(
			'ids',
			static function ( $items, $fields ) {
				echo implode( ' ', $items );
			}
		);

		// Register 'json' format
		self::add_format(
			'json',
			static function ( $items, $fields ) {
				if ( defined( 'JSON_PARTIAL_OUTPUT_ON_ERROR' ) ) {
					// phpcs:ignore PHPCompatibility.Constants.NewConstants.json_partial_output_on_errorFound
					echo json_encode( $items, JSON_PARTIAL_OUTPUT_ON_ERROR );
				} else {
					echo json_encode( $items );
				}
			}
		);

		// Register 'yaml' format
		self::add_format(
			'yaml',
			static function ( $items, $fields ) {
				echo Spyc::YAMLDump( $items, 2, 0 );
			}
		);

		// Register 'csv' format
		self::add_format(
			'csv',
			static function ( $items, $fields ) {
				Utils\write_csv( STDOUT, $items, $fields );
			}
		);

		// Register 'table' format
		self::add_format(
			'table',
			static function ( $items, $fields, $formatter = null, $ascii_pre_colorized = false ) {
				if ( $formatter instanceof Formatter ) {
					$formatter->show_table( $items, $fields, $ascii_pre_colorized );
				} else {
					// Fallback if no formatter instance provided
					$table = new Table();
					$table->setHeaders( $fields );
					foreach ( $items as $item ) {
						$table->addRow( array_values( Utils\pick_fields( $item, $fields ) ) );
					}
					foreach ( $table->getDisplayLines() as $line ) {
						WP_CLI::line( $line );
					}
				}
			}
		);
	}

	/**
	 * Get list of all available format names.
	 *
	 * Returns built-in formats plus any custom formats that have been registered.
	 * The list can be filtered via the 'formatter_available_formats' hook.
	 *
	 * ## EXAMPLE
	 *
	 *     // Get all available formats
	 *     $formats = WP_CLI\Formatter::get_available_formats();
	 *     // Returns: [ 'table', 'json', 'csv', 'yaml', 'count', 'ids', ... custom formats ]
	 *
	 *     // Filter to add a format to the list
	 *     WP_CLI::add_hook( 'formatter_available_formats', function( $formats ) {
	 *         $formats[] = 'my_custom_format';
	 *         return $formats;
	 *     });
	 *
	 * @return string[] Array of format names.
	 */
	public static function get_available_formats() {
		$all_formats = array_keys( self::$custom_formatters );

		/**
		 * Filter the list of available output formats.
		 *
		 * @param string[] $formats Array of format names.
		 */
		// @phpstan-ignore-next-line - We trust the hook to return the correct type
		return WP_CLI::do_hook( 'formatter_available_formats', $all_formats );
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
	 * @param iterable   $items               The items to display.
	 * @param bool|array $ascii_pre_colorized Optional. A boolean or an array of booleans to pass to `format()` if items in the table are pre-colorized. Default false.
	 */
	public function display_items( $items, $ascii_pre_colorized = false ) {
		if ( $this->args['field'] ) {
			$this->show_single_field( $items, $this->args['field'] );
		} else {
			// Convert iterator to array early to avoid consumption issues and enable validation
			if ( $items instanceof Iterator ) {
				$items = iterator_to_array( $items );
			}

			// Check if this is a custom formatter or a built-in format that needs field validation
			$is_custom_format       = isset( self::$custom_formatters[ $this->args['format'] ] );
			$needs_field_validation = in_array( $this->args['format'], [ 'csv', 'json', 'table', 'yaml' ], true ) || $is_custom_format;

			if ( $needs_field_validation ) {
				// Validate fields exist in at least one item and resolve field names with prefix support
				if ( ! empty( $this->args['fields'] ) ) {
					$this->validate_fields( $items );
				}
			}

			if ( in_array( $this->args['format'], [ 'table', 'csv' ], true ) ) {
				$items = array_map( [ $this, 'transform_item_values_to_json' ], (array) $items );
			}

			$this->format( $items, $ascii_pre_colorized );
		}
	}

	/**
	 * Display a single item according to the output arguments.
	 *
	 * @param mixed      $item
	 * @param bool|array $ascii_pre_colorized Optional. A boolean or an array of booleans to pass to `show_multiple_fields()` if the item in the table is pre-colorized. Default false.
	 */
	public function display_item( $item, $ascii_pre_colorized = false ) {
		if ( isset( $this->args['field'] ) ) {
			$item = (object) $item;
			$key  = $this->find_item_key( $item, $this->args['field'], true );
			if ( null === $key ) {
				WP_CLI::warning( "Field not found in item: {$this->args['field']}." );
				$value = null;
			} else {
				$value = $item->$key;
			}
			if ( in_array( $this->args['format'], [ 'table', 'csv' ], true ) && ( is_object( $value ) || is_array( $value ) ) ) {
				$value = json_encode( $value );
			}
			WP_CLI::print_value(
				$value,
				[
					'format' => $this->args['format'],
				]
			);
		} else {
			/**
			 * @var array $item
			 */
			$this->show_multiple_fields( $item, $this->args['format'], $ascii_pre_colorized );
		}
	}

	/**
	 * Truncate cell values in items for table/CSV output.
	 *
	 * @param iterable $items  Items to process.
	 * @param array    $fields Fields to truncate.
	 * @return array Processed items with truncated values.
	 */
	private function truncate_items( $items, $fields ) {
		$truncated = [];
		foreach ( $items as $item ) {
			$row = Utils\pick_fields( $item, $fields );
			// Truncate each field value
			foreach ( $row as $key => $value ) {
				if ( is_string( $value ) && strlen( $value ) > self::MAX_CELL_WIDTH ) {
					$row[ $key ] = substr( $value, 0, self::MAX_CELL_WIDTH ) . '...';
				}
			}
			$truncated[] = $row;
		}
		return $truncated;
	}

	/**
	 * Format items according to arguments.
	 *
	 * @param iterable   $items               Items.
	 * @param bool|array $ascii_pre_colorized Optional. A boolean or an array of booleans to pass to `show_table()` if items in the table are pre-colorized. Default false.
	 */
	private function format( $items, $ascii_pre_colorized = false ): void {
		$fields = $this->args['fields'];

		// Convert iterator to array if needed
		if ( ! is_array( $items ) ) {
			$items = iterator_to_array( $items );
		}

		// Check if a formatter is registered for this format
		if ( isset( self::$custom_formatters[ $this->args['format'] ] ) ) {
			// Special handling for 'ids' and 'count' formats - they work with raw items
			if ( in_array( $this->args['format'], [ 'ids', 'count' ], true ) ) {
				call_user_func( self::$custom_formatters[ $this->args['format'] ], $items, $fields );
				return;
			}

			// Special preprocessing for table and csv formats
			if ( in_array( $this->args['format'], [ 'table', 'csv' ], true ) ) {
				$items = $this->truncate_items( $items, $fields );
			}

			// Extract fields from items for formatter
			$formatted_items = [];
			foreach ( $items as $item ) {
				if ( is_array( $item ) || is_object( $item ) ) {
					// @phpstan-ignore-next-line - $item is guaranteed to be array|object here
					$formatted_items[] = Utils\pick_fields( $item, $fields );
				} else {
					WP_CLI::debug( 'Skipping item that is neither array nor object in format handler.', 'formatter' );
				}
			}

			// Call the formatter - pass $this as third parameter for built-in formats that need it
			$handler = self::$custom_formatters[ $this->args['format'] ];
			if ( in_array( $this->args['format'], [ 'table' ], true ) ) {
				// Table format needs the formatter instance and ascii_pre_colorized
				call_user_func( $handler, $formatted_items, $fields, $this, $ascii_pre_colorized );
			} else {
				// Other formats just need items and fields
				call_user_func( $handler, $formatted_items, $fields );
			}
			return;
		}

		// If no formatter is registered, show error
		WP_CLI::error( 'Invalid format: ' . $this->args['format'] );
	}

	/**
	 * Show a single field from a list of items.
	 *
	 * @param iterable $items Array of objects to show fields from
	 * @param string   $field The field to show
	 */
	private function show_single_field( $items, $field ): void {
		$key         = null;
		$values      = [];
		$field_found = false;
		$item_count  = 0;

		foreach ( $items as $item ) {
			++$item_count;
			$item = (object) $item;

			// Resolve the key on first item that has the field
			if ( ! $field_found && null === $key ) {
				$key = $this->find_item_key( $item, $field, true );
				if ( null !== $key ) {
					$field_found = true;
				}
			}

			// Get value if key exists
			$value = ( null !== $key && isset( $item->$key ) ) ? $item->$key : null;

			if ( 'json' === $this->args['format'] ) {
				$values[] = $value;
			} else {
				WP_CLI::print_value(
					$value,
					[
						'format' => $this->args['format'],
					]
				);
			}
		}

		if ( ! $field_found && $item_count > 0 ) {
			WP_CLI::warning( "Field not found in any item: $field." );
		}

		if ( 'json' === $this->args['format'] ) {
			echo json_encode( $values );
		}
	}

	/**
	 * Validate that requested fields exist in at least one item.
	 * Warns if a field doesn't exist in any item.
	 * Also resolves field names to their actual keys (including prefixes).
	 *
	 * @param iterable $items Items to validate
	 */
	private function validate_fields( $items ): void {
		// Track which fields have been found and their resolved keys
		$fields_to_find  = array_flip( $this->args['fields'] );
		$resolved_fields = [];
		$fields_count    = count( $fields_to_find );
		$found_count     = 0;
		$item_count      = 0;

		// Iterate through items once and check all fields
		foreach ( $items as $item ) {
			++$item_count;
			// Check each field that hasn't been found yet
			foreach ( $fields_to_find as $field => $_ ) {
				$key = $this->find_item_key( $item, $field, true );
				if ( null !== $key ) {
					// Store the resolved field name
					$resolved_fields[ $field ] = $key;
					// Mark this field as found
					unset( $fields_to_find[ $field ] );
					++$found_count;
					// If all fields found, we can stop early
					if ( $found_count === $fields_count ) {
						break 2;
					}
				}
			}
		}

		// Update the fields array with resolved field names
		foreach ( $this->args['fields'] as &$field ) {
			if ( isset( $resolved_fields[ $field ] ) ) {
				$field = $resolved_fields[ $field ];
			}
		}
		unset( $field ); // Break the reference to avoid issues with subsequent foreach loops

		// Only warn about missing fields if there were items to check
		if ( $item_count > 0 ) {
			// Warn about any fields that weren't found in any item
			foreach ( $fields_to_find as $missing_field => $_ ) {
				WP_CLI::warning( "Field not found in any item: $missing_field." );
			}
		}
	}

	/**
	 * Find an object's key.
	 * If $prefix is set, a key with that prefix will be prioritized.
	 *
	 * @param array|object $item
	 * @param string       $field
	 * @param bool         $lenient If true, return null instead of erroring when field is not found.
	 * @return string|null
	 */
	private function find_item_key( $item, $field, $lenient = false ) {
		foreach ( [ $field, $this->prefix . '_' . $field ] as $maybe_key ) {
			if (
				( is_object( $item ) && ( property_exists( $item, $maybe_key ) || isset( $item->$maybe_key ) ) ) ||
				( is_array( $item ) && array_key_exists( $maybe_key, $item ) )
			) {
				$key = $maybe_key;
				break;
			}
		}

		if ( ! isset( $key ) ) {
			if ( $lenient ) {
				return null;
			}
			WP_CLI::error( "Invalid field: $field." );
		}

		return $key;
	}

	/**
	 * Show multiple fields of an object.
	 *
	 * @param iterable   $data                Data to display
	 * @param string     $format              Format to display the data in
	 * @param bool|array $ascii_pre_colorized Optional. A boolean or an array of booleans to pass to `show_table()` if the item in the table is pre-colorized. Default false.
	 */
	private function show_multiple_fields( $data, $format, $ascii_pre_colorized = false ): void {

		$true_fields = [];
		foreach ( $this->args['fields'] as $field ) {
			$key = $this->find_item_key( $data, $field, true );
			if ( null === $key ) {
				// Field doesn't exist, show warning
				WP_CLI::warning( "Field not found in item: $field." );
			} else {
				$true_fields[] = $key;
			}
		}

		foreach ( $data as $key => $value ) {
			if ( ! in_array( $key, $true_fields, true ) ) {
				if ( is_array( $data ) ) {
					unset( $data[ $key ] );
				} elseif ( is_object( $data ) ) {
					unset( $data->$key );
				}
			}
		}

		$ordered_data = [];

		foreach ( $true_fields as $field ) {
			$ordered_data[ $field ] = ( ( (array) $data )[ $field ] );
		}

		// Check if a formatter is registered for this format
		if ( isset( self::$custom_formatters[ $format ] ) ) {
			// For table and csv formats in single-item display, convert to rows format
			if ( in_array( $format, [ 'table', 'csv' ], true ) ) {
				$rows   = $this->assoc_array_to_rows( $ordered_data );
				$fields = [ 'Field', 'Value' ];
				call_user_func( self::$custom_formatters[ $format ], $rows, $fields, $this, $ascii_pre_colorized );
			} else {
				// For all other formats, call with a single-item array
				call_user_func( self::$custom_formatters[ $format ], [ $ordered_data ], array_keys( $ordered_data ) );
			}
			return;
		}

		// If no formatter is registered, show error
		WP_CLI::error( 'Invalid format: ' . $format );
	}

	/**
	 * Show items in a \cli\Table.
	 *
	 * @param iterable   $items               Items.
	 * @param array      $fields              Fields.
	 * @param bool|array $ascii_pre_colorized Optional. A boolean or an array of booleans to pass to `Table::setAsciiPreColorized()` if items in the table are pre-colorized. Default false.
	 */
	private function show_table( $items, $fields, $ascii_pre_colorized = false ) {
		$table = new Table();

		$enabled = WP_CLI::get_runner()->in_color();
		if ( $enabled ) {
			Colors::disable( true );
		}

		$table->setAsciiPreColorized( $ascii_pre_colorized );
		$table->setHeaders( $fields );
		$table->setAlignments(
			$this->args['alignments']
		);

		foreach ( $items as $item ) {
			$table->addRow( array_values( Utils\pick_fields( $item, $fields ) ) );
		}

		foreach ( $table->getDisplayLines() as $line ) {
			WP_CLI::line( $line );
		}

		if ( $enabled ) {
			Colors::enable( true );
		}
	}

	/**
	 * Format an associative array as a table.
	 *
	 * @param iterable $fields Fields and values to format
	 * @return array
	 */
	private function assoc_array_to_rows( $fields ) {
		$rows = [];

		foreach ( $fields as $field => $value ) {

			if ( ! is_string( $value ) ) {
				$value = json_encode( $value );
			}

			// Truncate large values for table/CSV output performance
			if ( is_string( $value ) && strlen( $value ) > self::MAX_CELL_WIDTH ) {
				$value = substr( $value, 0, self::MAX_CELL_WIDTH ) . '...';
			}

			$rows[] = (object) [
				'Field' => $field,
				'Value' => $value,
			];
		}

		return $rows;
	}

	/**
	 * Transforms item values for string-based output formats (table/CSV).
	 *
	 * Converts complex types to strings:
	 * - Objects and arrays are converted to JSON strings
	 * - Booleans are converted to "true" or "false"
	 *
	 * @param array|object $item
	 * @return mixed
	 */
	public function transform_item_values_to_json( $item ) {
		foreach ( $this->args['fields'] as $field ) {
			$true_field = $this->find_item_key( $item, $field, true );
			if ( null === $true_field ) {
				// Field doesn't exist in this item, skip it
				continue;
			}
			$value = is_object( $item ) ? $item->$true_field : $item[ $true_field ];
			if ( is_array( $value ) || is_object( $value ) ) {
				if ( is_object( $item ) ) {
					$item->$true_field = json_encode( $value );
				} elseif ( is_array( $item ) ) {
					$item[ $true_field ] = json_encode( $value );
				}
			} elseif ( is_bool( $value ) ) {
				// Convert boolean to string representation for table/CSV display
				if ( is_object( $item ) ) {
					$item->$true_field = $value ? 'true' : 'false';
				} elseif ( is_array( $item ) ) {
					$item[ $true_field ] = $value ? 'true' : 'false';
				}
			}
		}
		return $item;
	}
}
