<?php

use WP_CLI\SynopsisValidator;
use WP_CLI\Tests\TestCase;

class ArgValidationTest extends TestCase {

	public function testMissingPositional(): void {
		$validator = new SynopsisValidator( '<foo> <bar> [<baz>]' );

		$this->assertFalse( $validator->enough_positionals( [] ) );
		$this->assertTrue( $validator->enough_positionals( [ 1, 2 ] ) );
		$this->assertTrue( $validator->enough_positionals( [ 1, 2, 3, 4 ] ) );

		$this->assertEquals( [ 4 ], $validator->unknown_positionals( [ 1, 2, 3, 4 ] ) );
	}

	public function testRepeatingPositional(): void {
		$validator = new SynopsisValidator( '<foo> [<bar>...]' );

		$this->assertFalse( $validator->enough_positionals( [] ) );
		$this->assertTrue( $validator->enough_positionals( [ 1 ] ) );
		$this->assertTrue( $validator->enough_positionals( [ 1, 2, 3 ] ) );

		$this->assertEmpty( $validator->unknown_positionals( [ 1, 2, 3 ] ) );
	}

	public function testUnknownAssocEmpty(): void {
		$validator = new SynopsisValidator( '' );

		$assoc_args = [
			'foo' => true,
			'bar' => false,
		];
		$this->assertEquals( array_keys( $assoc_args ), $validator->unknown_assoc( $assoc_args ) );
	}

	public function testUnknownAssoc(): void {
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

	public function testMissingAssoc(): void {
		$validator = new SynopsisValidator( '--type=<type> [--brand=<brand>] [--flag]' );

		$assoc_args                = [
			'brand' => true,
			'flag'  => true,
		];
		list( $errors, $to_unset ) = $validator->validate_assoc( $assoc_args );

		$this->assertCount( 1, $errors['fatal'] );
		$this->assertCount( 1, $errors['warning'] );
	}

	public function testAssocWithOptionalValue(): void {
		$validator = new SynopsisValidator( '[--network[=<id>]]' );

		$assoc_args                = [ 'network' => true ];
		list( $errors, $to_unset ) = $validator->validate_assoc( $assoc_args );

		$this->assertCount( 0, $errors['fatal'] );
		$this->assertCount( 0, $errors['warning'] );
	}

	public function testGetUnknownWithSpacesInSynopsis(): void {
		// Synopsis with spaces in value parameters should be detected as unknown
		$validator = new SynopsisValidator( '[--user_registered=<yyyy-mm-dd hh:ii:ss>]' );

		$unknown = $validator->get_unknown();

		// Should detect both parts of the broken synopsis
		$this->assertCount( 2, $unknown );
		$this->assertContains( '[--user_registered=<yyyy-mm-dd', $unknown );
		$this->assertContains( 'hh:ii:ss>]', $unknown );
	}

	public function testGetUnknownWithValidSynopsis(): void {
		// Valid synopsis should have no unknown tokens
		$validator = new SynopsisValidator( '[--user_registered=<yyyy-mm-dd-hh-ii-ss>]' );

		$unknown = $validator->get_unknown();

		$this->assertCount( 0, $unknown );
	}

	public function testGetUnknownWithMultipleInvalidTokens(): void {
		// Test with a mix of valid and invalid tokens
		$validator = new SynopsisValidator( '--valid=<value> [--invalid=<value with spaces>] [--another-valid]' );

		$unknown = $validator->get_unknown();

		// Should detect the two parts of the broken synopsis
		$this->assertCount( 2, $unknown );
		$this->assertContains( '[--invalid=<value', $unknown );
		$this->assertContains( 'spaces>]', $unknown );
	}
}
