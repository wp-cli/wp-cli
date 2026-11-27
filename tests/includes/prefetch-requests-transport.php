<?php

use WpOrg\Requests\Transport;

/**
 * Serves every request of a batch from a canned body, so that
 * WpHttpCacheManager::prefetch() can be exercised without a network.
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound
class Prefetch_Requests_Transport implements Transport {

	/**
	 * Bytes written to each request's target file, or null to fail the whole batch.
	 *
	 * @var string|null
	 */
	public static $body = null;

	/**
	 * URLs of each request_multiple() batch, in the order they were requested.
	 *
	 * @var array<int, array<int, string>>
	 */
	public static $batches = [];

	/**
	 * Redirect targets, keyed by the URL that answers with a redirect to them.
	 *
	 * @var array<string, string>
	 */
	public static $redirects = [];

	/**
	 * URLs requested on their own, which happens when a redirect is followed.
	 *
	 * @var array<int, string>
	 */
	public static $singles = [];

	/**
	 * @param string              $url
	 * @param array<mixed>        $headers
	 * @param array<mixed>|string $data
	 * @param array<mixed>        $options
	 * @return string
	 */
	public function request( $url, $headers = [], $data = [], $options = [] ) {
		self::$singles[] = $url;
		/** @var array{filename: string} $options */
		file_put_contents( $options['filename'], (string) self::$body );
		return 'HTTP/1.1 200 OK';
	}

	/**
	 * @param array<mixed> $requests
	 * @param array<mixed> $options
	 * @return array<string, string>
	 */
	public function request_multiple( $requests, $options ) {
		self::$batches[] = array_keys( $requests );

		if ( null === self::$body ) {
			throw new Exception( 'Simulated transport failure.' );
		}

		$responses = [];
		foreach ( $requests as $id => $request ) {
			/** @var array{options: array{filename: string}} $request */
			if ( isset( self::$redirects[ $id ] ) ) {
				$responses[ $id ] = "HTTP/1.1 302 Found\r\nLocation: " . self::$redirects[ $id ];
				continue;
			}
			file_put_contents( $request['options']['filename'], self::$body );
			$responses[ $id ] = 'HTTP/1.1 200 OK';
		}

		return $responses;
	}

	/**
	 * @param array<mixed> $capabilities
	 * @return bool
	 */
	public static function test( $capabilities = [] ) {
		return true;
	}
}
