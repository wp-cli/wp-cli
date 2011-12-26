<?php

/**
 * A Upgrader Skin for WordPress that only generates plain-text
 *
 * @package wp-cli
 */
class CLI_Upgrader_Skin {
	var $upgrader;
	var $done_header = false;
	var $result = false;

	function __construct( $args = array() ) {
		$defaults = array( 'url' => '', 'nonce' => '', 'title' => '', 'context' => false );
		$this->options = wp_parse_args( $args, $defaults );
	}

	function set_upgrader( &$upgrader ) {
		if( is_object( $upgrader ) ) {
			$this->upgrader =& $upgrader;
		}

		$this->add_strings();
	}

	function add_strings() {}

	function set_result( $result ) {
		$this->result = $result;
	}

	function request_filesystem_credentials( $error = false ) {
		$url = $this->options['url'];
		$context = $this->options['context'];
		if( !empty( $this->options['nonce'] ) ) {
			$url = wp_nonce_url( $url, $this->options['nonce']) ;
		}

		// Possible to bring inline, Leaving as is for now.
		return request_filesystem_credentials( $url, '', $error, $context );
	}

	function header() {}
	function footer() {}

	function error( $errors ) {
		$this->feedback( WP_CLI::errorToString($errors) );
	}

	function feedback( $string ) {
		if(isset( $this->upgrader->strings[$string] ) )
			$string = $this->upgrader->strings[$string];

		if( strpos( $string, '%' ) !== false ) {
			$args = func_get_args();
			$args = array_splice( $args, 1 );
			if( !empty( $args ) ) {
				$string = vsprintf( $string, $args );
			}

		}

		if( empty( $string ) ) {
			return;
		}

		echo $string;
	}

	function before() {}
	function after() {}
}

