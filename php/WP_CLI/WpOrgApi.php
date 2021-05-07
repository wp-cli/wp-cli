<?php

namespace WP_CLI;

/**
 * Class WpOrgApi.
 *
 * This is an abstraction of the WordPress.org API.
 *
 * @see https://codex.wordpress.org/WordPress.org_API
 *
 * @package WP_CLI
 */
final class WpOrgApi {

	/**
	 * WordPress.org API root URL.
	 *
	 * @var string
	 */
	const API_ROOT = 'https://api.wordpress.org';

	/**
	 * WordPress.org API root URL.
	 *
	 * @var string
	 */
	const DOWNLOADS_ROOT = 'https://downloads.wordpress.org';

	/**
	 * Core checksums endpoint.
	 *
	 * @see https://codex.wordpress.org/WordPress.org_API#Checksum
	 *
	 * @var string
	 */
	const CORE_CHECKSUMS_ENDPOINT = self::API_ROOT . '/core/checksums/1.0/';

	/**
	 * Plugin checksums endpoint.
	 *
	 * @var string
	 */
	const PLUGIN_CHECKSUMS_ENDPOINT = self::DOWNLOADS_ROOT . '/plugin-checksums/';

	/**
	 * Whether to retry without certificate validation on TLS handshake failures.
	 *
	 * @var bool
	 */
	private $insecure = false;

	/**
	 * Timeout to use for remote requests.
	 *
	 * @var int
	 */
	private $timeout = 30;

	/**
	 * WpOrgApi constructor.
	 *
	 * @param array $options Associative array of options to pass to the API abstraction.
	 */
	public function __construct( $options = [] ) {
		if ( array_key_exists( 'insecure', $options ) ) {
			$this->insecure = (bool) $options['insecure'];
		}

		if ( array_key_exists( 'timeout', $options ) ) {
			$this->timeout = (int) $options['timeout'];
		}
	}

	/**
	 * Gets the checksums for the given version of WordPress core.
	 *
	 * @param string $version Version string to query.
	 * @param string $locale  Locale to query.
	 * @return bool|array False on failure. An array of checksums on success.
	 * @throws ExitException If the remote request fails.
	 */
	public function get_core_checksums( $version, $locale ) {
		$url = sprintf(
			'%s?%s',
			self::CORE_CHECKSUMS_ENDPOINT,
			http_build_query( compact( 'version', 'locale' ), null, '&' )
		);

		$response = $this->json_get_request( $url );

		if ( ! is_array( $response )
			|| ! isset( $response['checksums'] )
			|| ! is_array( $response['checksums'] ) ) {
			return false;
		}

		return $response['checksums'];
	}

	/**
	 * Gets the checksums for the given version of plugin.
	 *
	 * @param string $version Version string to query.
	 * @param string $plugin  Plugin string to query.
	 * @return bool|array False on failure. An array of checksums on success.
	 * @throws ExitException If the remote request fails.
	 */
	public function get_plugin_checksums( $plugin, $version ) {
		$url = sprintf(
			'%s%s/%s.json',
			self::PLUGIN_CHECKSUMS_ENDPOINT,
			$plugin,
			$version
		);

		$response = $this->json_get_request( $url );

		if ( ! is_array( $response )
			|| ! isset( $response['files'] )
			|| ! is_array( $response['files'] ) ) {
			return false;
		}

		return $response['files'];
	}

	/**
	 * Execute a remote GET request.
	 *
	 * @param string $url     URL to execute the GET request on.
	 * @param array  $headers Optional. Associative array of headers.
	 * @param array  $options Optional. Associative array of options.
	 * @return mixed|false False on failure. Decoded JSON on success.
	 * @throws ExitException If the remote request fails.
	 */
	private function json_get_request( $url, $headers = [], $options = [] ) {
		$headers = array_merge(
			[
				'Accept' => 'application/json',
			],
			$headers
		);

		$response = $this->get_request( $url, $headers, $options );

		if ( false === $response ) {
			return $response;
		}

		return json_decode( $response, true );
	}

	/**
	 * Execute a remote GET request.
	 *
	 * @param string $url     URL to execute the GET request on.
	 * @param array  $headers Optional. Associative array of headers.
	 * @param array  $options Optional. Associative array of options.
	 * @return string|false False on failure. Response body string on success.
	 * @throws ExitException If the remote request fails.
	 */
	private function get_request( $url, $headers = [], $options = [] ) {
		$options = array_merge(
			[
				'insecure' => $this->insecure,
				'timeout'  => $this->timeout,
			],
			$options
		);

		$response = Utils\http_request( 'GET', $url, null, $headers, $options );

		if ( ! $response->success || 200 !== (int) $response->status_code ) {
			return false;
		}

		return trim( $response->body );
	}
}
