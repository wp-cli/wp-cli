<?php

use WP_CLI\FileCache;
use WP_CLI\Utils;
use Symfony\Component\Finder\Finder;

class FileCacheTest extends PHPUnit_Framework_TestCase {

	/**
	 * Test that no new classes are loaded in clean() as this can cause problems if it's called in a register_shutdown_function.
	 */
	public function testFinderLoaded() {
		$max_size = 32;
		$ttl = 60;

		$cache_dir = Utils\get_temp_dir() . '/' . uniqid( "wp-cli-test-file-cache", TRUE );

		$cache = new FileCache( $cache_dir, $ttl, $max_size );

		$after_construct_classes = get_declared_classes();

		// Less than time to live file.
		$cache->write( 'ttl', 'ttl' );
		touch( $cache_dir . '/ttl', time() - ( $ttl + 1 ) );

		// Greater than max size file.
		$cache->write( 'max_size', str_repeat( 'm', $max_size + 1 ) );

		// Check no change in loaded classes.
		$after_write_classes = get_declared_classes();
		$after_write_diff = array_diff( $after_write_classes, $after_construct_classes );
		$this->assertEmpty( $after_write_diff );

		$cache->clean();

		// Should be no change in loaded classes.
		$after_clean_classes = get_declared_classes();
		$after_clean_diff = array_diff( $after_clean_classes, $after_write_classes );
		$this->assertEmpty( $after_clean_diff );

		$this->assertFalse( file_exists( $cache_dir . '/max_size' ) );
		$this->assertFalse( file_exists( $cache_dir . '/ttl' ) );

		rmdir( $cache_dir );
	}
}
