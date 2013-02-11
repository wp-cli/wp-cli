<?php

use WP_CLI\SynopsisParser;

class SynopsisParserTest extends PHPUnit_Framework_TestCase {

	function testEmpty() {
		$r = SynopsisParser::parse( ' ' );

		$this->assertFoundParameters( 0, 'positional', $r );
	}

	function testPositional() {
		$r = SynopsisParser::parse( '<foo> [<bar>]' );

		$this->assertFoundParameters( 2, 'positional', $r );
		$this->assertFalse( $r['positional'][0]['optional'] );
		$this->assertTrue( $r['positional'][1]['optional'] );
	}

	function testFlag() {
		$r = SynopsisParser::parse( '[--foo]' );
		$this->assertFoundParameters( 1, 'flag', $r );

		// flags can't be mandatory
		$r = SynopsisParser::parse( '--foo' );
		$this->assertFoundParameters( 1, 'unknown', $r );
	}

	function testGeneric() {
		$r = SynopsisParser::parse( '--<field>=<value> [--<field>=<value>]' );

		$this->assertFoundParameters( 2, 'generic', $r );
		$this->assertFalse( $r['generic'][0]['optional'] );
		$this->assertTrue( $r['generic'][1]['optional'] );
	}

	function testAssoc() {
		$r = SynopsisParser::parse( '--foo=<value> [--bar=<value>]' );

		$this->assertFoundParameters( 2, 'assoc', $r );
		$this->assertFalse( $r['assoc'][0]['optional'] );
		$this->assertTrue( $r['assoc'][1]['optional'] );

		// shouldn't pass defaults to assoc parameters
		$r = SynopsisParser::parse( '--count=100' );
		$this->assertFoundParameters( 1, 'unknown', $r );
	}

	function testCombined() {
		$r = SynopsisParser::parse( '<positional> --assoc=<someval> --<field>=<value> [--flag]' );

		$this->assertCount( 1, $r['positional'] );
		$this->assertCount( 1, $r['assoc'] );
		$this->assertCount( 1, $r['generic'] );
		$this->assertCount( 1, $r['flag'] );
	}

	protected function assertFoundParameters( $count, $type, $r ) {
		foreach ( $r as $key => $params ) {
			$expected = ( $key == $type ) ? $count : 0;

			$this->assertCount( $expected, $params );
		}
	}
}

