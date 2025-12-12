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

use DateTime;
use Exception;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use WP_CLI;

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
	protected $max_size;
	/**
	 * @var string key allowed chars (regex class)
	 */
	protected $whitelist;

	/**
	 * @param string $cache_dir  location of the cache
	 * @param int    $ttl        cache files default time to live (expiration)
	 * @param int    $max_size   max total cache size
	 * @param string $whitelist  List of characters that are allowed in path names (used in a regex character class)
	 */
	public function __construct( $cache_dir, $ttl, $max_size, $whitelist = 'a-z0-9._-' ) {
		$this->root      = Utils\trailingslashit( $cache_dir );
		$this->ttl       = (int) $ttl;
		$this->max_size  = (int) $max_size;
		$this->whitelist = $whitelist;

		if ( ! $this->ensure_dir_exists( $this->root ) ) {
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
	 * @return false|string filename or false
	 *
	 * @phpstan-assert-if-true string $this->read()
	 */
	public function has( $key, $ttl = null ) {
		if ( ! $this->enabled ) {
			return false;
		}

		$filename = $this->filename( $key );

		if ( ! file_exists( $filename ) ) {
			return false;
		}

		// Use ttl param or global ttl.
		if ( null === $ttl ) {
			$ttl = $this->ttl;
		} elseif ( $this->ttl > 0 ) {
			$ttl = min( (int) $ttl, $this->ttl );
		} else {
			$ttl = (int) $ttl;
		}

		$modified_time = filemtime( $filename );
		if ( false === $modified_time ) {
			$modified_time = 0;
		}

		if ( $ttl > 0 && ( $modified_time + $ttl ) < time() ) {
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
		}

		return false;
	}

	/**
	 * Read from cache file
	 *
	 * @param string $key cache key
	 * @param int    $ttl time to live
	 * @return false|string file contents or false
	 */
	public function read( $key, $ttl = null ) {
		$filename = $this->has( $key, $ttl );

		if ( $filename ) {
			return (string) file_get_contents( $filename );
		}

		return false;
	}

	/**
	 * Copy a file into the cache
	 *
	 * @param string $key    cache key
	 * @param string $source source filename; tmp file filepath from HTTP response
	 * @return bool
	 */
	public function import( $key, $source ) {
		$filename = $this->prepare_write( $key );

		if ( ! is_readable( $source ) ) {
			return false;
		}

		if ( $filename ) {
			return copy( $source, $filename ) && touch( $filename );
		}

		return false;
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

		if ( $filename && $this->ensure_dir_exists( dirname( $target ) ) ) {
			return copy( $filename, $target );
		}

		return false;
	}

	/**
	 * Remove file from cache
	 *
	 * @param string $key cache key
	 * @return bool
	 */
	public function remove( $key ) {
		if ( ! $this->enabled ) {
			return false;
		}

		$filename = $this->filename( $key );

		if ( file_exists( $filename ) ) {
			return unlink( $filename );
		}

		return false;
	}

	/**
	 * Clean cache based on time to live and max size
	 *
	 * @return bool
	 */
	public function clean() {
		if ( ! $this->enabled ) {
			return false;
		}

		$ttl      = $this->ttl;
		$max_size = $this->max_size;

		// Unlink expired files.
		if ( $ttl > 0 ) {
			try {
				$expire = new DateTime();
				$expire->modify( '-' . $ttl . ' seconds' );
				$expire_time = $expire->getTimestamp();

				$files = $this->get_cache_files();
				foreach ( $files as $file ) {
					if ( $file->getMTime() <= $expire_time ) {
						unlink( $file->getRealPath() );
					}
				}
			} catch ( Exception $e ) {
				WP_CLI::error( $e->getMessage() );
			}
		}

		// Unlink older files if max cache size is exceeded.
		if ( $max_size > 0 ) {
			$files = $this->get_cache_files();

			// Sort files by accessed time (newest first)
			usort(
				$files,
				static function ( $a, $b ) {
					return $b->getATime() <=> $a->getATime();
				}
			);

			$total = 0;

			foreach ( $files as $file ) {
				if ( ( $total + $file->getSize() ) <= $max_size ) {
					$total += $file->getSize();
				} else {
					unlink( $file->getRealPath() );
				}
			}
		}

		return true;
	}

	/**
	 * Remove all cached files.
	 *
	 * @return bool
	 */
	public function clear() {
		if ( ! $this->enabled ) {
			return false;
		}

		$files = $this->get_cache_files();

		foreach ( $files as $file ) {
			unlink( $file->getRealPath() );
		}

		return true;
	}

	/**
	 * Remove all cached files except for the newest version of one.
	 *
	 * @return bool
	 */
	public function prune() {
		if ( ! $this->enabled ) {
			return false;
		}

		$cache_files = $this->get_cache_files();

		// Sort files by name
		usort(
			$cache_files,
			static function ( $a, $b ) {
				return strcmp( $a->getFilename(), $b->getFilename() );
			}
		);

		$files_by_base = [];

		// Group files by their base name (stripping version/timestamp).
		foreach ( $cache_files as $file ) {
			$basename   = $file->getBasename();
			$pieces     = explode( '-', $file->getBasename( $file->getExtension() ) );
			$last_piece = end( $pieces );

			// Try to identify a version or timestamp suffix.
			$basename_without_suffix = $basename;
			$version_string          = null;

			// Check if last piece is purely numeric (original timestamp format).
			if ( is_numeric( $last_piece ) ) {
				$basename_without_suffix = str_replace( '-' . $last_piece, '', $basename );
				$version_string          = $last_piece; // Store as string for comparison.
			} elseif ( preg_match( '/^(\d+(?:\.\d+)*)/', $last_piece, $matches ) ) {
				// Handle version numbers like "8.6.1" in "jetpack-8.6.1.zip".
				$basename_without_suffix = str_replace( '-' . $last_piece, '', $basename );
				$version_string          = $matches[0]; // Store the version string.
			}

			// Store file info: path, modification time, and optional version string.
			if ( ! isset( $files_by_base[ $basename_without_suffix ] ) ) {
				$files_by_base[ $basename_without_suffix ] = [];
			}

			$files_by_base[ $basename_without_suffix ][] = [
				'path'    => $file->getRealPath(),
				'mtime'   => $file->getMTime(),
				'version' => $version_string,
			];
		}

		// For each group, keep only the newest file and delete the rest.
		foreach ( $files_by_base as $files ) {
			if ( count( $files ) <= 1 ) {
				continue;
			}

			// Sort files: prefer version comparison if available, otherwise use mtime.
			usort(
				$files,
				static function ( $a, $b ) {
					// If both have version strings, use version_compare().
					if ( null !== $a['version'] && null !== $b['version'] ) {
						$cmp = version_compare( $b['version'], $a['version'] );
						if ( 0 !== $cmp ) {
							return $cmp;
						}
						// If versions are equal, fall through to mtime comparison.
					}
					// Otherwise, compare by modification time.
					return $b['mtime'] <=> $a['mtime'];
				}
			);

			// Delete all except the first (newest).
			$total = count( $files );
			for ( $i = 1; $i < $total; $i++ ) {
				unlink( $files[ $i ]['path'] );
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
		if ( ! is_dir( $dir ) ) {
			// Disable the cache if a null device like /dev/null is being used.
			if ( preg_match( '{(^|[\\\\/])(\$null|nul|NUL|/dev/null)([\\\\/]|$)}', $dir ) ) {
				return false;
			}

			if ( ! @mkdir( $dir, 0777, true ) ) {
				$message = "Failed to create directory '{$dir}'";
				$error   = error_get_last();
				if ( is_array( $error ) ) {
					$message .= ": {$error['message']}";
				}
				WP_CLI::warning( "{$message}." );
				return false;
			}
		}

		return true;
	}

	/**
	 * Prepare cache write
	 *
	 * @param string $key cache key
	 * @return false|string The destination filename or false when cache disabled or directory creation fails.
	 */
	protected function prepare_write( $key ) {
		if ( ! $this->enabled ) {
			return false;
		}

		$filename = $this->filename( $key );

		if ( ! $this->ensure_dir_exists( dirname( $filename ) ) ) {
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
		$url_parts = Utils\parse_url( $key, -1, false );
		if ( $url_parts && array_key_exists( 'path', $url_parts ) && ! empty( $url_parts['scheme'] ) ) { // is url
			$parts   = [ 'misc' ];
			$parts[] = $url_parts['scheme'] .
				( empty( $url_parts['host'] ) ? '' : '-' . $url_parts['host'] ) .
				( empty( $url_parts['port'] ) ? '' : '-' . $url_parts['port'] );
			$parts[] = substr( $url_parts['path'], 1 ) .
				( empty( $url_parts['query'] ) ? '' : '-' . $url_parts['query'] );
		} else {
			$key   = str_replace( '\\', '/', $key );
			$parts = explode( '/', ltrim( $key ) );
		}

		$parts = preg_replace( "#[^{$this->whitelist}]#i", '-', $parts );

		return rtrim( implode( '/', $parts ), '.' );
	}

	/**
	 * Destination filename from key
	 *
	 * @param string $key
	 * @return string filename
	 */
	protected function filename( $key ) {
		return $this->root . $this->validate_key( $key );
	}

	/**
	 * Get all files in the cache directory recursively
	 *
	 * @return SplFileInfo[]
	 */
	protected function get_cache_files() {
		$files = [];

		if ( ! is_dir( $this->root ) ) {
			return $files;
		}

		try {
			// Match Symfony Finder behavior: do not follow symlinks.
			// We explicitly do NOT include FilesystemIterator::FOLLOW_SYMLINKS flag.
			// This prevents the iterator from traversing into symlinked directories.
			// We also filter out symlink files themselves with !isLink() check.
			$iterator = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator(
					$this->root,
					FilesystemIterator::SKIP_DOTS | FilesystemIterator::UNIX_PATHS
				),
				RecursiveIteratorIterator::LEAVES_ONLY
			);

			foreach ( $iterator as $file ) {
				if ( $file instanceof SplFileInfo && $file->isFile() && ! $file->isLink() ) {
					$files[] = $file;
				}
			}
		} catch ( Exception $e ) {
			// If directory iteration fails (e.g., permissions issue, directory deleted),
			// return empty array. This matches the behavior of Symfony Finder which
			// would also return an empty result for inaccessible directories.
			return [];
		}

		return $files;
	}
}
