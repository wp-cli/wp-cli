<?php

use WP_CLI\SynopsisParser;

class ArgValidationTests extends PHPUnit_Framework_TestCase {

	function testMissingPositional() {
		$parser = new SynopsisParser( '<foo> <bar> [<baz>]' );

		$this->assertFalse( $parser->enough_positionals( array() ) );
		$this->assertTrue( $parser->enough_positionals( array( 1, 2 ) ) );
		$this->assertTrue( $parser->enough_positionals( array( 1, 2, 3, 4 ) ) );
	}

	function testRepeatingPositional() {
		$parser = new SynopsisParser( '<foo> [<bar>...]' );

		$this->assertFalse( $parser->enough_positionals( array() ) );
		$this->assertTrue( $parser->enough_positionals( array( 1 ) ) );
		$this->assertTrue( $parser->enough_positionals( array( 1, 2, 3 ) ) );
	}

	function testUnknownAssocEmpty() {
		$parser = new SynopsisParser( '' );

		$assoc_args = array( 'foo' => true, 'bar' => false );
		$this->assertEquals( array_keys( $assoc_args ), $parser->unknown_assoc( $assoc_args ) );
	}

	function testUnknownAssoc() {
		$parser = new SynopsisParser( '--type=<type> [--brand=<brand>] [--flag]' );

		$assoc_args = array( 'type' => 'analog', 'brand' => true, 'flag' => true );
		$this->assertEmpty( $parser->unknown_assoc( $assoc_args ) );

		$assoc_args['another'] = true;
		$this->assertContains( 'another', $parser->unknown_assoc( $assoc_args ) );
	}

	function testMissingAssoc() {
		$parser = new SynopsisParser( '--type=<type> [--brand=<brand>] [--flag]' );

		$assoc_args = array( 'brand' => true, 'flag' => true );
		$errors = $parser->validate_assoc( $assoc_args );

		$this->assertCount( 1, $errors['fatal'] );
		$this->assertCount( 1, $errors['warning'] );
	}
}

