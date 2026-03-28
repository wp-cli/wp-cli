<?php

use WP_CLI\Tests\TestCase;

class WPCLITest extends TestCase {

	public function testGetPHPBinary(): void {
		$this->assertSame( WP_CLI\Utils\get_php_binary(), WP_CLI::get_php_binary() );
	}

	public function testErrorToString(): void {
		$this->expectException( 'InvalidArgumentException' );
		$this->expectExceptionMessage( "Unsupported argument type passed to WP_CLI::error_to_string(): 'boolean'" );
		// @phpstan-ignore argument.type
		WP_CLI::error_to_string( true );
	}

	public function testPrintValueJsonFormat(): void {
		ob_start();
		WP_CLI::print_value(
			'hello',
			[
				'format' => 'json',
			]
		);
		$out = ob_get_clean();
		$this->assertIsString( $out );
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
		$out = ob_get_clean();
		$this->assertIsString( $out );
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
		$out = ob_get_clean();
		$this->assertIsString( $out );
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
		$out = ob_get_clean();
		$this->assertIsString( $out );
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
		$out = ob_get_clean();
		$this->assertIsString( $out );
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
		$out = ob_get_clean();
		$this->assertIsString( $out );
		$this->assertStringContainsString( "'x' => 'y'", $out );
	}

	public function testPrintValueIntegerScalar(): void {
		ob_start();
		WP_CLI::print_value( 42, [] );
		$out = ob_get_clean();
		$this->assertIsString( $out );
		$this->assertSame( "42\n", $out );
	}

	public function testPrintValueFloatScalar(): void {
		ob_start();
		WP_CLI::print_value( 1.5, [] );
		$out = ob_get_clean();
		$this->assertIsString( $out );
		$this->assertSame( "1.5\n", $out );
	}

	public function testPrintValueBooleanTrue(): void {
		ob_start();
		WP_CLI::print_value( true, [] );
		$out = ob_get_clean();
		$this->assertIsString( $out );
		$this->assertSame( "1\n", $out );
	}

	public function testPrintValueBooleanFalse(): void {
		ob_start();
		WP_CLI::print_value( false, [] );
		$out = ob_get_clean();
		$this->assertIsString( $out );
		$this->assertSame( "\n", $out );
	}

	public function testPrintValueNull(): void {
		ob_start();
		WP_CLI::print_value( null, [] );
		$out = ob_get_clean();
		$this->assertIsString( $out );
		$this->assertSame( "\n", $out );
	}
}
