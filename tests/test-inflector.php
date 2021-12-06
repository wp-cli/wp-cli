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

	public function dataProviderPluralize() {
		return [
			[ 'string', 'strings' ], // regular
			[ 'person', 'people' ],  // irregular
			[ 'scissors', 'scissors' ], // uncountable
		];
	}

	/**
	 * @dataProvider dataProviderSingularize
	 */
	public function testSingularize( $singular, $expected ) {
		$this->assertEquals( $expected, Inflector::singularize( $singular ) );
	}

	public function dataProviderSingularize() {
		return [
			[ 'strings', 'string' ], // regular
			[ 'people', 'person' ],  // irregular
			[ 'scissors', 'scissors' ], // uncountable
		];
	}
}
