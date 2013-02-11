<?php

use WP_CLI\SynopsisParser;

class SynopsisParserTest extends PHPUnit_Framework_TestCase {

	function testPositional() {
		$r = SynopsisParser::parse( '<foo> [<bar>]' );

		$this->assertEquals( 2, count( $r['positional'] ) );
		$this->assertFalse( $r['positional'][0]['optional'] );
		$this->assertTrue( $r['positional'][1]['optional'] );
	}

	function testFlag() {
		$r = SynopsisParser::parse( '--foo' );
		$this->assertEquals( 0, count( $r['flag'] ) ); // flags can't be mandatory

		$r = SynopsisParser::parse( '[--foo]' );
		$this->assertEquals( 1, count( $r['flag'] ) );
	}

	function testGeneric() {
		$r = SynopsisParser::parse( '--<field>=<value>' );

		$this->assertEquals( 1, count( $r['generic'] ) );
	}

	function testAssoc() {
		$r = SynopsisParser::parse( '--foo=<value> [--bar=<value>]' );

		$this->assertEquals( 2, count( $r['assoc'] ) );
		$this->assertFalse( $r['assoc'][0]['optional'] );
		$this->assertTrue( $r['assoc'][1]['optional'] );
	}
}

