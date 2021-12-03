<?php

use WP_CLI\FileCache;
use WP_CLI\Loggers;
use WP_CLI\Tests\TestCase;
use WP_CLI\Utils;

require_once dirname( __DIR__ ) . '/php/class-wp-cli.php';

class FileCacheTest extends TestCase {

	/**
	 * Test get_root() deals with backslashed directory.
	 */
	public function testGetRoot() {
		$max_size = 32;
		$ttl      = 60;

		$cache_dir = Utils\get_temp_dir() . uniqid( 'wp-cli-test-file-cache', true );

		$cache = new FileCache( $cache_dir, $ttl, $max_size );
		$this->assertSame( $cache_dir . '/', $cache->get_root() );
		unset( $cache );

		$cache = new FileCache( $cache_dir . '/', $ttl, $max_size );
		$this->assertSame( $cache_dir . '/', $cache->get_root() );
		unset( $cache );

		$cache = new FileCache( $cache_dir . '\\', $ttl, $max_size );
		$this->assertSame( $cache_dir . '/', $cache->get_root() );
		unset( $cache );

		rmdir( $cache_dir );
	}

	public function test_ensure_dir_exists() {
		$prev_logger = WP_CLI::get_logger();

		$logger = new Loggers\Execution();
		WP_CLI::set_logger( $logger );

		$max_size  = 32;
		$ttl       = 60;
		$cache_dir = Utils\get_temp_dir() . uniqid( 'wp-cli-test-file-cache', true );

		$cache      = new FileCache( $cache_dir, $ttl, $max_size );
		$test_class = new ReflectionClass( $cache );
		$method     = $test_class->getMethod( 'ensure_dir_exists' );
		$method->setAccessible( true );

		// Cache directory should be created.
		$result = $method->invokeArgs( $cache, [ $cache_dir . '/test1' ] );
		$this->assertTrue( $result );
		$this->assertTrue( is_dir( $cache_dir . '/test1' ) );

		// Try to create the same directory again. it should return true.
		$result = $method->invokeArgs( $cache, [ $cache_dir . '/test1' ] );
		$this->assertTrue( $result );

		// `chmod()` doesn't work on Windows.
		if ( ! Utils\is_windows() ) {
			// It should be failed because permission denied.
			$logger->stderr = '';
			chmod( $cache_dir . '/test1', 0000 );
			$result   = $method->invokeArgs( $cache, [ $cache_dir . '/test1/error' ] );
			$expected = "/^Warning: Failed to create directory '.+': mkdir\(\): Permission denied\.$/";
			$this->assertMatchesRegularExpression( $expected, $logger->stderr );
		}

		// It should be failed because file exists.
		$logger->stderr = '';
		file_put_contents( $cache_dir . '/test2', '' );
		$result   = $method->invokeArgs( $cache, [ $cache_dir . '/test2' ] );
		$expected = "/^Warning: Failed to create directory '.+': mkdir\(\): File exists\.$/";
		$this->assertMatchesRegularExpression( $expected, $logger->stderr );

		// Restore
		chmod( $cache_dir . '/test1', 0755 );
		rmdir( $cache_dir . '/test1' );
		unlink( $cache_dir . '/test2' );
		rmdir( $cache_dir );
		WP_CLI::set_logger( $prev_logger );
	}

	public function test_export() {
		$max_size   = 32;
		$ttl        = 60;
		$cache_dir  = Utils\get_temp_dir() . uniqid( 'wp-cli-test-file-cache', true );
		$target_dir = Utils\get_temp_dir() . uniqid( 'wp-cli-test-file-cache-export/nonexistant-subdirectory', true );
		$target     = $target_dir . '/foo';
		$key        = 'foo';
		$contents   = 'bar';
		$cache      = new FileCache( $cache_dir, $ttl, $max_size );

		// Assert subdirectory is created.
		$cache->write( $key, $contents );
		$cache->export( $key, $target );
		$this->assertEquals( $contents, file_get_contents( $target ) );

		// Clean up.
		$cache->clear();
		unlink( $target );
		rmdir( $target_dir );
	}
}
