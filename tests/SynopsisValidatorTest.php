<?php

use WP_CLI\SynopsisValidator;
use WP_CLI\Tests\TestCase;

class SynopsisValidatorTest extends TestCase {

	public function testHasGenericIsFalseWithoutGenericToken(): void {
		$validator = new SynopsisValidator( '<id> [--format=<format>] [--porcelain]' );

		$this->assertFalse( $validator->has_generic() );
	}

	public function testHasGenericIsTrueWithGenericToken(): void {
		$validator = new SynopsisValidator( '<id> [--format=<format>] --<field>=<value>' );

		$this->assertTrue( $validator->has_generic() );
	}

	public function testUnknownAssocWithoutGenericToken(): void {
		$validator = new SynopsisValidator( '<id> [--user_pass=<password>] [--skip-email]' );

		$this->assertSame(
			[ 'user-pass' ],
			$validator->unknown_assoc(
				[
					'user_pass' => 'a',
					'user-pass' => 'b',
				]
			)
		);
	}

	/**
	 * A generic token means arbitrary keys are legitimate, so nothing is
	 * reported unless the caller opts in.
	 */
	public function testUnknownAssocIsEmptyWithGenericToken(): void {
		$validator = new SynopsisValidator( '<id> [--user_pass=<password>] --<field>=<value>' );

		$this->assertSame(
			[],
			$validator->unknown_assoc( [ 'user-pass' => 'b' ] )
		);
	}

	/**
	 * With $ignore_generic, the caller gets the full set and decides which of
	 * them are actually errors.
	 */
	public function testUnknownAssocReportsWithIgnoreGeneric(): void {
		$validator = new SynopsisValidator( '<id> [--user_pass=<password>] --<field>=<value>' );

		$this->assertSame(
			[ 'user-pass', 'acme_crm_id' ],
			$validator->unknown_assoc(
				[
					'user_pass'   => 'a',
					'user-pass'   => 'b',
					'acme_crm_id' => 'c',
				],
				true
			)
		);
	}

	public function testUnknownAssocIgnoreGenericStillExcludesFlags(): void {
		$validator = new SynopsisValidator( '<id> [--skip-email] --<field>=<value>' );

		$this->assertSame(
			[],
			$validator->unknown_assoc( [ 'skip-email' => true ], true )
		);
	}
}
