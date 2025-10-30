<?php

use WP_CLI\Loggers;
use WP_CLI\Tests\TestCase;
use WP_CLI\Utils;

class HttpRequestLoggingTest extends TestCase {

	public static function set_up_before_class() {
		require_once dirname( __DIR__ ) . '/php/class-wp-cli.php';
		require_once __DIR__ . '/mock-requests-transport.php';
	}

	public function testHttpRequestOptionsHookReceivesAllParameters(): void {
		$hook_called = false;
		$received_method = null;
		$received_url = null;
		$received_data = null;
		$received_headers = null;

		WP_CLI::add_hook(
			'http_request_options',
			function ( $options, $method, $url, $data, $headers ) use ( &$hook_called, &$received_method, &$received_url, &$received_data, &$received_headers ) {
				$hook_called = true;
				$received_method = $method;
				$received_url = $url;
				$received_data = $data;
				$received_headers = $headers;
				return $options;
			}
		);

		$test_url = 'https://example.com/test';
		$test_data = [ 'key' => 'value' ];
		$test_headers = [ 'X-Test' => 'test' ];

		try {
			Utils\http_request( 'POST', $test_url, $test_data, $test_headers, [ 'timeout' => 0.01, 'halt_on_error' => false ] );
		} catch ( \RuntimeException $e ) {
			// Expected to fail due to short timeout
		}

		$this->assertTrue( $hook_called, 'http_request_options hook should be called' );
		$this->assertEquals( 'POST', $received_method, 'Method should be passed to hook' );
		$this->assertEquals( $test_url, $received_url, 'URL should be passed to hook' );
		$this->assertEquals( $test_data, $received_data, 'Data should be passed to hook' );
		$this->assertEquals( $test_headers, $received_headers, 'Headers should be passed to hook' );
	}

	public function testHttpRequestLoggingDebugMessage(): void {
		// Save WP_CLI state
		$prev_logger = WP_CLI::get_logger();

		// Set up a logger to capture debug output
		$logger = new Loggers\Execution();
		WP_CLI::set_logger( $logger );

		// Add the HTTP request logging hook (simulating what setup_bootstrap_hooks does)
		WP_CLI::add_hook(
			'http_request_options',
			static function ( $options, $method, $url, $data, $headers ) {
				WP_CLI::debug( sprintf( 'HTTP %s request to %s', $method, $url ), 'http' );
				return $options;
			}
		);

		$test_url = 'https://example.com/api';
		try {
			Utils\http_request( 'GET', $test_url, null, [], [ 'timeout' => 0.01, 'halt_on_error' => false ] );
		} catch ( \RuntimeException $e ) {
			// Expected to fail due to short timeout
		}

		// Verify the debug message was logged
		$this->assertStringContainsString( 'HTTP GET request to ' . $test_url, $logger->stderr );

		// Restore logger
		WP_CLI::set_logger( $prev_logger );
	}
}
