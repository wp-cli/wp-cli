<?php

namespace WP_CLI\Bootstrap;

use WP_CLI;

/**
 * Class InitializeHttpRequestMonitoring
 *
 * Initialize HTTP Request Monitoring by hooking into WordPress HTTP API filters.
 *
 * @package WP_CLI\Bootstrap
 */
class InitializeHttpRequestMonitoring implements BootstrapStep {

	/**
	 * Log file handle for HTTP requests/responses.
	 *
	 * @var resource|false
	 */
	private $log_file_handle = false;

	/**
	 * Process this single bootstrapping step.
	 *
	 * @param BootstrapState $state Contextual state to pass into the step.
	 *
	 * @return BootstrapState Modified state to pass to the next step.
	 */
	public function process( BootstrapState $state ) {
		// Initialize log file if http_log_file is set.
		$http_log_file = WP_CLI::get_config( 'http_log_file' );
		if ( $http_log_file ) {
			$this->log_file_handle = fopen( $http_log_file, 'a' );
			if ( ! $this->log_file_handle ) {
				WP_CLI::warning( sprintf( 'Could not open HTTP log file %s for writing', $http_log_file ) );
			} else {
				// Write header to log file.
				fwrite( $this->log_file_handle, '=== HTTP Request Log Started at ' . gmdate( 'Y-m-d H:i:s' ) . " ===\n" );

				// Ensure we close the file handle on shutdown.
				register_shutdown_function(
					function () {
						if ( $this->log_file_handle ) {
							fwrite( $this->log_file_handle, '=== HTTP Request Log Ended at ' . gmdate( 'Y-m-d H:i:s' ) . " ===\n" );
							fclose( $this->log_file_handle );
						}
					}
				);
			}
		}

		// Register the WordPress hooks that will be applied once WordPress is loaded.
		WP_CLI::add_wp_hook( 'http_request_args', array( $this, 'log_http_request_args' ), 9999, 2 );
		WP_CLI::add_wp_hook( 'http_api_debug', array( $this, 'log_http_api_response' ), 9999, 5 );

		return $state;
	}

	/**
	 * Encode data to JSON with PHP 5.6 compatibility.
	 *
	 * @param mixed $data Data to encode.
	 * @param bool  $pretty Whether to pretty print the JSON.
	 * @return string JSON encoded string.
	 */
	private function json_encode_compat( $data, $pretty = false ) {
		$options = 0;
		if ( $pretty && defined( 'JSON_PRETTY_PRINT' ) ) {
			$options |= JSON_PRETTY_PRINT;
		}

		// Handle PHP < 5.6 compatibility.
		$json = json_encode( $data, $options );
		if ( false === $json ) {
			// If encoding fails, try a simpler version.
			$fallback_json = json_encode( 'Could not encode data' );
			if ( false === $fallback_json ) {
				return '{"error": "JSON encoding failed"}';
			}
			return $fallback_json;
		}

		return $json;
	}

	/**
	 * Log HTTP request arguments when HTTP request is made.
	 *
	 * @param array  $args HTTP request arguments.
	 * @param string $url  Request URL.
	 *
	 * @return array Unmodified request arguments.
	 */
	public function log_http_request_args( $args, $url ) {
		// Only log if debug mode or http_log is enabled.
		$debug    = WP_CLI::get_config( 'debug' );
		$http_log = WP_CLI::get_config( 'http_log' );

		if ( ! $debug && ! $http_log && ! $this->log_file_handle ) {
			return $args;
		}

		$log_group = $http_log ? 'http' : ( true === $debug ? 'http' : $debug );
		$log_level = $http_log ? 'info' : 'debug';

		$log_data = array(
			'method' => isset( $args['method'] ) ? $args['method'] : 'GET',
			'url'    => $url,
		);

		// Only include headers and data in verbose logging mode.
		if ( WP_CLI::get_config( 'http_log_verbose' ) ) {
			// Filter out sensitive headers (like Authorization).
			if ( isset( $args['headers'] ) ) {
				$log_headers = $args['headers'];
				if ( isset( $log_headers['Authorization'] ) ) {
					$log_headers['Authorization'] = 'REDACTED';
				}
				$log_data['headers'] = $log_headers;
			}

			// Only log body if it exists and isn't too large.
			if ( isset( $args['body'] ) && is_string( $args['body'] ) && strlen( $args['body'] ) < 1024 ) {
				$log_data['body'] = $args['body'];
			} elseif ( isset( $args['body'] ) ) {
				$log_data['body'] = '[body too large to log]';
			}
		}

		$method      = isset( $args['method'] ) ? $args['method'] : 'GET';
		$log_message = "WordPress HTTP Request: {$method} {$url}";

		if ( 'debug' === $log_level ) {
			WP_CLI::debug( $log_message . ' ' . $this->json_encode_compat( $log_data ), $log_group );
		} else {
			WP_CLI::log( $log_message );
		}

		// Write to log file if enabled.
		if ( $this->log_file_handle ) {
			$timestamp = gmdate( 'Y-m-d H:i:s' );
			$log_entry = "[{$timestamp}] REQUEST: {$method} {$url}\n";
			$log_entry .= $this->json_encode_compat( $log_data, true ) . "\n\n";
			fwrite( $this->log_file_handle, $log_entry );
		}

		return $args;
	}

	/**
	 * Log HTTP API response.
	 *
	 * @param array|\WP_Error $response HTTP response or WP_Error object.
	 * @param string          $context  Context under which the hook is fired.
	 * @param string          $transport HTTP transport used.
	 * @param array           $args     HTTP request arguments.
	 * @param string          $url      The request URL.
	 */
	public function log_http_api_response( $response, $context, $transport, $args, $url ) {
		// Only log if debug mode or http_log is enabled.
		$debug    = WP_CLI::get_config( 'debug' );
		$http_log = WP_CLI::get_config( 'http_log' );

		if ( ! $debug && ! $http_log && ! $this->log_file_handle ) {
			return;
		}

		$log_group = $http_log ? 'http' : ( true === $debug ? 'http' : $debug );
		$log_level = $http_log ? 'info' : 'debug';

		$method = isset( $args['method'] ) ? $args['method'] : 'GET';

		if ( is_wp_error( $response ) ) {
			$log_data = array(
				'error_code'    => $response->get_error_code(),
				'error_message' => $response->get_error_message(),
			);

			$log_message = "WordPress HTTP Response Error: {$method} {$url} - " . $response->get_error_message();
		} else {
			$log_data = array(
				'status'  => isset( $response['response']['code'] ) ? $response['response']['code'] : '?',
				'success' => isset( $response['response']['code'] ) && 200 <= $response['response']['code'] && $response['response']['code'] < 300,
			);

			// Only include headers and body in verbose logging mode.
			if ( WP_CLI::get_config( 'http_log_verbose' ) ) {
				if ( isset( $response['headers'] ) ) {
					$log_data['headers'] = $response['headers'];
				}

				// Only log body if it's not too large.
				if ( isset( $response['body'] ) && is_string( $response['body'] ) && strlen( $response['body'] ) < 1024 ) {
					$log_data['body'] = $response['body'];
				} elseif ( isset( $response['body'] ) ) {
					$log_data['body'] = '[body too large to log]';
				}
			}

			$log_message = "WordPress HTTP Response: {$log_data['status']} for {$method} {$url}";
		}

		if ( 'debug' === $log_level ) {
			WP_CLI::debug( $log_message . ' ' . $this->json_encode_compat( $log_data ), $log_group );
		} else {
			WP_CLI::log( $log_message );
		}

		// Write to log file if enabled.
		if ( $this->log_file_handle ) {
			$timestamp  = gmdate( 'Y-m-d H:i:s' );
			$log_entry  = "[{$timestamp}] RESPONSE: {$method} {$url}\n";
			$log_entry .= $this->json_encode_compat( $log_data, true ) . "\n\n";
			fwrite( $this->log_file_handle, $log_entry );
		}
	}
}
