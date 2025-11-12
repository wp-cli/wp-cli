<?php

namespace WP_CLI;

use WP_CLI;

/**
 * Manage caching with whitelisting
 *
 * @package WP_CLI
 */
class WpHttpCacheManager {

	/**
	 * @var array<string, array{key:string, ttl: int|null}> map whitelisted urls to keys and ttls
	 */
	protected $whitelist = [];

	/**
	 * @var FileCache
	 */
	protected $cache;

	/**
	 * Minimum valid archive file size in bytes.
	 *
	 * This threshold (20 bytes) roughly corresponds to the smallest possible
	 * valid ZIP or TAR.GZ header, ensuring we skip obviously invalid or empty downloads.
	 */
	private const MIN_VALID_ARCHIVE_SIZE = 20;

	/**
	 * @param FileCache $cache
	 */
	public function __construct( FileCache $cache ) {
		$this->cache = $cache;

		// hook into wp http api
		add_filter( 'pre_http_request', [ $this, 'filter_pre_http_request' ], 10, 3 );
		add_filter( 'http_response', [ $this, 'filter_http_response' ], 10, 3 );
	}

	/**
	 * short circuit wp http api with cached file
	 */
	public function filter_pre_http_request( $response, $args, $url ) {
		// check if whitelisted
		if ( ! isset( $this->whitelist[ $url ] ) ) {
			return $response;
		}
		// check if downloading
		if ( 'GET' !== $args['method'] || empty( $args['filename'] ) ) {
			return $response;
		}
		// check cache and export to designated location
		$filename = $this->cache->has( $this->whitelist[ $url ]['key'], $this->whitelist[ $url ]['ttl'] );
		if ( $filename ) {
			WP_CLI::log( sprintf( 'Using cached file \'%s\'...', $filename ) );
			if ( copy( $filename, $args['filename'] ) ) {
				// simulate successful download response
				return [
					'response' => [
						'code'    => 200,
						'message' => 'OK',
					],
					'filename' => $args['filename'],
				];
			}

			WP_CLI::error( sprintf( 'Error copying cached file %s to %s', $filename, $url ) );
		}
		return $response;
	}


	/**
	 * cache wp http api downloads
	 *
	 * @param array  $response
	 * @param array  $args
	 * @param string $url
	 * @return array
	 */
	public function filter_http_response( $response, $args, $url ) {
		// check if whitelisted
		if ( ! isset( $this->whitelist[ $url ] ) ) {
			return $response;
		}
		// check if downloading
		if ( 'GET' !== $args['method'] || empty( $args['filename'] ) ) {
			return $response;
		}
		// check if download was successful
		if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return $response;
		}
		// Validate before caching.
		if ( ! $this->validate_downloaded_file( $response['filename'], $url ) ) {
			WP_CLI::warning( "Invalid or corrupt file from {$url}, skipping cache." );
			return $response;
		}
		// cache downloaded file
		$this->cache->import( $this->whitelist[ $url ]['key'], $response['filename'] );
		return $response;
	}

	/**
	 * Validate downloaded file before adding to cache.
	 *
	 * @param string $file Path to the downloaded file.
	 * @param string $url  Source URL.
	 * @return bool True if file is valid, false otherwise.
	 */
	private function validate_downloaded_file( $file, $url ) {
		if ( ! is_readable( $file ) ) {
			return false;
		}

		$size = filesize( $file );
		if ( false === $size || $size < self::MIN_VALID_ARCHIVE_SIZE ) {
			return false;
		}

		$ext  = strtolower( pathinfo( (string) wp_parse_url( $url, PHP_URL_PATH ), PATHINFO_EXTENSION ) );
		$mime = function_exists( 'mime_content_type' ) ? mime_content_type( $file ) : '';

		if ( ( 'zip' === $ext || 'application/zip' === $mime ) && class_exists( '\ZipArchive' ) ) {
			$zip    = new \ZipArchive();
			$result = $zip->open( $file );
			if ( true !== $result ) {
				return false;
			}
			// Optional deeper check: ensure we can read file list.
			if ( 0 === $zip->numFiles ) { //phpcs:ignore
				$zip->close();
				return false;
			}
			$zip->close();
		}

		if ( ( preg_match( '/\.tar\.gz$/i', $url ) || 'application/gzip' === $mime ) && class_exists( '\PharData' ) ) {
			try {
				$phar = new \PharData( $file );
				// Accessing the file list ensures it can be read.
				if ( empty( iterator_to_array( $phar ) ) ) {
					return false;
				}
			} catch ( \Exception $e ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * whitelist a package url
	 *
	 * @param string $url
	 * @param string $group   package group (themes, plugins, ...)
	 * @param string $slug    package slug
	 * @param string $version package version
	 * @param int    $ttl
	 */
	public function whitelist_package( $url, $group, $slug, $version, $ttl = null ) {
		$ext = pathinfo( (string) Utils\parse_url( $url, PHP_URL_PATH ), PATHINFO_EXTENSION );
		$key = "$group/$slug-$version.$ext";
		$this->whitelist_url( $url, $key, $ttl );
		wp_update_plugins();
	}

	/**
	 * whitelist a url
	 *
	 * @param string $url
	 * @param string $key
	 * @param int    $ttl
	 */
	public function whitelist_url( $url, $key = null, $ttl = null ) {
		$key                     = $key ? : $url;
		$this->whitelist[ $url ] = [
			'key' => $key,
			'ttl' => $ttl,
		];
	}

	/**
	 * check if url is whitelisted
	 *
	 * @param string $url
	 * @return bool
	 */
	public function is_whitelisted( $url ) {
		return isset( $this->whitelist[ $url ] );
	}
}
