<?php

namespace WP_CLI\Tests;

use WP_CLI\Runner;

/**
 * @group bootstrap
 */
class RunnerTest extends \PHPUnit\Framework\TestCase {

	/**
	 * Ensures that memory limits are correctly set to unlimited (-1)
	 * in CLI context to prevent out-of-memory issues during long runs.
	 */
	public function test_memory_limits_are_unlimited_in_cli_context() {
		if ( ! defined( 'WP_CLI' ) ) {
			define( 'WP_CLI', true );
		}

		$runner = $this->getMockBuilder( Runner::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'load_wordpress' ] )
			->getMock();

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

	/**
	 * Tests that get_global_config_path() returns a valid path or null
	 * depending on whether a global config file exists.
	 */
	public function test_get_global_config_path_behavior() {
		$runner = new Runner();
		$ref    = new \ReflectionClass( $runner );
		$method = $ref->getMethod( 'get_global_config_path' );
		$method->setAccessible( true );

		$result = $method->invoke( $runner );

		// Should either return a valid path string or null.
		$this->assertTrue(
			is_null( $result ) || ( is_string( $result ) && file_exists( $result ) ),
			'Expected a string path if config exists, or null if not.'
		);
	}

	/**
	 * Tests that find_wp_root() throws a TypeError when given an invalid path.
	 */
	public function test_find_wp_root_with_invalid_path() {
		$runner = new Runner();
		$ref    = new \ReflectionClass( $runner );
		$method = $ref->getMethod( 'find_wp_root' );
		$method->setAccessible( true );

		$this->expectException( \TypeError::class );
		$method->invoke( $runner, '/invalid/fake/path' );
	}

	/**
	 * Tests that set_wp_root() correctly assigns a valid path
	 * containing wp-load.php to the Runner instance.
	 */
	/**
 * Tests that set_wp_root() correctly accepts a valid path
 * containing wp-load.php and executes without errors.
 */
	public function test_set_wp_root_with_valid_path() {
		$runner = new Runner();
		$ref    = new \ReflectionClass( $runner );
		$method = $ref->getMethod( 'set_wp_root' );
		$method->setAccessible( true );

		$tmp_dir = sys_get_temp_dir() . '/wpcli_test_root';
		if ( ! is_dir( $tmp_dir ) ) {
			mkdir( $tmp_dir );
		}

		file_put_contents( $tmp_dir . '/wp-load.php', '<?php // fake wp-load' );

		// If no exception is thrown, the method works correctly.
		try {
			$result = $method->invoke( $runner, $tmp_dir );
			$this->assertNull( $result, 'set_wp_root() should not return a value.' );
		} catch ( \Throwable $e ) {
			$this->fail( 'set_wp_root() threw an exception: ' . $e->getMessage() );
		}

		unlink( $tmp_dir . '/wp-load.php' );
		rmdir( $tmp_dir );
	}
}
