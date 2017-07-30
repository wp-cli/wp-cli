<?php

use WP_CLI\FileCache;
use WP_CLI\Utils;

class FileCacheTest extends PHPUnit_Framework_TestCase {

	/**
	 * Test get_root() deals with backslashed directory.
	 */
	public function testGetRoot() {
		$max_size = 32;
		$ttl = 60;

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
}
