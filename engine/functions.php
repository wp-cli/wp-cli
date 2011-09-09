<?php

function error_to_string($errors) {
	if ( is_string($errors) ) {
		return $errors;
	}
	elseif ( is_wp_error($errors) && $errors->get_error_code() ) {
		foreach ( $errors->get_error_messages() as $message ) {
			if ( $errors->get_error_data() )
				return $message . ' ' . $errors->get_error_data();
			else
				return $message;
		}
	}
}

class CLI_Upgrader_Skin {
	var $upgrader;
	var $done_header = false;
	var $result = false;

	function __construct($args = array()) {
		$defaults = array( 'url' => '', 'nonce' => '', 'title' => '', 'context' => false );
		$this->options = wp_parse_args($args, $defaults);
	}

	function set_upgrader(&$upgrader) {
		if ( is_object($upgrader) )
			$this->upgrader =& $upgrader;
		$this->add_strings();
	}

	function add_strings() {}

	function set_result($result) {
		$this->result = $result;
	}

	function request_filesystem_credentials($error = false) {
		$url = $this->options['url'];
		$context = $this->options['context'];
		if ( !empty($this->options['nonce']) )
			$url = wp_nonce_url($url, $this->options['nonce']);
		return request_filesystem_credentials($url, '', $error, $context); //Possible to bring inline, Leaving as is for now.
	}

	function header() {}
	function footer() {}

	function error($errors) {
		$this->feedback(error_to_string($errors));
	}

	function feedback($string) {
		if ( isset( $this->upgrader->strings[$string] ) )
			$string = $this->upgrader->strings[$string];

		if ( strpos($string, '%') !== false ) {
			$args = func_get_args();
			$args = array_splice($args, 1);
			if ( !empty($args) )
				$string = vsprintf($string, $args);
		}
		if ( empty($string) )
			return;
		
		echo $string;
	}
	function before() {}
	function after() {}
}