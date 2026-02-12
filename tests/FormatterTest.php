<?php

use WP_CLI\Formatter;
use PHPUnit\Framework\TestCase;

class FormatterTest extends TestCase {

	public function test_add_format() {
		$called  = false;
		$handler = function ( $items, $fields ) use ( &$called ) {
			$called = true;
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
			function ( $items, $fields ) {
				echo 'XML';
			}
		);

		$formats = Formatter::get_available_formats();
		$this->assertContains( 'xml', $formats );
	}

	public function test_custom_format_with_single_item() {
		$output_collected = '';
		$handler          = function ( $items, $fields ) use ( &$output_collected ) {
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
}
