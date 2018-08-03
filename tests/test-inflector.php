<?php

use WP_CLI\Inflector;

class InflectorTest extends PHPUnit_Framework_TestCase {

	/** @dataProvider dataProviderPluralize */
	function testPluralize( $singular, $expected ) {
		$this->assertEquals( $expected, Inflector::pluralize( $singular ) );
	}

	function dataProviderPluralize() {
		return array(
			array( 'string', 'strings' ), // regular
			array( 'person', 'people' ),  // irregular
			array( 'scissors', 'scissors' ), // uncountable
		);
	}

	/** @dataProvider dataProviderSingularize */
	function testSingularize( $singular, $expected ) {
		$this->assertEquals( $expected, Inflector::singularize( $singular ) );
	}

	function dataProviderSingularize() {
		return array(
			array( 'strings', 'string' ), // regular
			array( 'people', 'person' ),  // irregular
			array( 'scissors', 'scissors' ), // uncountable
		);
	}
}
