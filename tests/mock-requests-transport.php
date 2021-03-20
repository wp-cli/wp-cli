<?php

class Mock_Requests_Transport implements Requests_Transport {
	public $requests = array();

	public function request( $url, $headers = array(), $data = array(), $options = array() ) {
		$this->requests[] = compact( 'url', 'headers', 'data', 'options' );

		return 'HTTP/1.1 418' . "\r\n"
			. 'Content-Type: water/leaf-infused' . "\r\n"
			. "\r\n\r\n"; // This last line is actually important or the request will error.
	}

	public function request_multiple( $requests, $options ) {
		throw new Exception( 'Method not implemented: ' . __METHOD__ );
	}

	public static function test() {
		return true;
	}
}
