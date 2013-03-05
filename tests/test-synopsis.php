<?php

use WP_CLI\SynopsisParser;

class SynopsisParserTest extends PHPUnit_Framework_TestCase {

	function testEmpty() {
		$r = SynopsisParser::parse( ' ' );

		$this->assertEmpty( $r );
	}

	function testPositional() {
		$r = SynopsisParser::parse( '<foo> [<bar>]' );

		$this->assertCount( 2, $r );

		$this->assertEquals( 'positional', $r[0]['type'] );
		$this->assertEquals( 'mandatory', $r[0]['flavour'] );

		$this->assertEquals( 'positional', $r[1]['type'] );
		$this->assertEquals( 'optional', $r[1]['flavour'] );
	}

	function testFlag() {
		$r = SynopsisParser::parse( '[--foo]' );

		$this->assertCount( 1, $r );
		$this->assertEquals( 'flag', $r[0]['type'] );
		$this->assertEquals( 'optional', $r[0]['flavour'] );

		// flags can't be mandatory
		$r = SynopsisParser::parse( '--foo' );

		$this->assertCount( 1, $r );
		$this->assertEquals( 'unknown', $r[0]['type'] );
	}

	function testGeneric() {
		$r = SynopsisParser::parse( '--<field>=<value> [--<field>=<value>]' );

		$this->assertCount( 2, $r );

		$this->assertEquals( 'generic', $r[0]['type'] );
		$this->assertEquals( 'mandatory', $r[0]['flavour'] );

		$this->assertEquals( 'generic', $r[1]['type'] );
		$this->assertEquals( 'optional', $r[1]['flavour'] );
	}

	function testAssoc() {
		$r = SynopsisParser::parse( '--foo=<value> [--bar=<value>]' );

		$this->assertCount( 2, $r );

		$this->assertEquals( 'assoc', $r[0]['type'] );
		$this->assertEquals( 'mandatory', $r[0]['flavour'] );

		$this->assertEquals( 'assoc', $r[1]['type'] );
		$this->assertEquals( 'optional', $r[1]['flavour'] );

		// shouldn't pass defaults to assoc parameters
		$r = SynopsisParser::parse( '--count=100' );
		$this->assertCount( 1, $r );
		$this->assertEquals( 'unknown', $r[0]['type'] );
	}

	function testRepeating() {
		$r = SynopsisParser::parse( '<positional>... [--<field>=<value>...]' );

		$this->assertCount( 2, $r );

		$this->assertEquals( 'positional', $r[0]['type'] );
		$this->assertEquals( 'repeating', $r[0]['flavour'] );

		$this->assertEquals( 'generic', $r[1]['type'] );
		$this->assertEquals( 'repeating', $r[1]['flavour'] );
	}

	function testCombined() {
		$r = SynopsisParser::parse( '<positional> --assoc=<someval> --<field>=<value> [--flag]' );

		$this->assertEquals( 'positional', $r[0]['type'] );
		$this->assertEquals( 'assoc', $r[1]['type'] );
		$this->assertEquals( 'generic', $r[2]['type'] );
		$this->assertEquals( 'flag', $r[3]['type'] );
	}
}

