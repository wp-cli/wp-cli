<?php

use WP_CLI\SynopsisValidator;

class ArgValidationTests extends PHPUnit_Framework_TestCase {

	function testMissingPositional() {
		$validator = new SynopsisValidator( '<foo> <bar> [<baz>]' );

		$this->assertFalse( $validator->enough_positionals( array() ) );
		$this->assertTrue( $validator->enough_positionals( array( 1, 2 ) ) );
		$this->assertTrue( $validator->enough_positionals( array( 1, 2, 3, 4 ) ) );

		$this->assertEquals( array( 4 ), $validator->unknown_positionals( array( 1, 2, 3, 4 ) ) );
	}

	function testRepeatingPositional() {
		$validator = new SynopsisValidator( '<foo> [<bar>...]' );

		$this->assertFalse( $validator->enough_positionals( array() ) );
		$this->assertTrue( $validator->enough_positionals( array( 1 ) ) );
		$this->assertTrue( $validator->enough_positionals( array( 1, 2, 3 ) ) );

		$this->assertEmpty( $validator->unknown_positionals( array( 1, 2, 3 ) ) );
	}

	function testUnknownAssocEmpty() {
		$validator = new SynopsisValidator( '' );

		$assoc_args = array( 'foo' => true, 'bar' => false );
		$this->assertEquals( array_keys( $assoc_args ), $validator->unknown_assoc( $assoc_args ) );
	}

	function testUnknownAssoc() {
		$validator = new SynopsisValidator( '--type=<type> [--brand=<brand>] [--flag]' );

		$assoc_args = array( 'type' => 'analog', 'brand' => true, 'flag' => true );
		$this->assertEmpty( $validator->unknown_assoc( $assoc_args ) );

		$assoc_args['another'] = true;
		$this->assertContains( 'another', $validator->unknown_assoc( $assoc_args ) );
	}

	function testMissingAssoc() {
		$validator = new SynopsisValidator( '--type=<type> [--brand=<brand>] [--flag]' );

		$assoc_args = array( 'brand' => true, 'flag' => true );
		list( $errors, $to_unset ) = $validator->validate_assoc( $assoc_args );

		$this->assertCount( 1, $errors['fatal'] );
		$this->assertCount( 1, $errors['warning'] );
	}

	function testAssocWithOptionalValue() {
		$validator = new SynopsisValidator( '[--network[=<id>]]' );

		$assoc_args = array( 'network' => true );
		list( $errors, $to_unset ) = $validator->validate_assoc( $assoc_args );

		$this->assertCount( 0, $errors['fatal'] );
		$this->assertCount( 0, $errors['warning'] );
	}
}

