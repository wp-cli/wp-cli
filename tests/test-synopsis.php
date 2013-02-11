<?php

use WP_CLI\SynopsisParser;

class SynopsisParserTest extends PHPUnit_Framework_TestCase {

	function testPositional() {
		$r = SynopsisParser::parse( '<foo> [<bar>]' );

		$this->assertFoundParameters( 2, 'positional', $r );
		$this->assertFalse( $r['positional'][0]['optional'] );
		$this->assertTrue( $r['positional'][1]['optional'] );
	}

	function testFlag() {
		$r = SynopsisParser::parse( '--foo' );
		$this->assertFoundParameters( 0, 'flag', $r ); // flags can't be mandatory

		$r = SynopsisParser::parse( '[--foo]' );
		$this->assertFoundParameters( 1, 'flag', $r );
	}

	function testGeneric() {
		$r = SynopsisParser::parse( '--<field>=<value>' );

		$this->assertFoundParameters( 1, 'generic', $r );
	}

	function testAssoc() {
		$r = SynopsisParser::parse( '--foo=<value> [--bar=<value>]' );

		$this->assertFoundParameters( 2, 'assoc', $r );
		$this->assertFalse( $r['assoc'][0]['optional'] );
		$this->assertTrue( $r['assoc'][1]['optional'] );
	}

	protected function assertFoundParameters( $count, $type, $r ) {
		foreach ( $r as $key => $params ) {
			$expected = ( $key == $type ) ? $count : 0;

			$this->assertEquals( $expected, count( $params ) );
		}
	}
}

