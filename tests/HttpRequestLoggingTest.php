<?php

use WP_CLI\Tests\TestCase;
use WP_CLI\Utils;

class HttpRequestLoggingTest extends TestCase {

	public static function set_up_before_class() {
		require_once dirname( __DIR__ ) . '/php/class-wp-cli.php';
		require_once __DIR__ . '/mock-requests-transport.php';
	}

	public function testHttpRequestOptionsHookReceivesAllParameters(): void {
		$hook_called      = false;
		$received_method  = null;
		$received_url     = null;
		$received_data    = null;
		$received_headers = null;

		WP_CLI::add_hook(
			'http_request_options',
			function ( $options, $method, $url, $data, $headers ) use ( &$hook_called, &$received_method, &$received_url, &$received_data, &$received_headers ) {
				$hook_called      = true;
				$received_method  = $method;
				$received_url     = $url;
				$received_data    = $data;
				$received_headers = $headers;
				return $options;
			}
		);

		$test_url     = 'https://example.com/test';
		$test_data    = [ 'key' => 'value' ];
		$test_headers = [ 'X-Test' => 'test' ];

		try {
			Utils\http_request(
				'POST',
				$test_url,
				$test_data,
				$test_headers,
				[
					'timeout'       => 0.01,
					'halt_on_error' => false,
				]
			);
		// phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
		} catch ( \RuntimeException $e ) {
			// Expected to fail due to short timeout
		}

		$this->assertTrue( $hook_called, 'http_request_options hook should be called' );
		$this->assertEquals( 'POST', $received_method, 'Method should be passed to hook' );
		$this->assertEquals( $test_url, $received_url, 'URL should be passed to hook' );
		$this->assertEquals( $test_data, $received_data, 'Data should be passed to hook' );
		$this->assertEquals( $test_headers, $received_headers, 'Headers should be passed to hook' );
	}
}
