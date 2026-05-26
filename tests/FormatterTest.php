<?php

use WP_CLI\Formatter;
use PHPUnit\Framework\TestCase;

class FormatterTest extends TestCase {

	public static function setUpBeforeClass(): void {
		// Ensure built-in formats are registered for tests
		Formatter::register_builtin_formats();
	}

	public function test_add_format() {
		$called          = false;
		$received_items  = null;
		$received_fields = null;
		$handler         = function ( $items, $fields ) use ( &$called, &$received_items, &$received_fields ) {
			$called          = true;
			$received_items  = $items;
			$received_fields = $fields;
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
	}

	public function test_get_available_formats() {
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

	public function test_custom_format_with_single_item() {
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

	public function test_custom_format_field_filtering() {
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

	public function test_custom_format_with_prefix() {
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

	public function test_override_builtin_format() {
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

	public function test_add_single_value_format() {
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

	public function test_has_single_value_format() {
		$this->assertTrue( Formatter::has_single_value_format( 'json' ), 'json format should be registered' );
		$this->assertTrue( Formatter::has_single_value_format( 'yaml' ), 'yaml format should be registered' );
		$this->assertTrue( Formatter::has_single_value_format( 'var_export' ), 'var_export format should be registered' );
		$this->assertFalse( Formatter::has_single_value_format( 'nonexistent' ), 'nonexistent format should not be registered' );
	}

	public function test_format_single_value_json() {
		$value  = [ 'key' => 'value' ];
		$result = Formatter::format_single_value( $value, 'json' );
		$this->assertSame( '{"key":"value"}', $result );
	}

	public function test_format_single_value_yaml() {
		$value  = [ 'key' => 'value' ];
		$result = Formatter::format_single_value( $value, 'yaml' );
		$this->assertStringContainsString( 'key: value', $result );
	}

	public function test_format_single_value_var_export() {
		$value  = [ 'key' => 'value' ];
		$result = Formatter::format_single_value( $value, 'var_export' );
		$this->assertStringContainsString( "'key' => 'value'", $result );
	}

	public function test_format_single_value_fallback() {
		// Test fallback for unregistered format
		$value  = [ 'key' => 'value' ];
		$result = Formatter::format_single_value( $value, 'unknown_format' );
		$this->assertStringContainsString( "'key' => 'value'", $result, 'Should fallback to var_export for arrays' );

		// Test fallback for scalar values
		$result = Formatter::format_single_value( 'simple_string', 'unknown_format' );
		$this->assertSame( 'simple_string', $result, 'Should return string as-is for scalars' );
	}
}
