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
		WP_CLI::print_value( 'hello', [ 'format' => 'json' ] );
		$this->assertSame( '"hello"' . "\n", ob_get_clean() );
	}

	public function testPrintValueVarExportFormatScalarUnquoted(): void {
		ob_start();
		WP_CLI::print_value( 'https://example.com', [ 'format' => 'var_export' ] );
		$this->assertSame( 'https://example.com' . "\n", ob_get_clean() );
	}

	public function testPrintValuePlaintextFormatScalarUnquoted(): void {
		ob_start();
		WP_CLI::print_value( 'https://example.com', [ 'format' => 'plaintext' ] );
		$this->assertSame( 'https://example.com' . "\n", ob_get_clean() );
	}

	public function testPrintValueVarExportFormatArray(): void {
		ob_start();
		WP_CLI::print_value( [ 'a' => 1 ], [ 'format' => 'var_export' ] );
		$out = ob_get_clean();
		$this->assertStringContainsString( "'a' => 1", $out );
		$this->assertStringStartsWith( 'array (', $out );
	}

	public function testPrintValueYamlFormat(): void {
		ob_start();
		WP_CLI::print_value( [ 'k' => 'v' ], [ 'format' => 'yaml' ] );
		$out = ob_get_clean();
		$this->assertStringContainsString( 'k:', $out );
		$this->assertStringContainsString( 'v', $out );
	}

	public function testPrintValueDefaultArrayUsesVarExport(): void {
		ob_start();
		WP_CLI::print_value( [ 'x' => 'y' ], [] );
		$out = ob_get_clean();
		$this->assertStringContainsString( "'x' => 'y'", $out );
	}

	public function testPrintValueIntegerScalar(): void {
		ob_start();
		WP_CLI::print_value( 42, [] );
		$this->assertSame( "42\n", ob_get_clean() );
	}

	public function testPrintValueFloatScalar(): void {
		ob_start();
		WP_CLI::print_value( 1.5, [] );
		$this->assertSame( "1.5\n", ob_get_clean() );
	}

	public function testPrintValueBooleanTrue(): void {
		ob_start();
		WP_CLI::print_value( true, [] );
		$this->assertSame( "1\n", ob_get_clean() );
	}

	public function testPrintValueBooleanFalse(): void {
		ob_start();
		WP_CLI::print_value( false, [] );
		$this->assertSame( "\n", ob_get_clean() );
	}

	public function testPrintValueNull(): void {
		ob_start();
		WP_CLI::print_value( null, [] );
		$this->assertSame( "\n", ob_get_clean() );
	}
}
