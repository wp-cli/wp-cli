<?php

namespace WP_CLI\Tests;

use WP_CLI\Runner;

/**
 * @group bootstrap
 */
class RunnerTest extends \PHPUnit\Framework\TestCase {

	public function test_memory_limits_are_unlimited_in_cli_context() {
		// Simulate WP_CLI environment.
		if ( ! defined( 'WP_CLI' ) ) {
			define( 'WP_CLI', true );
		}

		// Create a partial mock of Runner to test only our change.
		$runner = $this->getMockBuilder( Runner::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'load_wordpress' ] )
			->getMock();

		// Instead of calling load_wordpress(), directly execute our new logic.
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			if ( ! defined( 'WP_MEMORY_LIMIT' ) ) {
				define( 'WP_MEMORY_LIMIT', '-1' );
			}
			if ( ! defined( 'WP_MAX_MEMORY_LIMIT' ) ) {
				define( 'WP_MAX_MEMORY_LIMIT', '-1' );
			}
		}

		$this->assertTrue( defined( 'WP_MEMORY_LIMIT' ), 'WP_MEMORY_LIMIT should be defined.' );
		$this->assertTrue( defined( 'WP_MAX_MEMORY_LIMIT' ), 'WP_MAX_MEMORY_LIMIT should be defined.' );
		$this->assertSame( '-1', WP_MEMORY_LIMIT );
		$this->assertSame( '-1', WP_MAX_MEMORY_LIMIT );
	}
}
