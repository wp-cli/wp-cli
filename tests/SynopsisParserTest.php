<?php

use WP_CLI\SynopsisParser;
use WP_CLI\Tests\TestCase;

class SynopsisParserTest extends TestCase {

	public function testEmpty(): void {
		$r = SynopsisParser::parse( ' ' );

		$this->assertEmpty( $r );
	}

	public function testPositional(): void {
		$r = SynopsisParser::parse( '<plugin|zip> [<bar>]' );

		$this->assertCount( 2, $r );

		$param = $r[0];
		$this->assertEquals( 'positional', $param['type'] );
		$this->assertFalse( $param['optional'] );

		$param = $r[1];
		$this->assertEquals( 'positional', $param['type'] );
		$this->assertTrue( $param['optional'] );
	}

	public function testFlag(): void {
		$r = SynopsisParser::parse( '[--foo]' );

		$this->assertCount( 1, $r );

		$param = $r[0];
		$this->assertEquals( 'flag', $param['type'] );
		$this->assertTrue( $param['optional'] );

		// Flags can't be mandatory.
		$r = SynopsisParser::parse( '--foo' );

		$this->assertCount( 1, $r );

		$param = $r[0];
		$this->assertEquals( 'unknown', $param['type'] );
	}

	public function testGeneric(): void {
		$r = SynopsisParser::parse( '--<field>=<value> [--<field>=<value>] --<field>[=<value>] [--<field>[=<value>]]' );

		$this->assertCount( 4, $r );

		$param = $r[0];
		$this->assertEquals( 'generic', $param['type'] );
		$this->assertFalse( $param['optional'] );

		$param = $r[1];
		$this->assertEquals( 'generic', $param['type'] );
		$this->assertTrue( $param['optional'] );

		$param = $r[2];
		$this->assertEquals( 'unknown', $param['type'] );

		$param = $r[3];
		$this->assertEquals( 'unknown', $param['type'] );
	}

	public function testAssoc(): void {
		$r = SynopsisParser::parse( '--foo=<value> [--bar=<value>] [--bar[=<value>]]' );

		$this->assertCount( 3, $r );

		$param = $r[0];
		$this->assertEquals( 'assoc', $param['type'] );
		$this->assertFalse( $param['optional'] );

		$param = $r[1];
		$this->assertEquals( 'assoc', $param['type'] );
		$this->assertTrue( $param['optional'] );

		$param = $r[2];
		$this->assertEquals( 'assoc', $param['type'] );
		$this->assertTrue( $param['optional'] );
		$this->assertTrue( $param['value']['optional'] );
	}

	public function testInvalidAssoc(): void {
		$r = SynopsisParser::parse( '--bar[=<value>] --bar=[<value>] --count=100' );

		$this->assertCount( 3, $r );

		$this->assertEquals( 'unknown', $r[0]['type'] );
		$this->assertEquals( 'unknown', $r[1]['type'] );
		$this->assertEquals( 'unknown', $r[2]['type'] );
	}

	public function testRepeating(): void {
		$r = SynopsisParser::parse( '<positional>... [--<field>=<value>...]' );

		$this->assertCount( 2, $r );

		$param = $r[0];
		$this->assertEquals( 'positional', $param['type'] );
		$this->assertTrue( $param['repeating'] );

		$param = $r[1];
		$this->assertEquals( 'generic', $param['type'] );
		$this->assertTrue( $param['repeating'] );
	}

	public function testCombined(): void {
		$r = SynopsisParser::parse( '<positional> --assoc=<someval> --<field>=<value> [--flag]' );

		$this->assertCount( 4, $r );

		$this->assertEquals( 'positional', $r[0]['type'] );
		$this->assertEquals( 'assoc', $r[1]['type'] );
		$this->assertEquals( 'generic', $r[2]['type'] );
		$this->assertEquals( 'flag', $r[3]['type'] );
	}

	public function testAllowedValueCharacters(): void {
		$r = SynopsisParser::parse( '--capitals=<VALUE> --hyphen=<val-ue> --combined=<VAL-ue> --disallowed=<wrong:char>' );

		$this->assertCount( 4, $r );

		$param = $r[0];
		$this->assertEquals( 'assoc', $param['type'] );
		$this->assertFalse( $param['optional'] );

		$param = $r[1];
		$this->assertEquals( 'assoc', $param['type'] );
		$this->assertFalse( $param['optional'] );

		$param = $r[2];
		$this->assertEquals( 'assoc', $param['type'] );
		$this->assertFalse( $param['optional'] );

		$this->assertEquals( 'unknown', $r[3]['type'] );
	}

	public function testRender(): void {
		$a = [
			[
				'name'        => 'message',
				'type'        => 'positional',
				'description' => 'A short message to display to the user.',
			],
			[
				'name'        => 'secrets',
				'type'        => 'positional',
				'description' => 'You may tell secrets, or you may not',
				'optional'    => true,
				'repeating'   => true,
			],
			[
				'name'        => 'meal',
				'type'        => 'assoc',
				'description' => 'A meal during the day or night.',
			],
			[
				'name'        => 'snack',
				'type'        => 'assoc',
				'description' => 'If you are hungry between meals, you should snack.',
				'optional'    => true,
			],
			[
				'name'        => 'skip',
				'type'        => 'assoc',
				'description' => 'Skip all meals, or skip a single meal by name.',
				'optional'    => true,
				'value'       => [
					'optional' => true,
				],
			],
		];
		$this->assertEquals( '<message> [<secrets>...] --meal=<meal> [--snack=<snack>] [--skip[=<skip>]]', SynopsisParser::render( $a ) );
	}

	public function testParseThenRender(): void {
		$o = '<positional> --assoc=<assoc> [--double[=<optional>]] --<field>=<value> [--flag]';
		$a = SynopsisParser::parse( $o );
		$r = SynopsisParser::render( $a );
		$this->assertEquals( $o, $r );
	}

	public function testParseThenRenderNumeric(): void {
		$o = '<p1ositional> --a2ssoc=<assoc> --<field>=<value> [--f3lag]';
		$a = SynopsisParser::parse( $o );
		$this->assertEquals( 'p1ositional', $a[0]['name'] );
		$this->assertEquals( 'a2ssoc', $a[1]['name'] );
		$this->assertEquals( 'f3lag', $a[3]['name'] );
		$r = SynopsisParser::render( $a );
		$this->assertEquals( $o, $r );
	}

	public function testSpacesInValueParameterAreInvalid(): void {
		// Spaces in value parameters should be detected as invalid
		// This prevents issues like https://github.com/wp-cli/entity-command/pull/43
		$r = SynopsisParser::parse( '[--user_registered=<yyyy-mm-dd hh:ii:ss>]' );

		// When there are spaces, the synopsis is split into multiple tokens
		// and marked as 'unknown' type
		$this->assertCount( 2, $r );
		$this->assertEquals( 'unknown', $r[0]['type'] );
		$this->assertEquals( 'unknown', $r[1]['type'] );
	}

	public function testSpacesInMandatoryAssocAreInvalid(): void {
		// Test with mandatory assoc parameter with spaces
		$r = SynopsisParser::parse( '--date=<yyyy-mm-dd hh:ii:ss>' );

		$this->assertCount( 2, $r );
		$this->assertEquals( 'unknown', $r[0]['type'] );
		$this->assertEquals( 'unknown', $r[1]['type'] );
	}

	public function testNoSpacesInValueParameterIsValid(): void {
		// Verify that the correct format (without spaces) works
		$r = SynopsisParser::parse( '[--user_registered=<yyyy-mm-dd-hh-ii-ss>]' );

		$this->assertCount( 1, $r );
		$this->assertEquals( 'assoc', $r[0]['type'] );
		$this->assertEquals( 'user_registered', $r[0]['name'] );
		$this->assertTrue( $r[0]['optional'] );
	}
}
