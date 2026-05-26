<?php

use WP_CLI\Formatter;
use WP_CLI\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class WPCLITest extends TestCase {

	public static function set_up_before_class(): void {
		// Ensure built-in formats are registered for print_value tests
		Formatter::register_builtin_formats();
	}

	public function test_get_php_binary(): void {
		$this->assertSame( WP_CLI\Utils\get_php_binary(), WP_CLI::get_php_binary() );
	}

	public function testErrorToString(): void {
		$this->expectException( 'InvalidArgumentException' );
		$this->expectExceptionMessage( "Unsupported argument type passed to WP_CLI::error_to_string(): 'boolean'" );
		// @phpstan-ignore argument.type
		WP_CLI::error_to_string( true );
	}

	/**
	 * @dataProvider data_print_value
	 */
	#[DataProvider( 'data_print_value' )] // phpcs:ignore PHPCompatibility.Attributes.NewAttributes.PHPUnitAttributeFound
	public function test_print_value( $value, $assoc_args, $expected_contains ): void {
		ob_start();
		WP_CLI::print_value( $value, $assoc_args );
		$out = (string) ob_get_clean();

		$this->assertStringContainsString( $expected_contains, $out );
	}

	/**
	 * @return array<string, array{0: mixed, 1: array<string, mixed>, 2: string}>
	 */
	public static function data_print_value(): array {
		return [
			'json format scalar'      => [
				'hello',
				[ 'format' => 'json' ],
				'"hello"' . "\n",
			],
			'json format array'       => [
				[ 'a' => 1 ],
				[ 'format' => 'json' ],
				'{"a":1}' . "\n",
			],
			'yaml format array'       => [
				[ 'a' => 1 ],
				[ 'format' => 'yaml' ],
				"a: 1\n",
			],
			'var_export format array' => [
				[ 'a' => 1 ],
				[ 'format' => 'var_export' ],
				"array (\n  'a' => 1,\n)",
			],
			'plaintext format array'  => [
				[ 'a' => 1 ],
				[ 'format' => 'plaintext' ],
				"array (\n  'a' => 1,\n)",
			],
			'plaintext format scalar' => [
				'hello',
				[ 'format' => 'plaintext' ],
				"hello\n",
			],
			'default format scalar'   => [
				'hello',
				[],
				"hello\n",
			],
			'default format array'    => [
				[ 'a' => 1 ],
				[],
				"array (\n  'a' => 1,\n)",
			],
		];
	}

	public function testPrintValueJsonFormat(): void {
		ob_start();
		WP_CLI::print_value(
			'hello',
			[
				'format' => 'json',
			]
		);
		$out = $this->capture_stdout();
		$this->assertSame( '"hello"' . "\n", $out );
	}

	public function testPrintValueVarExportFormatScalarUnquoted(): void {
		ob_start();
		WP_CLI::print_value(
			'https://example.com',
			[
				'format' => 'var_export',
			]
		);
		$out = $this->capture_stdout();
		$this->assertSame( 'https://example.com' . "\n", $out );
	}

	public function testPrintValuePlaintextFormatScalarUnquoted(): void {
		ob_start();
		WP_CLI::print_value(
			'https://example.com',
			[
				'format' => 'plaintext',
			]
		);
		$out = $this->capture_stdout();
		$this->assertSame( 'https://example.com' . "\n", $out );
	}

	public function testPrintValueVarExportFormatArray(): void {
		ob_start();
		WP_CLI::print_value(
			[
				'a' => 1,
			],
			[
				'format' => 'var_export',
			]
		);
		$out = $this->capture_stdout();
		$this->assertStringContainsString( "'a' => 1", $out );
		$this->assertStringStartsWith( 'array (', $out );
	}

	public function testPrintValueYamlFormat(): void {
		ob_start();
		WP_CLI::print_value(
			[
				'k' => 'v',
			],
			[
				'format' => 'yaml',
			]
		);
		$out = $this->capture_stdout();
		$this->assertStringContainsString( 'k:', $out );
		$this->assertStringContainsString( 'v', $out );
	}

	public function testPrintValueDefaultArrayUsesVarExport(): void {
		ob_start();
		WP_CLI::print_value(
			[
				'x' => 'y',
			],
			[]
		);
		$out = $this->capture_stdout();
		$this->assertStringContainsString( "'x' => 'y'", $out );
	}

	public function testPrintValueIntegerScalar(): void {
		ob_start();
		WP_CLI::print_value( 42, [] );
		$out = $this->capture_stdout();
		$this->assertSame( "42\n", $out );
	}

	public function testPrintValueFloatScalar(): void {
		ob_start();
		WP_CLI::print_value( 1.5, [] );
		$out = $this->capture_stdout();
		$this->assertSame( "1.5\n", $out );
	}

	public function testPrintValueBooleanTrue(): void {
		ob_start();
		WP_CLI::print_value( true, [] );
		$out = $this->capture_stdout();
		$this->assertSame( "1\n", $out );
	}

	public function testPrintValueBooleanFalse(): void {
		ob_start();
		WP_CLI::print_value( false, [] );
		$out = $this->capture_stdout();
		$this->assertSame( "\n", $out );
	}

	public function testPrintValueNull(): void {
		ob_start();
		WP_CLI::print_value( null, [] );
		$out = $this->capture_stdout();
		$this->assertSame( "\n", $out );
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
