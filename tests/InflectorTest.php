<?php

use WP_CLI\Inflector;
use WP_CLI\Tests\TestCase;

class InflectorTest extends TestCase {

	/**
	 * @dataProvider dataProviderPluralize
	 */
	public function testPluralize( $singular, $expected ) {
		$this->assertEquals( $expected, Inflector::pluralize( $singular ) );
	}

	public static function dataProviderPluralize() {
		return [
			[ 'string', 'strings' ], // Regular.
			[ 'person', 'people' ],  // Irregular.
			[ 'scissors', 'scissors' ], // Uncountable.
		];
	}

	/**
	 * @dataProvider dataProviderSingularize
	 */
	public function testSingularize( $singular, $expected ) {
		$this->assertEquals( $expected, Inflector::singularize( $singular ) );
	}

	public static function dataProviderSingularize() {
		return [
			[ 'strings', 'string' ], // Regular.
			[ 'people', 'person' ],  // Irregular.
			[ 'scissors', 'scissors' ], // Uncountable.
		];
	}
}
