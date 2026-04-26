<?php

use WP_CLI\Formatter;
use WP_CLI\Tests\TestCase;

class FormatterTest extends TestCase {

	public function testDisplayItemsVarExportFormat(): void {
		$items      = [
			[
				'label' => 'Foo',
				'slug'  => 'foo',
			],
		];
		$assoc_args = [
			'fields' => 'label,slug',
			'format' => 'var_export',
		];
		$formatter  = new Formatter( $assoc_args );
		ob_start();
		$formatter->display_items( $items );
		$out = $this->capture_stdout();
		$this->assertStringStartsWith( 'array (', $out );
		$this->assertStringContainsString( "'label' => 'Foo'", $out );
		$this->assertStringContainsString( "'slug' => 'foo'", $out );
	}

	public function testDisplayItemsPlaintextFormat(): void {
		$items      = [
			[
				'label' => 'Bar',
				'slug'  => 'bar',
			],
		];
		$assoc_args = [
			'fields' => 'label,slug',
			'format' => 'plaintext',
		];
		$formatter  = new Formatter( $assoc_args );
		ob_start();
		$formatter->display_items( $items );
		$out = $this->capture_stdout();
		$this->assertStringStartsWith( 'array (', $out );
		$this->assertStringContainsString( "'label' => 'Bar'", $out );
	}

	public function testDisplayItemSingleVarExportFormat(): void {
		$item       = [
			'label' => 'One',
			'slug'  => 'one',
		];
		$assoc_args = [
			'field'  => 'slug',
			'fields' => 'label,slug',
			'format' => 'var_export',
		];
		$formatter  = new Formatter( $assoc_args );
		ob_start();
		$formatter->display_item( $item );
		$out = $this->capture_stdout();
		$this->assertSame( "one\n", $out );
	}

	public function testDisplayItemAllFieldsVarExport(): void {
		$item       = [
			'label' => 'Z',
			'slug'  => 'z',
		];
		$assoc_args = [
			'fields' => 'label,slug',
			'format' => 'var_export',
		];
		$formatter  = new Formatter( $assoc_args );
		ob_start();
		$formatter->display_item( $item );
		$out = $this->capture_stdout();
		$this->assertStringContainsString( "'label' => 'Z'", $out );
		$this->assertStringContainsString( "'slug' => 'z'", $out );
	}

	/**
	 * Normalize captured stdout for Windows (CRLF to LF).
	 *
	 * @return string
	 */
	private function capture_stdout(): string {
		$out = ob_get_clean();
		$this->assertIsString( $out );

		return str_replace( [ "\r\n", "\r" ], "\n", $out );
	}
}
