<?php

namespace WP_CLI\Tests;

use ReflectionClass;
use WP_CLI\CommandHints;

/**
 * Tests for the WP_CLI\CommandHints class.
 */
final class CommandHintsTest extends TestCase {

	/**
	 * Collect the hints declared within a fixture directory.
	 *
	 * @param string $fixture Name of the directory within `tests/data`.
	 * @return array<string, string> Map of full command name to hint.
	 */
	private function get_hints_from_fixture( string $fixture ): array {
		$class  = new ReflectionClass( CommandHints::class );
		$method = $class->getMethod( 'get_hints_from_vendor_dir' );
		if ( PHP_VERSION_ID < 80100 ) {
			// @phpstan-ignore method.deprecated
			$method->setAccessible( true );
		}

		/**
		 * @var array<string, string> $hints
		 */
		$hints = $method->invoke( null, __DIR__ . '/data/' . $fixture . '/vendor' );

		return $hints;
	}

	public function testItCollectsHintsFromInstalledPackages(): void {
		$hints = $this->get_hints_from_fixture( 'command-hints' );

		$this->assertSame( "The 'package' command ships with the Phar only.", $hints['package'] );
		$this->assertSame( "The 'acme sync' command needs the acme/sync-command package.", $hints['acme sync'] );
	}

	public function testItCollectsHintsFromTheRootPackage(): void {
		$hints = $this->get_hints_from_fixture( 'command-hints' );

		$this->assertSame( "The 'root-only' command is declared by the root package.", $hints['root-only'] );
	}

	public function testTheRootPackageTakesPrecedence(): void {
		$hints = $this->get_hints_from_fixture( 'command-hints' );

		$this->assertSame( 'Hint coming from the root package.', $hints['overridden'] );
	}

	public function testItIgnoresIncompleteHints(): void {
		$hints = $this->get_hints_from_fixture( 'command-hints' );

		$this->assertArrayNotHasKey( '', $hints );
		$this->assertArrayNotHasKey( 'empty-hint', $hints );
		$this->assertArrayNotHasKey( 'array-hint', $hints );
	}

	public function testItHandlesAMissingVendorDirectory(): void {
		$this->assertSame( [], $this->get_hints_from_fixture( 'command-hints-does-not-exist' ) );
	}
}
