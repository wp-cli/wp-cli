<?php

use PHPUnit\Framework\Attributes\DataProvider;
use WP_CLI\Formatter;
use WP_CLI\Tests\TestCase;

class FormatterTest extends TestCase {

	public function tear_down(): void {
		// Several tests register handlers that override the built-in formats. Wipe the
		// registry so that they cannot leak into the next test; the built-ins are
		// registered again on demand.
		$this->reset_format_registry();

		parent::tear_down();
	}

	/**
	 * Wipe the format registry, as if this copy of the class had just been autoloaded
	 * and the `InitializeFormatter` bootstrap step had never run for it.
	 *
	 * @return void
	 */
	private function reset_format_registry(): void {
		foreach (
			[
				'custom_formatters'          => [],
				'format_options'             => [],
				'single_value_formatters'    => [],
				'builtin_formats_registered' => false,
			] as $property => $value
		) {
			$reflection = new \ReflectionProperty( Formatter::class, $property );
			if ( PHP_VERSION_ID < 80100 ) {
				// @phpstan-ignore method.deprecated
				$reflection->setAccessible( true );
			}
			$reflection->setValue( null, $value );
		}
	}

	public function test_add_format(): void {
		$called             = false;
		$received_items     = null;
		$received_fields    = null;
		$received_formatter = null;
		$received_args      = null;
		$handler            = function ( $items, $fields, $formatter, $args ) use ( &$called, &$received_items, &$received_fields, &$received_formatter, &$received_args ) {
			$called             = true;
			$received_items     = $items;
			$received_fields    = $fields;
			$received_formatter = $formatter;
			$received_args      = $args;
			echo 'CUSTOM';
		};

		Formatter::add_format( 'test_format', $handler );

		$items = [
			[
				'name' => 'Alice',
				'age'  => 30,
			],
			[
				'name' => 'Bob',
				'age'  => 25,
			],
		];

		$assoc_args = [ 'format' => 'test_format' ];
		$formatter  = new Formatter( $assoc_args, [ 'name', 'age' ] );

		ob_start();
		$formatter->display_items( $items );
		$output = ob_get_clean();

		$this->assertTrue( $called, 'Custom format handler should be called' );
		$this->assertSame( 'CUSTOM', $output );

		// Verify correct parameters were passed
		$this->assertIsArray( $received_items, 'Handler should receive items array' );
		$this->assertCount( 2, $received_items, 'Handler should receive all items' );
		$this->assertIsArray( $received_items[0], 'First item should be an array' );
		$this->assertArrayHasKey( 'name', $received_items[0], 'Items should have name field' );
		$this->assertArrayHasKey( 'age', $received_items[0], 'Items should have age field' );
		$this->assertSame( [ 'name', 'age' ], $received_fields, 'Handler should receive fields array' );
		$this->assertInstanceOf( Formatter::class, $received_formatter, 'Handler should receive formatter instance' );
		$this->assertIsArray( $received_args, 'Handler should receive args array' );
	}

	public function test_get_available_formats(): void {
		$formats = Formatter::get_available_formats();
		$this->assertContains( 'table', $formats );
		$this->assertContains( 'json', $formats );
		$this->assertContains( 'csv', $formats );
		$this->assertContains( 'yaml', $formats );
		$this->assertContains( 'count', $formats );
		$this->assertContains( 'ids', $formats );

		// Add a custom format
		Formatter::add_format(
			'xml',
			static function () {
				echo 'XML';
			}
		);

		$formats = Formatter::get_available_formats();
		$this->assertContains( 'xml', $formats );
	}

	public function test_custom_format_with_single_item(): void {
		$output_collected = '';
		$handler          = static function ( $items ) use ( &$output_collected ) {
			foreach ( $items as $item ) {
				foreach ( $item as $key => $value ) {
					$output_collected .= "$key:$value ";
				}
			}
		};

		Formatter::add_format( 'test_single', $handler );

		$item       = [
			'name' => 'Charlie',
			'age'  => 35,
		];
		$assoc_args = [ 'format' => 'test_single' ];
		$formatter  = new Formatter( $assoc_args, [ 'name', 'age' ] );

		ob_start();
		$formatter->display_item( $item );
		ob_get_clean();

		$this->assertStringContainsString( 'name:Charlie', $output_collected );
		$this->assertStringContainsString( 'age:35', $output_collected );
	}

	public function test_custom_format_field_filtering(): void {
		$received_items = null;
		$handler        = function ( $items ) use ( &$received_items ) {
			$received_items = $items;
		};

		Formatter::add_format( 'test_filter', $handler );

		$items = [
			[
				'name'  => 'Test',
				'age'   => 30,
				'email' => 'test@example.com',
			],
		];

		// Only request name and age fields
		$assoc_args = [ 'format' => 'test_filter' ];
		$formatter  = new Formatter( $assoc_args, [ 'name', 'age' ] );

		ob_start();
		$formatter->display_items( $items );
		ob_get_clean();

		// Handler should only receive the requested fields
		$this->assertIsArray( $received_items, 'Handler should receive items array' );
		$this->assertCount( 1, $received_items, 'Handler should receive 1 item' );
		$this->assertIsArray( $received_items[0], 'First item should be an array' );
		$this->assertArrayHasKey( 'name', $received_items[0] );
		$this->assertArrayHasKey( 'age', $received_items[0] );
		$this->assertArrayNotHasKey( 'email', $received_items[0], 'Non-requested field should be filtered out' );
	}

	public function test_custom_format_with_prefix(): void {
		$received_items = null;
		$handler        = function ( $items ) use ( &$received_items ) {
			$received_items = $items;
		};

		Formatter::add_format( 'test_prefix', $handler );

		$items = [
			[
				'post_title'  => 'Test Post',
				'post_status' => 'publish',
			],
		];

		// Request fields without prefix, but items have prefix
		$assoc_args = [ 'format' => 'test_prefix' ];
		$formatter  = new Formatter( $assoc_args, [ 'title', 'status' ], 'post' );

		ob_start();
		$formatter->display_items( $items );
		ob_get_clean();

		// Handler should receive items with resolved prefixed field names
		$this->assertIsArray( $received_items, 'Handler should receive items array' );
		$this->assertCount( 1, $received_items, 'Handler should receive 1 item' );
		$this->assertIsArray( $received_items[0], 'First item should be an array' );
		// The fields should be resolved to the prefixed versions
		$this->assertArrayHasKey( 'post_title', $received_items[0], 'Should have resolved post_title field' );
		$this->assertArrayHasKey( 'post_status', $received_items[0], 'Should have resolved post_status field' );
		$this->assertSame( 'Test Post', $received_items[0]['post_title'] );
		$this->assertSame( 'publish', $received_items[0]['post_status'] );
	}

	public function test_override_builtin_format(): void {
		$called  = false;
		$handler = function () use ( &$called ) {
			$called = true;
			echo 'OVERRIDDEN';
		};

		// Override the built-in json format
		Formatter::add_format( 'json', $handler );

		$items = [
			[ 'name' => 'Test' ],
		];

		$assoc_args = [ 'format' => 'json' ];
		$formatter  = new Formatter( $assoc_args, [ 'name' ] );

		ob_start();
		$formatter->display_items( $items );
		$output = ob_get_clean();

		$this->assertTrue( $called, 'Custom handler should override built-in format' );
		$this->assertSame( 'OVERRIDDEN', $output );
	}

	public function test_json_from_iterator_matches_array(): void {
		$items = [
			[
				'post_title' => 'First',
				'post_meta'  => [ 'a' => 1 ],
				'unused'     => 'x',
			],
			(object) [
				'post_title' => 'Second "quoted"',
				'post_meta'  => null,
				'unused'     => 'y',
			],
		];

		$generator = ( static function () use ( $items ) {
			yield from $items;
		} )();

		$outputs = [];
		foreach ( [ $items, new ArrayIterator( $items ), $generator ] as $input ) {
			$assoc_args = [
				'format' => 'json',
				'fields' => 'title,meta',
			];
			$formatter  = new Formatter( $assoc_args, null, 'post' );

			ob_start();
			$formatter->display_items( $input );
			$outputs[] = ob_get_clean();
		}

		$this->assertSame( '[{"post_title":"First","post_meta":{"a":1}},{"post_title":"Second \\"quoted\\"","post_meta":null}]', $outputs[0] );
		$this->assertSame( $outputs[0], $outputs[1] );
		$this->assertSame( $outputs[0], $outputs[2] );
	}

	/**
	 * Rows with repeated keys, e.g. from `yield from`, are all output, whether the items
	 * are streamed or not.
	 *
	 * @dataProvider data_iterator_fields
	 */
	#[DataProvider( 'data_iterator_fields' )] // phpcs:ignore PHPCompatibility.Attributes.NewAttributes.PHPUnitAttributeFound
	public function test_iterator_with_repeated_keys_outputs_all_rows( string $fields ): void {
		$generator = ( static function () {
			yield from [ [ 'name' => 'a' ], [ 'name' => 'b' ] ];
			yield from [
				[
					'name'   => 'c',
					'custom' => 'x',
				],
				[
					'name'   => 'd',
					'custom' => 'y',
				],
			];
		} )();

		$assoc_args = [ 'format' => 'json' ];
		$formatter  = new Formatter( $assoc_args, explode( ',', $fields ) );

		ob_start();
		$formatter->display_items( $generator );
		/** @var array<int, array<string, mixed>> $rows */
		$rows = json_decode( (string) ob_get_clean(), true );

		$this->assertSame( [ 'a', 'b', 'c', 'd' ], array_column( $rows, 'name' ) );
	}

	/**
	 * Transformations of an iterator run once per item, whether the items are streamed or not.
	 *
	 * @dataProvider data_iterator_fields
	 */
	#[DataProvider( 'data_iterator_fields' )] // phpcs:ignore PHPCompatibility.Attributes.NewAttributes.PHPUnitAttributeFound
	public function test_iterator_transformations_run_once_per_item( string $fields ): void {
		$calls = 0;
		$items = WP_CLI\Utils\iterator_map(
			[
				[ 'name' => 'a' ],
				[
					'name'   => 'b',
					'custom' => 'x',
				],
			],
			static function ( $item ) use ( &$calls ) {
				$item['number'] = ++$calls;
				return $item;
			}
		);

		$assoc_args = [ 'format' => 'json' ];
		$formatter  = new Formatter( $assoc_args, explode( ',', $fields . ',number' ) );

		ob_start();
		$formatter->display_items( $items );
		/** @var array<int, array<string, mixed>> $rows */
		$rows = json_decode( (string) ob_get_clean(), true );

		$this->assertSame( [ 1, 2 ], array_column( $rows, 'number' ) );
		$this->assertSame( 2, $calls );
	}

	/**
	 * Fields that are all in the first item, so the items are streamed, and fields that
	 * are not, so they are not.
	 *
	 * @return array<string, array{string}>
	 */
	public static function data_iterator_fields(): array {
		return [
			'streamed'     => [ 'name' ],
			'not streamed' => [ 'name,custom' ],
		];
	}

	public function test_json_from_empty_iterator(): void {
		$assoc_args = [ 'format' => 'json' ];
		$formatter  = new Formatter( $assoc_args, [ 'name' ] );

		ob_start();
		$formatter->display_items( new ArrayIterator( [] ) );
		$this->assertSame( '[]', ob_get_clean() );
	}

	public function test_overridden_builtin_format_receives_all_items_from_iterator(): void {
		$received = null;
		Formatter::add_format(
			'json',
			function ( $items ) use ( &$received ) {
				$received = $items;
			}
		);

		$assoc_args = [ 'format' => 'json' ];
		$formatter  = new Formatter( $assoc_args, [ 'name' ] );

		ob_start();
		$formatter->display_items( new ArrayIterator( [ [ 'name' => 'a' ], [ 'name' => 'b' ] ] ) );
		ob_end_clean();

		$this->assertSame( [ [ 'name' => 'a' ], [ 'name' => 'b' ] ], $received );
	}

	public function test_streaming_custom_format_receives_items_one_by_one(): void {
		$log      = [];
		$is_array = null;
		$args     = null;
		Formatter::add_format(
			'test_stream',
			function ( $items, $fields, $formatter, $handler_args ) use ( &$log, &$is_array, &$args ) {
				$is_array = is_array( $items );
				$args     = $handler_args;
				foreach ( $items as $item ) {
					$log[] = [ 'output', $item ];
				}
			},
			[ 'streaming' => true ]
		);

		$assoc_args = [
			'format' => 'test_stream',
			'fields' => 'name,ID',
		];
		$formatter  = new Formatter( $assoc_args, null, 'post' );

		$generator = ( function () use ( &$log ) {
			foreach ( [ [ 1, 'a' ], [ 2, 'b' ] ] as list( $id, $name ) ) {
				$log[] = [ 'read', $id ];
				yield (object) [
					'ID'        => $id,
					'post_name' => $name,
					'extra'     => 'x',
				];
			}
		} )();

		$formatter->display_items( $generator );

		$this->assertFalse( $is_array );
		$this->assertIsArray( $args );
		$this->assertTrue( $args['streaming'] );
		// Each item is passed on before the next one is read, with only the requested fields.
		$this->assertSame(
			[
				[ 'read', 1 ],
				[
					'output',
					[
						'post_name' => 'a',
						'ID'        => 1,
					],
				],
				[ 'read', 2 ],
				[
					'output',
					[
						'post_name' => 'b',
						'ID'        => 2,
					],
				],
			],
			$log
		);
	}

	public function test_streaming_custom_format_receives_array_items_as_array(): void {
		$received = null;
		$args     = null;
		Formatter::add_format(
			'test_stream',
			function ( $items, $fields, $formatter, $handler_args ) use ( &$received, &$args ) {
				$received = $items;
				$args     = $handler_args;
			},
			[ 'streaming' => true ]
		);

		$assoc_args = [ 'format' => 'test_stream' ];
		$formatter  = new Formatter( $assoc_args, [ 'name' ] );
		$formatter->display_items( [ [ 'name' => 'a' ], [ 'name' => 'b' ] ] );

		$this->assertSame( [ [ 'name' => 'a' ], [ 'name' => 'b' ] ], $received );
		$this->assertIsArray( $args );
		$this->assertArrayNotHasKey( 'streaming', $args );
	}

	public function test_overriding_builtin_format_with_streaming_receives_items_one_by_one(): void {
		$received = null;
		Formatter::add_format(
			'csv',
			function ( $items ) use ( &$received ) {
				$received = is_array( $items ) ? 'array' : iterator_to_array( $items, false );
			},
			[ 'streaming' => true ]
		);

		$assoc_args = [ 'format' => 'csv' ];
		$formatter  = new Formatter( $assoc_args, [ 'name', 'tags' ] );
		$formatter->display_items(
			new ArrayIterator(
				[
					[
						'name' => 'a',
						'tags' => [ 'x' ],
					],
				]
			)
		);

		// Like for an array of items, CSV values are JSON-encoded.
		$this->assertSame(
			[
				[
					'name' => 'a',
					'tags' => '["x"]',
				],
			],
			$received
		);
	}

	public function test_add_single_value_format(): void {
		$called         = false;
		$received_value = null;
		$handler        = function ( $value ) use ( &$called, &$received_value ) {
			$called         = true;
			$received_value = $value;
			return 'CUSTOM:' . $value;
		};

		Formatter::add_single_value_format( 'test_single_format', $handler );

		$result = Formatter::format_single_value( 'test_value', 'test_single_format' );

		$this->assertTrue( $called, 'Single-value format handler should be called' );
		$this->assertSame( 'test_value', $received_value, 'Handler should receive the value' );
		$this->assertSame( 'CUSTOM:test_value', $result, 'Handler should return formatted value' );
	}

	public function test_format_single_value_json(): void {
		$value  = [ 'key' => 'value' ];
		$result = Formatter::format_single_value( $value, 'json' );
		$this->assertSame( '{"key":"value"}', $result );
	}

	public function test_format_single_value_yaml(): void {
		$value  = [ 'key' => 'value' ];
		$result = Formatter::format_single_value( $value, 'yaml' );
		$this->assertStringContainsString( 'key: value', $result );
	}

	public function test_format_single_value_var_export(): void {
		$value  = [ 'key' => 'value' ];
		$result = Formatter::format_single_value( $value, 'var_export' );
		$this->assertStringContainsString( "'key' => 'value'", $result );
	}

	public function test_format_single_value_fallback(): void {
		// Test fallback for unregistered format
		$value  = [ 'key' => 'value' ];
		$result = Formatter::format_single_value( $value, 'unknown_format' );
		$this->assertStringContainsString( "'key' => 'value'", $result, 'Should fallback to var_export for arrays' );

		// Test fallback for scalar values
		$result = Formatter::format_single_value( 'simple_string', 'unknown_format' );
		$this->assertSame( 'simple_string', $result, 'Should return string as-is for scalars' );
	}

	public function test_single_item_unsupported_formats(): void {
		$class_wp_cli_capture_exit = new \ReflectionProperty( 'WP_CLI', 'capture_exit' );
		if ( PHP_VERSION_ID < 80100 ) {
			// @phpstan-ignore method.deprecated
			$class_wp_cli_capture_exit->setAccessible( true );
		}
		$prev_capture_exit = $class_wp_cli_capture_exit->getValue();
		$class_wp_cli_capture_exit->setValue( null, true );

		$prev_logger = WP_CLI::get_logger();
		$logger      = new WP_CLI\Loggers\Execution();
		WP_CLI::set_logger( $logger );

		$item = [ 'name' => 'Alice' ];

		try {
			$assoc_args = [ 'format' => 'ids' ];
			$formatter  = new Formatter( $assoc_args, [ 'name' ] );
			$formatter->display_item( $item );
			$this->fail( 'Should have thrown ExitException' );
		} catch ( \WP_CLI\ExitException $e ) {
			$this->assertStringContainsString( 'Error: Invalid format: ids', $logger->stderr );
		} finally {
			$class_wp_cli_capture_exit->setValue( null, $prev_capture_exit );
			WP_CLI::set_logger( $prev_logger );
		}
	}

	public function test_custom_format_options(): void {
		$called  = false;
		$handler = function () use ( &$called ) {
			$called = true;
		};

		Formatter::add_format( 'no_single', $handler, [ 'single_item' => false ] );

		$class_wp_cli_capture_exit = new \ReflectionProperty( 'WP_CLI', 'capture_exit' );
		if ( PHP_VERSION_ID < 80100 ) {
			// @phpstan-ignore method.deprecated
			$class_wp_cli_capture_exit->setAccessible( true );
		}
		$prev_capture_exit = $class_wp_cli_capture_exit->getValue();
		$class_wp_cli_capture_exit->setValue( null, true );

		$prev_logger = WP_CLI::get_logger();
		$logger      = new WP_CLI\Loggers\Execution();
		WP_CLI::set_logger( $logger );

		$item = [ 'name' => 'Bob' ];

		try {
			$assoc_args = [ 'format' => 'no_single' ];
			$formatter  = new Formatter( $assoc_args, [ 'name' ] );
			$formatter->display_item( $item );
			$this->fail( 'Should have thrown ExitException' );
		} catch ( \WP_CLI\ExitException $e ) {
			$this->assertStringContainsString( 'Error: Invalid format: no_single', $logger->stderr );
			$this->assertFalse( $called, 'Handler should not be called when single_item option is false' );
		} finally {
			$class_wp_cli_capture_exit->setValue( null, $prev_capture_exit );
			WP_CLI::set_logger( $prev_logger );
		}
	}

	public function test_plaintext_alias_print_value(): void {
		$value  = [ 'nested' => 'value' ];
		$result = Formatter::format_single_value( $value, 'plaintext' );

		// Should match var_export output
		$this->assertStringContainsString( "'nested' => 'value'", $result );
	}

	public function test_display_item_object_with_protected_properties(): void {
		$dummy = new class() {
			/** @var string */
			public $public_prop = 'public_val';

			/** @var string */
			protected $protected_prop = 'protected_val';

			/** @var string */
			private $private_prop = 'private_val';

			public function get_private_prop(): string {
				return $this->private_prop;
			}
		};

		$assoc_args = [ 'format' => 'json' ];
		$formatter  = new Formatter( $assoc_args, [ 'public_prop' ] );

		ob_start();
		$formatter->display_item( $dummy );
		$output = ob_get_clean();

		$this->assertSame( '{"public_prop":"public_val"}', $output );
	}

	public function test_display_item_object_inaccessible_protected_property_issues_warning(): void {
		$prev_logger = WP_CLI::get_logger();
		$logger      = new WP_CLI\Loggers\Execution();
		WP_CLI::set_logger( $logger );

		$dummy = new class() {
			/** @var string */
			public $public_prop = 'public_val';

			/** @var string */
			protected $protected_prop = 'protected_val';
		};

		try {
			$assoc_args = [ 'format' => 'json' ];
			$formatter  = new Formatter( $assoc_args, [ 'public_prop', 'protected_prop' ] );

			ob_start();
			$formatter->display_item( $dummy );
			$output = ob_get_clean();

			$this->assertSame( '{"public_prop":"public_val"}', $output );
			$this->assertStringContainsString( 'Field not found in item: protected_prop.', $logger->stderr );
		} finally {
			WP_CLI::set_logger( $prev_logger );
		}
	}

	public function test_display_item_object_with_magic_getter(): void {
		$dummy = new class() {
			/** @var string */
			protected $protected_prop = 'protected_val';

			/**
			 * @param string $name
			 * @return mixed
			 */
			public function __get( $name ) {
				if ( 'protected_prop' === $name ) {
					return $this->protected_prop;
				}
				return null;
			}

			/**
			 * @param string $name
			 * @return bool
			 */
			public function __isset( $name ) {
				return 'protected_prop' === $name;
			}
		};

		$assoc_args = [ 'format' => 'json' ];
		$formatter  = new Formatter( $assoc_args, [ 'protected_prop' ] );

		ob_start();
		$formatter->display_item( $dummy );
		$output = ob_get_clean();

		$this->assertSame( '{"protected_prop":"protected_val"}', $output );
	}

	public function test_display_items_with_traversable(): void {
		$items = new \ArrayObject(
			[
				[
					'name' => 'Alice',
					'role' => 'admin',
				],
				[
					'name' => 'Bob',
					'role' => 'editor',
				],
			]
		);

		$assoc_args = [ 'format' => 'json' ];
		$formatter  = new Formatter( $assoc_args, [ 'name', 'role' ] );

		ob_start();
		$formatter->display_items( $items );
		$output = ob_get_clean();

		$this->assertSame( '[{"name":"Alice","role":"admin"},{"name":"Bob","role":"editor"}]', $output );
	}

	public function test_display_items_scalar_items_in_json_and_yaml(): void {
		$items = [ 1, 2, 3 ];

		$assoc_args = [ 'format' => 'json' ];
		$formatter  = new Formatter( $assoc_args );

		ob_start();
		$formatter->display_items( $items );
		$output = ob_get_clean();

		$this->assertSame( '[1,2,3]', $output );

		$assoc_args = [ 'format' => 'yaml' ];
		$formatter  = new Formatter( $assoc_args );

		ob_start();
		$formatter->display_items( $items );
		$output = ob_get_clean();

		$this->assertSame( "---\n- 1\n- 2\n- 3", trim( (string) $output ) );
	}

	/**
	 * Built-in formats have to work without `InitializeFormatter` having run, because
	 * the class can be loaded from a Composer install whose bootstrap never executes.
	 *
	 * @see https://github.com/wp-cli/wp-cli/issues/4632
	 */
	public function test_builtin_formats_self_register_without_bootstrap(): void {
		$this->reset_format_registry();

		$items      = [ [ 'name' => 'Alice' ] ];
		$assoc_args = [ 'format' => 'table' ];
		$formatter  = new Formatter( $assoc_args, [ 'name' ] );

		ob_start();
		$formatter->display_items( $items );
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( 'Alice', $output, 'Built-in table format should work without an explicit register_builtin_formats() call' );
	}

	/**
	 * Without self-registration, `print_value()` silently fell through to var_export()
	 * for `--format=json`, producing wrong output rather than an error.
	 */
	public function test_single_value_formats_self_register_without_bootstrap(): void {
		$this->reset_format_registry();

		$this->assertSame( '{"key":"value"}', Formatter::format_single_value( [ 'key' => 'value' ], 'json' ) );
	}

	public function test_get_available_formats_self_registers_without_bootstrap(): void {
		$this->reset_format_registry();

		$this->assertSame( [ 'table', 'json', 'csv', 'yaml', 'count', 'ids' ], Formatter::get_available_formats() );
	}

	/**
	 * The built-in registration is idempotent, so a late `register_builtin_formats()`
	 * call (e.g. from the bootstrap step) cannot clobber an extension's override.
	 */
	public function test_register_builtin_formats_does_not_clobber_overrides(): void {
		$this->reset_format_registry();

		Formatter::add_format(
			'json',
			static function () {
				echo 'OVERRIDDEN';
			}
		);

		$assoc_args = [ 'format' => 'json' ];
		$formatter  = new Formatter( $assoc_args, [ 'name' ] );

		ob_start();
		$formatter->display_items( [ [ 'name' => 'Alice' ] ] );
		$output = ob_get_clean();

		$this->assertSame( 'OVERRIDDEN', $output );
	}
}
