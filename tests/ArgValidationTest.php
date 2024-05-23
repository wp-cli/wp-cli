<?php

use WP_CLI\SynopsisValidator;
use WP_CLI\Tests\TestCase;

class ArgValidationTest extends TestCase {

	public function testMissingPositional() {
		$validator = new SynopsisValidator( '<foo> <bar> [<baz>]' );

		$this->assertFalse( $validator->enough_positionals( [] ) );
		$this->assertTrue( $validator->enough_positionals( [ 1, 2 ] ) );
		$this->assertTrue( $validator->enough_positionals( [ 1, 2, 3, 4 ] ) );

		$this->assertEquals( [ 4 ], $validator->unknown_positionals( [ 1, 2, 3, 4 ] ) );
	}

	public function testRepeatingPositional() {
		$validator = new SynopsisValidator( '<foo> [<bar>...]' );

		$this->assertFalse( $validator->enough_positionals( [] ) );
		$this->assertTrue( $validator->enough_positionals( [ 1 ] ) );
		$this->assertTrue( $validator->enough_positionals( [ 1, 2, 3 ] ) );

		$this->assertEmpty( $validator->unknown_positionals( [ 1, 2, 3 ] ) );
	}

	public function testUnknownAssocEmpty() {
		$validator = new SynopsisValidator( '' );

		$assoc_args = [
			'foo' => true,
			'bar' => false,
		];
		$this->assertEquals( array_keys( $assoc_args ), $validator->unknown_assoc( $assoc_args ) );
	}

	public function testUnknownAssoc() {
		$validator = new SynopsisValidator( '--type=<type> [--brand=<brand>] [--flag]' );

		$assoc_args = [
			'type'  => 'analog',
			'brand' => true,
			'flag'  => true,
		];
		$this->assertEmpty( $validator->unknown_assoc( $assoc_args ) );

		$assoc_args['another'] = true;
		$this->assertContains( 'another', $validator->unknown_assoc( $assoc_args ) );
	}

	public function testMissingAssoc() {
		$validator = new SynopsisValidator( '--type=<type> [--brand=<brand>] [--flag]' );

		$assoc_args                = [
			'brand' => true,
			'flag'  => true,
		];
		list( $errors, $to_unset ) = $validator->validate_assoc( $assoc_args );

		$this->assertCount( 1, $errors['fatal'] );
		$this->assertCount( 1, $errors['warning'] );
	}

	public function testAssocWithOptionalValue() {
		$validator = new SynopsisValidator( '[--network[=<id>]]' );

		$assoc_args                = [ 'network' => true ];
		list( $errors, $to_unset ) = $validator->validate_assoc( $assoc_args );

		$this->assertCount( 0, $errors['fatal'] );
		$this->assertCount( 0, $errors['warning'] );
	}
}
