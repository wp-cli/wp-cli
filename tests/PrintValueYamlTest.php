<?php

namespace WP_CLI\Tests;

use WP_CLI;

class PrintValueYamlTest extends TestCase {

	public function testPrintValueYamlEmptyString() {
		$value = '';
		$args  = [ 'format' => 'yaml' ];

		ob_start();
		WP_CLI::print_value( $value, $args );
		$output = trim( ob_get_clean() ?: '' );

		$this->assertSame( "---\n\"\"", $output );
	}

	public function testPrintValueYamlNull() {
		$value = null;
		$args  = [ 'format' => 'yaml' ];

		ob_start();
		WP_CLI::print_value( $value, $args );
		$output = trim( ob_get_clean() ?: '' );

		$this->assertSame( "---\nnull", $output );
	}

	public function testPrintValueYamlScalar() {
		$value = 42;
		$args  = [ 'format' => 'yaml' ];

		ob_start();
		WP_CLI::print_value( $value, $args );
		$output = trim( ob_get_clean() ?: '' );

		$this->assertSame( "---\n42", $output );
	}
		/**
	 * Test YAML output for array fallback via Spyc::YAMLDump().
	 */
	public function test_yaml_array_fallback() {
		$value = [
			'foo' => 'bar',
			'num' => 123,
		];

		ob_start();
		WP_CLI::print_value( $value, [ 'format' => 'yaml' ] );
		$output = trim( ob_get_clean() ?: '' );

		// YAML output should start with '---' and include keys.
		$this->assertStringStartsWith( '---', $output );
		$this->assertStringContainsString( 'foo: bar', $output );
		$this->assertStringContainsString( 'num: 123', $output );
	}
}
