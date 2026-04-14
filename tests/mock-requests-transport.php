<?php

use WpOrg\Requests\Transport;

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound
class Mock_Requests_Transport implements Transport {
	public $requests = [];

	public function request( $url, $headers = [], $data = [], $options = [] ) {
		// Simulate retrying without SSL verification when a custom (bad) cert file is used.
		// Use realpath() to normalize paths so that 8.3 short paths on Windows
		// (e.g., RUNNER~1) compare correctly against long paths from tempnam().
		$normalized_verify  = isset( $options['verify'] ) && is_string( $options['verify'] ) ? realpath( $options['verify'] ) : false;
		$normalized_tmp_dir = realpath( sys_get_temp_dir() );
		if (
			isset( $options['insecure'] )
			&& $options['insecure']
			&& false !== $normalized_verify
			&& false !== $normalized_tmp_dir
			&& 0 === strpos( $normalized_verify, $normalized_tmp_dir . DIRECTORY_SEPARATOR )
		) {
			$options['verify'] = false;
		}

		$this->requests[] = compact( 'url', 'headers', 'data', 'options' );

		return 'HTTP/1.1 418' . "\r\n"
			. 'Content-Type: water/leaf-infused' . "\r\n"
			. "\r\n\r\n"; // This last line is actually important or the request will error.
	}

	public function request_multiple( $requests, $options ) {
		throw new Exception( 'Method not implemented: ' . __METHOD__ );
	}

	public static function test( $capabilities = [] ) {
		return true;
	}
}
