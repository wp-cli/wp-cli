<?php

use WP_CLI\Formatter;
use WP_CLI\Tests\TestCase;

class FormatterTest extends TestCase {

	public function testDisplayItemsVarExportFormat(): void {
		$items = [
			[
				'label' => 'Foo',
				'slug' => 'foo',
			],
		];
		$assoc_args = [
			'format' => 'var_export',
			'fields' => 'label,slug',
		];
		$formatter = new Formatter( $assoc_args );
		ob_start();
		$formatter->display_items( $items );
		$out = ob_get_clean();
		$this->assertIsString( $out );
		$this->assertStringStartsWith( 'array (', $out );
		$this->assertStringContainsString( "'label' => 'Foo'", $out );
		$this->assertStringContainsString( "'slug' => 'foo'", $out );
	}

	public function testDisplayItemsPlaintextFormat(): void {
		$items = [
			[
				'label' => 'Bar',
				'slug' => 'bar',
			],
		];
		$assoc_args = [
			'format' => 'plaintext',
			'fields' => 'label,slug',
		];
		$formatter = new Formatter( $assoc_args );
		ob_start();
		$formatter->display_items( $items );
		$out = ob_get_clean();
		$this->assertIsString( $out );
		$this->assertStringStartsWith( 'array (', $out );
		$this->assertStringContainsString( "'label' => 'Bar'", $out );
	}

	public function testDisplayItemSingleVarExportFormat(): void {
		$item = [
			'label' => 'One',
			'slug' => 'one',
		];
		$assoc_args = [
			'format' => 'var_export',
			'fields' => 'label,slug',
			'field' => 'slug',
		];
		$formatter = new Formatter( $assoc_args );
		ob_start();
		$formatter->display_item( $item );
		$out = ob_get_clean();
		$this->assertIsString( $out );
		$this->assertSame( "one\n", $out );
	}

	public function testDisplayItemAllFieldsVarExport(): void {
		$item = [
			'label' => 'Z',
			'slug' => 'z',
		];
		$assoc_args = [
			'format' => 'var_export',
			'fields' => 'label,slug',
		];
		$formatter = new Formatter( $assoc_args );
		ob_start();
		$formatter->display_item( $item );
		$out = ob_get_clean();
		$this->assertIsString( $out );
		$this->assertStringContainsString( "'label' => 'Z'", $out );
		$this->assertStringContainsString( "'slug' => 'z'", $out );
	}
}
