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
	 *
	 * @param false|array<string, mixed>|\WP_Error $response
	 * @param array<string, mixed>                  $args
	 * @param string                                $url
	 * @return false|array<string, mixed>|\WP_Error
	 */
	public function filter_pre_http_request( $response, $args, $url ) {
		// check if whitelisted
		if ( ! isset( $this->whitelist[ $url ] ) ) {
			return $response;
		}
		// check if downloading
		$method      = isset( $args['method'] ) && is_string( $args['method'] ) ? $args['method'] : '';
		$target_file = isset( $args['filename'] ) && is_string( $args['filename'] ) ? $args['filename'] : '';
		if ( 'GET' !== $method || '' === $target_file ) {
			return $response;
		}
		// check cache and export to designated location
		$filename = $this->cache->has( $this->whitelist[ $url ]['key'], $this->whitelist[ $url ]['ttl'] );
		if ( $filename ) {
			WP_CLI::log( sprintf( 'Using cached file \'%s\'...', $filename ) );
			if ( copy( $filename, $target_file ) ) {
				// simulate successful download response
				return [
					'response' => [
						'code'    => 200,
						'message' => 'OK',
					],
					'filename' => $target_file,
				];
			}

			WP_CLI::error( sprintf( 'Error copying cached file %s to %s', $filename, $url ) );
		}
		return $response;
	}


	/**
	 * cache wp http api downloads
	 *
	 * @param array<string, mixed> $response
	 * @param array<string, mixed> $args
	 * @param string               $url
	 * @return array<string, mixed>
	 */
	public function filter_http_response( $response, $args, $url ) {
		// check if whitelisted
		if ( ! isset( $this->whitelist[ $url ] ) ) {
			return $response;
		}
		// check if downloading
		$method = isset( $args['method'] ) && is_string( $args['method'] ) ? $args['method'] : '';
		if ( 'GET' !== $method || empty( $args['filename'] ) ) {
			return $response;
		}
		// check if download was successful
		if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return $response;
		}
		// Validate before caching.
		if ( ! isset( $response['filename'] ) || ! is_string( $response['filename'] ) || ! $this->validate_downloaded_file( $response['filename'], $url ) ) {
			WP_CLI::warning( "Invalid or corrupt file from {$url}, skipping cache." );
			return $response;
		}
		// cache downloaded file
		$this->cache->import( $this->whitelist[ $url ]['key'], $response['filename'] );
		return $response;
	}

	/**
	 * Download whitelisted URLs concurrently and store them in the cache.
	 *
	 * The WordPress upgrader downloads each package on its own, one after the
	 * other. Filling the cache ahead of time lets filter_pre_http_request()
	 * answer those downloads from disk, so a bulk update spends its network
	 * time on all packages at once instead of on each in turn.
	 *
	 * Only URLs that are whitelisted and not yet cached are fetched. A URL
	 * whose download fails validation is left out of the cache, so the
	 * upgrader's own download still runs for it. Fetching is skipped when the
	 * cache is disabled or when WordPress is configured to block or proxy
	 * outbound requests, because those settings are applied by the WordPress
	 * HTTP API and this method does not go through it. For the same reason
	 * WordPress's request filters (http_request_args, pre_http_request) are
	 * not applied here: a package that depends on them, such as one that
	 * needs an authorization header, fails validation and is downloaded by
	 * the upgrader as before.
	 *
	 * @param string[] $urls        URLs to fetch.
	 * @param int      $concurrency Maximum number of downloads in flight at once.
	 * @return int Number of URLs added to the cache.
	 */
	public function prefetch( array $urls, $concurrency = 6 ) {
		if ( ! $this->cache->is_enabled() ) {
			return 0;
		}

		if ( ( defined( 'WP_HTTP_BLOCK_EXTERNAL' ) && WP_HTTP_BLOCK_EXTERNAL ) || defined( 'WP_PROXY_HOST' ) ) {
			return 0;
		}

		$pending = [];
		foreach ( array_unique( $urls ) as $url ) {
			if ( ! is_string( $url ) || ! isset( $this->whitelist[ $url ] ) || ! preg_match( '#^https?://#i', $url ) ) {
				continue;
			}
			if ( $this->cache->has( $this->whitelist[ $url ]['key'], $this->whitelist[ $url ]['ttl'] ) ) {
				continue;
			}
			$pending[] = $url;
		}

		// One download gains nothing from running "in parallel".
		if ( count( $pending ) < 2 ) {
			return 0;
		}

		$concurrency = max( 1, (int) $concurrency );
		$temp_dir    = Utils\get_temp_dir();
		$user_agent  = function_exists( 'get_bloginfo' ) && function_exists( 'home_url' )
			? 'WordPress/' . get_bloginfo( 'version' ) . '; ' . home_url( '/' )
			: 'WP-CLI/' . WP_CLI_VERSION;

		// make_temp_file() exits the process when it cannot create a file; the
		// upgrader can still download without a prefetch, so bow out instead.
		if ( ! is_writable( $temp_dir ) ) {
			WP_CLI::debug( 'Package prefetch skipped: the temp directory is not writable.', 'http' );
			return 0;
		}

		WP_CLI::log( sprintf( 'Downloading %d packages...', count( $pending ) ) );

		RequestsLibrary::register_autoloader();
		$requests_class = RequestsLibrary::get_class_name();
		$start          = microtime( true );
		$cached         = 0;

		foreach ( array_chunk( $pending, $concurrency ) as $chunk ) {
			$requests      = [];
			$files         = [];
			$batch_options = [];
			$transport     = null;

			try {
				foreach ( $chunk as $url ) {
					$options = $this->prefetch_request_options( $url, $user_agent );

					// Requests reads the transport from the batch options, not from a
					// request's own, so every request in a batch has to share one. A
					// URL handed a different transport is left to the upgrader.
					$hooked_transport = isset( $options['transport'] ) ? $options['transport'] : null;
					$transport_class  = '';
					if ( is_object( $hooked_transport ) ) {
						$transport_class = get_class( $hooked_transport );
					} elseif ( is_string( $hooked_transport ) ) {
						$transport_class = $hooked_transport;
					}
					if ( null === $transport ) {
						$transport = $transport_class;
						if ( '' !== $transport_class ) {
							$batch_options['transport'] = $hooked_transport;
						}
					} elseif ( $transport !== $transport_class ) {
						WP_CLI::debug( "Prefetch of {$url} skipped: its transport differs from the batch's; the upgrader will download it.", 'http' );
						continue;
					}
					unset( $options['transport'] );

					$files[ $url ]       = Utils\make_temp_file( 'wp-cli-prefetch-' );
					$options['filename'] = $files[ $url ];
					$requests[ $url ]    = [
						'url'     => $url,
						'type'    => 'GET',
						'headers' => [],
						'data'    => [],
						'options' => $options,
					];
				}

				if ( empty( $requests ) ) {
					continue;
				}

				try {
					$responses = $requests_class::request_multiple( $requests, $batch_options );
				} catch ( \Exception $exception ) {
					WP_CLI::debug( 'Package prefetch failed: ' . $exception->getMessage(), 'http' );
					$responses = [];
				}

				foreach ( $files as $url => $file ) {
					$response = isset( $responses[ $url ] ) ? $responses[ $url ] : null;
					$success  = is_object( $response )
						&& ! empty( $response->success )
						&& isset( $response->status_code )
						&& 200 === (int) $response->status_code
						&& $this->validate_downloaded_file( $file, $url );

					if ( $success && $this->cache->import( $this->whitelist[ $url ]['key'], $file ) ) {
						++$cached;
						WP_CLI::debug( "Prefetched {$url}.", 'http' );
					} else {
						WP_CLI::debug( "Prefetch of {$url} failed; the upgrader will download it.", 'http' );
					}
				}
			} finally {
				foreach ( $files as $file ) {
					if ( file_exists( $file ) ) {
						unlink( $file );
					}
				}
			}
		}

		WP_CLI::debug(
			sprintf(
				'Prefetched %d of %d packages in %.1fs.',
				$cached,
				count( $pending ),
				microtime( true ) - $start
			),
			'http'
		);

		return $cached;
	}

	/**
	 * Request options for one prefetch download, after the http_request_options hook.
	 *
	 * @param string $url        URL to download.
	 * @param string $user_agent User agent to send.
	 * @return array<string, mixed>
	 */
	private function prefetch_request_options( $url, $user_agent ) {
		$options = [
			'timeout'          => 300,
			'connect_timeout'  => 10,
			'follow_redirects' => true,
			'useragent'        => $user_agent,
			'verify'           => ! empty( ini_get( 'curl.cainfo' ) ) ? ini_get( 'curl.cainfo' ) : true,
		];

		/** This hook is documented in php/utils.php */
		return WP_CLI::do_hook( 'http_request_options', $options, 'GET', $url, null, [] );
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

		$ext  = strtolower( pathinfo( (string) Utils\parse_url( $url, PHP_URL_PATH ), PATHINFO_EXTENSION ) );
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
	 * @return void
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
	 * @return void
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
