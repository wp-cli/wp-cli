<?php

use WP_CLI\DocParser;
use WP_CLI\Tests\TestCase;

class ArgAliasTest extends TestCase {

	public function test_get_arg_aliases_single_alias(): void {
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

	public function test_get_arg_aliases_multiple_aliases(): void {
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

	public function test_get_arg_aliases_multiple_params(): void {
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

	public function test_get_arg_aliases_no_aliases(): void {
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

	public function test_get_arg_aliases_with_dashes(): void {
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

	public function test_get_arg_aliases_with_required_param(): void {
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

	public function test_get_arg_aliases_with_leading_dashes(): void {
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

	public function test_get_arg_aliases_yaml_boolean_handling(): void {
		// Test that YAML boolean values are handled correctly
		// YAML 1.1 interprets 'n' as false and 'y' as true, but we convert them back
		$doc     = <<<'EOD'
/**
 * Test command
 *
 * ## OPTIONS
 *
 * [--number=<number>]
 * : Number parameter.
 * ---
 * alias: n
 * ---
 *
 * [--answer=<answer>]
 * : Answer parameter.
 * ---
 * alias: y
 * ---
 */
EOD;
		$parser  = new DocParser( $doc );
		$aliases = $parser->get_arg_aliases();

		// YAML interprets 'n' as false and 'y' as true, but we convert them back
		$this->assertEquals(
			[
				'n' => 'number',
				'y' => 'answer',
			],
			$aliases
		);
	}
}
