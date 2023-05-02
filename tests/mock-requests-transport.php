<?php

use WpOrg\Requests\Transport;

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound
class Mock_Requests_Transport implements Transport {
	public $requests = [];

	public function request( $url, $headers = [], $data = [], $options = [] ) {
		// Simulate retrying.
		if (
			isset( $options['insecure'] )
			&& $options['insecure']
			&& isset( $options['verify'] )
			&& false !== strpos( $options['verify'], sys_get_temp_dir() )
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
