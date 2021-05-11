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
	 * @var array map whitelisted urls to keys and ttls
	 */
	protected $whitelist = [];

	/**
	 * @var FileCache
	 */
	protected $cache;

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
	 * @param array $response
	 * @param array $args
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
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return $response;
		}
		// cache downloaded file
		$this->cache->import( $this->whitelist[ $url ]['key'], $response['filename'] );
		return $response;
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
		$ext = pathinfo( Utils\parse_url( $url, PHP_URL_PATH ), PATHINFO_EXTENSION );
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
		$this->whitelist[ $url ] = compact( 'key', 'ttl' );
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
