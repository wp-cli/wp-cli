<?php

/*
 * This file is heavily inspired and use code from Composer(getcomposer.org),
 * in particular Composer/Cache and Composer/Util/FileSystem from 1.0.0-alpha7
 *
 * The original code and this file are both released under MIT license.
 *
 * The copyright holders of the original code are:
 * (c) Nils Adermann <naderman@naderman.de>
 *     Jordi Boggiano <j.boggiano@seld.be>
 */

namespace WP_CLI;

use Symfony\Component\Finder\Finder;

/**
 * Reads/writes to a filesystem cache
 */
class FileCache {

	/**
	 * @var string cache path
	 */
	protected $root;
	/**
	 * @var bool
	 */
	protected $enabled = true;
	/**
	 * @var int files time to live
	 */
	protected $ttl;
	/**
	 * @var int max total size
	 */
	protected $maxSize;
	/**
	 * @var string key allowed chars (regex class)
	 */
	protected $whitelist;

	/**
	 * @param string $cacheDir   location of the cache
	 * @param int    $ttl        cache files default time to live (expiration)
	 * @param int    $maxSize    max total cache size
	 * @param string $whitelist  List of characters that are allowed in path names (used in a regex character class)
	 */
	public function __construct( $cacheDir, $ttl, $maxSize, $whitelist = 'a-z0-9._-' ) {
		$this->root = rtrim( $cacheDir, '/\\' ) . '/';
		$this->ttl = (int) $ttl;
		$this->maxSize = (int) $maxSize;
		$this->whitelist = $whitelist;

		if ( !$this->ensure_dir_exists( $this->root ) ) {
			$this->enabled = false;
		}

	}

	/**
	 * Cache is enabled
	 *
	 * @return bool
	 */
	public function is_enabled() {
		return $this->enabled;
	}

	/**
	 * Cache root
	 *
	 * @return string
	 */
	public function get_root() {
		return $this->root;
	}


	/**
	 * Check if a file is in cache and return its filename
	 *
	 * @param string $key cache key
	 * @param int    $ttl time to live
	 * @return bool|string filename or false
	 */
	public function has( $key, $ttl = null ) {
		if ( !$this->enabled ) {
			return false;
		}

		$filename = $this->filename( $key );

		if ( !file_exists( $filename ) ) {
			return false;
		}

		// use ttl param or global ttl
		if ( $ttl === null ) {
			$ttl = $this->ttl;
		} elseif ( $this->ttl > 0 ) {
			$ttl = min( (int) $ttl, $this->ttl );
		} else {
			$ttl = (int) $ttl;
		}

		//
		if ( $ttl > 0 && filemtime( $filename ) + $ttl < time() ) {
			if ( $this->ttl > 0 && $ttl >= $this->ttl ) {
				unlink( $filename );
			}
			return false;
		}

		return $filename;
	}

	/**
	 * Write to cache file
	 *
	 * @param string $key      cache key
	 * @param string $contents file contents
	 * @return bool
	 */
	public function write( $key, $contents ) {
		$filename = $this->prepare_write( $key );

		if ( $filename ) {
			return file_put_contents( $filename, $contents ) && touch( $filename );
		} else {
			return false;
		}
	}

	/**
	 * Read from cache file
	 *
	 * @param string $key cache key
	 * @param int    $ttl time to live
	 * @return bool|string file contents or false
	 */
	public function read( $key, $ttl = null ) {
		$filename = $this->has( $key, $ttl );

		if ( $filename ) {
			return file_get_contents( $filename );
		} else {
			return false;
		}
	}

	/**
	 * Copy a file into the cache
	 *
	 * @param string $key    cache key
	 * @param string $source source filename
	 * @return bool
	 */
	public function import( $key, $source ) {
		$filename = $this->prepare_write( $key );

		if ( $filename ) {
			return copy( $source, $filename ) && touch( $filename );
		} else {
			return false;
		}
	}

	/**
	 * Copy a file out of the cache
	 *
	 * @param string $key    cache key
	 * @param string $target target filename
	 * @param int    $ttl    time to live
	 * @return bool
	 */
	public function export( $key, $target, $ttl = null ) {
		$filename = $this->has( $key, $ttl );

		if ( $filename ) {
			return copy( $filename, $target );
		} else {
			return false;
		}
	}

	/**
	 * Remove file from cache
	 *
	 * @param string $key cache key
	 * @return bool
	 */
	public function remove( $key ) {
		if ( !$this->enabled ) {
			return false;
		}

		$filename = $this->filename( $key );

		if ( file_exists( $filename ) ) {
			return unlink( $filename );
		} else {
			return false;
		}
	}

	/**
	 * Clean cache based on time to live and max size
	 *
	 * @return bool
	 */
	public function clean() {
		if ( !$this->enabled ) {
			return false;
		}

		$ttl = $this->ttl;
		$maxSize = $this->maxSize;

		// unlink expired files
		if ( $ttl > 0 ) {
			$expire = new \DateTime();
			$expire->modify( '-' . $ttl . ' seconds' );

			$finder = $this->get_finder()->date( 'until ' . $expire->format( 'Y-m-d H:i:s' ) );
			foreach ( $finder as $file ) {
				unlink( $file->getRealPath() );
			}
		}

		// unlink older files if max cache size is exceeded
		if ( $maxSize > 0 ) {
			$files = array_reverse( iterator_to_array( $this->get_finder()->sortByAccessedTime()->getIterator() ) );
			$total = 0;

			foreach ( $files as $file ) {
				if ( $total + $file->getSize() <= $maxSize ) {
					$total += $file->getSize();
				} else {
					unlink( $file->getRealPath() );
				}
			}
		}

		return true;
	}

	/**
	 * Ensure directory exists
	 *
	 * @param string $dir directory
	 * @return bool
	 */
	protected function ensure_dir_exists( $dir ) {
		if ( !is_dir( $dir ) ) {
			if ( file_exists( $dir ) ) {
				// exists and not a dir
				return false;
			}
			if ( !@mkdir( $dir, 0777, true ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Prepare cache write
	 *
	 * @param string $key cache key
	 * @return bool|string filename or false
	 */
	protected function prepare_write( $key ) {
		if ( !$this->enabled ) {
			return false;
		}

		$filename = $this->filename( $key );

		if ( !$this->ensure_dir_exists( dirname( $filename ) ) ) {
			return false;
		}

		return $filename;
	}

	/**
	 * Validate cache key
	 *
	 * @param string $key cache key
	 * @return string relative filename
	 */
	protected function validate_key( $key ) {
		$url_parts = parse_url( $key );
		if ( ! empty($url_parts['scheme']) ) { // is url
			$parts = array('misc');
			$parts[] = $url_parts['scheme'] . '-' . $url_parts['host'] .
				( empty( $url_parts['port'] ) ? '' : '-' . $url_parts['port'] );
			$parts[] = substr($url_parts['path'], 1) .
				( empty( $url_parts['query'] ) ? '' : '-' . $url_parts['query'] );
		} else {
			$key = str_replace( '\\', '/', $key );
			$parts = explode( '/', ltrim( $key ) );
		}

		$parts = preg_replace( "#[^{$this->whitelist}]#i", '-', $parts );

		return implode( '/', $parts );
	}

	/**
	 * Filename from key
	 *
	 * @param string $key
	 * @return string filename
	 */
	protected function filename( $key ){
		return $this->root . $this->validate_key( $key );
	}

	/**
	 * Get a Finder that iterates in cache root only the files
	 *
	 * @return Finder
	 */
	protected function get_finder() {
		return Finder::create()->in( $this->root )->files();
	}
}
