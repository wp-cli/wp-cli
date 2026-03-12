<?php

use WP_CLI\SynopsisParser;
use WP_CLI\Tests\TestCase;

class ArgAliasTest extends TestCase {

	public function test_synopsis_parser_extracts_single_alias(): void {
		$params = SynopsisParser::parse( '[--with-dependencies|w]' );

		$this->assertCount( 1, $params );
		$param = $params[0];
		$this->assertEquals( 'flag', $param['type'] );
		$this->assertEquals( 'with-dependencies', $param['name'] );
		$this->assertEquals( [ 'w' ], $param['aliases'] );
		$this->assertTrue( $param['optional'] );
	}

	public function test_synopsis_parser_extracts_multiple_aliases(): void {
		$params = SynopsisParser::parse( '[--verbose|v|wordy|deprecated-name]' );

		$this->assertCount( 1, $params );
		$param = $params[0];
		$this->assertEquals( 'flag', $param['type'] );
		$this->assertEquals( 'verbose', $param['name'] );
		$this->assertEquals( [ 'v', 'wordy', 'deprecated-name' ], $param['aliases'] );
	}

	public function test_synopsis_parser_extracts_aliases_from_assoc_param(): void {
		$params = SynopsisParser::parse( '[--number=<number>|n]' );

		$this->assertCount( 1, $params );
		$param = $params[0];
		$this->assertEquals( 'assoc', $param['type'] );
		$this->assertEquals( 'number', $param['name'] );
		$this->assertEquals( [ 'n' ], $param['aliases'] );
		$this->assertTrue( $param['optional'] );
	}

	public function test_synopsis_parser_no_aliases_when_absent(): void {
		$params = SynopsisParser::parse( '[--verbose]' );

		$this->assertCount( 1, $params );
		$param = $params[0];
		$this->assertEquals( 'flag', $param['type'] );
		$this->assertEquals( 'verbose', $param['name'] );
		$this->assertArrayNotHasKey( 'aliases', $param );
	}

	public function test_synopsis_parser_ignores_pipe_inside_value_brackets(): void {
		// The | inside <a|b> should NOT be treated as an alias separator
		$params = SynopsisParser::parse( '<plugin|zip>' );

		$this->assertCount( 1, $params );
		$param = $params[0];
		$this->assertEquals( 'positional', $param['type'] );
		$this->assertArrayNotHasKey( 'aliases', $param );
	}

	public function test_synopsis_parser_assoc_alias_with_pipe_in_value(): void {
		// Pipe inside <...> is ignored; alias is only extracted from outside
		$params = SynopsisParser::parse( '[--type=<type>|t]' );

		$this->assertCount( 1, $params );
		$param = $params[0];
		$this->assertEquals( 'assoc', $param['type'] );
		$this->assertEquals( 'type', $param['name'] );
		$this->assertEquals( [ 't' ], $param['aliases'] );
	}

	public function test_synopsis_parser_render_includes_aliases_for_flag(): void {
		$synopsis = [
			[
				'type'      => 'flag',
				'name'      => 'verbose',
				'aliases'   => [ 'v', 'wordy' ],
				'optional'  => true,
				'repeating' => false,
			],
		];

		$rendered = SynopsisParser::render( $synopsis );
		$this->assertEquals( '[--verbose|v|wordy]', $rendered );
	}

	public function test_synopsis_parser_render_includes_aliases_for_assoc(): void {
		$synopsis = [
			[
				'type'      => 'assoc',
				'name'      => 'number',
				'aliases'   => [ 'n' ],
				'optional'  => true,
				'repeating' => false,
				'value'     => [
					'optional' => false,
					'name'     => 'number',
				],
			],
		];

		$rendered = SynopsisParser::render( $synopsis );
		$this->assertEquals( '[--number=<number>|n]', $rendered );
	}

	public function test_synopsis_roundtrip_with_aliases(): void {
		$synopsis = '[--number=<number>|n] [--with-dependencies|w] [--verbose|v|wordy]';
		$parsed   = SynopsisParser::parse( $synopsis );
		$rendered = SynopsisParser::render( $parsed );
		$this->assertEquals( $synopsis, $rendered );
	}
}
