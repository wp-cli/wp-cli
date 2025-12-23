<?php

use WP_CLI\DocParser;
use WP_CLI\Tests\TestCase;

class ArgAliasTest extends TestCase {

	public function testGetArgAliasesSingleAlias(): void {
		$doc     = <<<'EOD'
/**
 * Test command
 *
 * ## OPTIONS
 *
 * [--format=<format>]
 * : Output format.
 * ---
 * alias: f
 * ---
 */
EOD;
		$parser  = new DocParser( $doc );
		$aliases = $parser->get_arg_aliases();

		$this->assertEquals( [ 'f' => 'format' ], $aliases );
	}

	public function testGetArgAliasesMultipleAliases(): void {
		$doc     = <<<'EOD'
/**
 * Test command
 *
 * ## OPTIONS
 *
 * [--verbose]
 * : Enable verbose output.
 * ---
 * alias:
 *   - v
 *   - debug
 * ---
 */
EOD;
		$parser  = new DocParser( $doc );
		$aliases = $parser->get_arg_aliases();

		$this->assertEquals(
			[
				'v'     => 'verbose',
				'debug' => 'verbose',
			],
			$aliases
		);
	}

	public function testGetArgAliasesMultipleParams(): void {
		$doc     = <<<'EOD'
/**
 * Test command
 *
 * ## OPTIONS
 *
 * [--format=<format>]
 * : Output format.
 * ---
 * alias: f
 * ---
 *
 * [--verbose]
 * : Enable verbose output.
 * ---
 * alias: v
 * ---
 */
EOD;
		$parser  = new DocParser( $doc );
		$aliases = $parser->get_arg_aliases();

		$this->assertEquals(
			[
				'f' => 'format',
				'v' => 'verbose',
			],
			$aliases
		);
	}

	public function testGetArgAliasesNoAliases(): void {
		$doc     = <<<'EOD'
/**
 * Test command
 *
 * ## OPTIONS
 *
 * [--format=<format>]
 * : Output format.
 */
EOD;
		$parser  = new DocParser( $doc );
		$aliases = $parser->get_arg_aliases();

		$this->assertEquals( [], $aliases );
	}

	public function testGetArgAliasesWithDashes(): void {
		$doc     = <<<'EOD'
/**
 * Test command
 *
 * ## OPTIONS
 *
 * [--with-dependencies]
 * : Include dependencies.
 * ---
 * alias: w
 * ---
 */
EOD;
		$parser  = new DocParser( $doc );
		$aliases = $parser->get_arg_aliases();

		$this->assertEquals( [ 'w' => 'with-dependencies' ], $aliases );
	}

	public function testGetArgAliasesWithRequiredParam(): void {
		$doc     = <<<'EOD'
/**
 * Test command
 *
 * ## OPTIONS
 *
 * --type=<type>
 * : Required type parameter.
 * ---
 * alias: t
 * ---
 */
EOD;
		$parser  = new DocParser( $doc );
		$aliases = $parser->get_arg_aliases();

		$this->assertEquals( [ 't' => 'type' ], $aliases );
	}

	public function testGetArgAliasesWithLeadingDashes(): void {
		$doc     = <<<'EOD'
/**
 * Test command
 *
 * ## OPTIONS
 *
 * [--format=<format>]
 * : Output format.
 * ---
 * alias: -f
 * ---
 */
EOD;
		$parser  = new DocParser( $doc );
		$aliases = $parser->get_arg_aliases();

		// Leading dashes should be stripped
		$this->assertEquals( [ 'f' => 'format' ], $aliases );
	}
}
