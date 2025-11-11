<?php

namespace WP_CLI\Tests\Utils;

use WP_CLI\Formatter;
use WP_CLI\Tests\TestCase;

class YamlScalarFormatterTest extends TestCase {

	private function runFormatter( $value ) {
		ob_start();
		$formatter = new Formatter( null, $value, 'yaml' );
		$formatter->display();
		return ob_get_clean();
	}

	public function test_zero_is_preserved() {
		$output = $this->runFormatter( 0 );
		$this->assertStringContainsString( '---', $output );
		$this->assertStringContainsString( "\n0\n", $output );
	}

	public function test_null_outputs_empty_scalar() {
		$output = $this->runFormatter( null );
		$this->assertStringContainsString( '---', $output );
		$this->assertMatchesRegularExpression( '/^---\s*$/m', trim( $output ) );
	}

	public function test_empty_string_outputs_quoted_empty() {
		$output = $this->runFormatter( '' );
		$this->assertStringContainsString( '---', $output );
		$this->assertStringContainsString( '""', $output );
	}

	public function test_string_passes_through() {
		$output = $this->runFormatter( 'hello' );
		$this->assertStringContainsString( '---', $output );
		$this->assertStringContainsString( 'hello', $output );
	}
}
