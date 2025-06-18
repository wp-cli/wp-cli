<?php

use WP_CLI\Inflector;
use WP_CLI\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class InflectorTest extends TestCase {

	/**
	 * @dataProvider dataProviderPluralize
	 */
	#[DataProvider( 'dataProviderPluralize' )] // phpcs:ignore PHPCompatibility.Attributes.NewAttributes.PHPUnitAttributeFound
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
	#[DataProvider( 'dataProviderSingularize' )] // phpcs:ignore PHPCompatibility.Attributes.NewAttributes.PHPUnitAttributeFound
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
