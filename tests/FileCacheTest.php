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

		// Restore.
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

	public function test_import() {
		$max_size  = 32;
		$ttl       = 60;
		$cache_dir = Utils\get_temp_dir() . uniqid( 'wp-cli-test-file-cache', true );
		$cache     = new FileCache( $cache_dir, $ttl, $max_size );

		$tmp_dir = Utils\get_temp_dir() . uniqid( 'wp-cli-test-file-cache-import', true );
		mkdir( $tmp_dir );

		// "$group/$slug-$version.$ext";
		$key              = 'plugin/my-fixture-plugin-1.0.0.zip';
		$fixture_filepath = $tmp_dir . '/my-downloaded-fixture-plugin-1.0.0.zip';

		$zip = new ZipArchive();
		$zip->open( $fixture_filepath, ZIPARCHIVE::CREATE );
		$zip->addFile( __FILE__ );
		$zip->close();

		$result = $cache->import( $key, $fixture_filepath );

		// Assert file is imported.
		$this->assertTrue( $result );
		$this->assertFileExists( "{$cache_dir}/{$key}" );

		// Clean up.
		$cache->clear();
		unlink( $fixture_filepath );
		rmdir( $tmp_dir );
	}

	/**
	 * @see https://github.com/wp-cli/wp-cli/pull/5947
	 */
	public function test_import_do_not_use_cache_file_cannot_be_read() {
		$max_size  = 32;
		$ttl       = 60;
		$cache_dir = Utils\get_temp_dir() . uniqid( 'wp-cli-test-file-cache', true );
		$cache     = new FileCache( $cache_dir, $ttl, $max_size );

		$tmp_dir = Utils\get_temp_dir() . uniqid( 'wp-cli-test-file-cache-import', true );
		mkdir( $tmp_dir );

		$key              = 'plugin/my-fixture-plugin-1.0.0.zip';
		$fixture_filepath = $tmp_dir . '/my-bad-permissions-fixture-plugin-1.0.0.zip';

		$zip = new ZipArchive();
		$zip->open( $fixture_filepath, ZIPARCHIVE::CREATE );
		$zip->addFile( __FILE__ );
		$zip->close();

		chmod( $fixture_filepath, 0000 );

		// "Warning: copy(-.): Failed to open stream: Permission denied".
		$error = null;
		set_error_handler(
			function ( $errno, $errstr ) use ( &$error ) {
				$error = $errstr;
			}
		);

		$result = $cache->import( $key, $fixture_filepath );

		restore_error_handler();

		$this->assertNull( $error );
		$this->assertFalse( $result );

		// Clean up.
		$cache->clear();
		unlink( $fixture_filepath );
		rmdir( $tmp_dir );
	}

	/**
	 * Windows filenames cannot end in periods.
	 *
	 * @covers \WP_CLI\FileCache::validate_key
	 *
	 * @see https://github.com/wp-cli/wp-cli/pull/5947
	 * @see https://learn.microsoft.com/en-us/windows/win32/fileio/naming-a-file#naming-conventions
	 */
	public function test_validate_key_ending_in_period() {
		$max_size  = 32;
		$ttl       = 60;
		$cache_dir = Utils\get_temp_dir() . uniqid( 'wp-cli-test-file-cache', true );
		$cache     = new FileCache( $cache_dir, $ttl, $max_size );

		$key = 'plugin/advanced-sidebar-menu-pro-9.5.7.';

		$reflection = new ReflectionClass( $cache );

		$method = $reflection->getMethod( 'validate_key' );
		$method->setAccessible( true );

		$result = $method->invoke( $cache, $key );

		$this->assertStringEndsNotWith( '.', $result );
		$this->assertSame( 'plugin/advanced-sidebar-menu-pro-9.5.7', $result );
	}
}
