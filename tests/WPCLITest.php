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
}
