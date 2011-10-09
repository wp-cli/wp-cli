<?php

/**
 * Wrapper class for WP-CLI
 *
 * @package wp-cli
 * @author Andreas Creten
 */
class WP_CLI {
	static $commands = array();

	/**
	 * Add a command to the wp-cli list of commands
	 *
	 * @param string $name The name of the command that will be used in the cli
	 * @param string $class The class to manage the command
	 * @return void
	 */
	public function addCommand($name, $class) {
		self::$commands[$name] = $class;
	}

	/**
	 * Display a message in the cli
	 *
	 * @param string $message
	 * @return void
	 */
	static function out($message) {
		\cli\out($message);
	}

	/**
	 * Display a message in the CLI and end with a newline
	 *
	 * @param string $message
	 * @return void
	 */
	static function line($message = '') {
		\cli\line($message);
	}

	/**
	 * Display an error in the CLI and end with a newline
	 *
	 * @param string $message
	 * @param string $label
	 * @return void
	 */
	static function error($message, $label = 'Error') {
		\cli\line('%R'.$label.': %n'.self::errorToString($message));
	}

	/**
	 * Display a success in the CLI and end with a newline
	 *
	 * @param string $message
	 * @param string $label
	 * @return void
	 */
	static function success($message, $label = 'Success') {
		\cli\line('%G'.$label.': %n'.$message);
	}

	/**
	 * Display a warning in the CLI and end with a newline
	 *
	 * @param string $message
	 * @param string $label
	 * @return void
	 */
	static function warning($message, $label = 'Warning') {
		\cli\line('%C'.$label.': %n'.$message);
	}

	/**
	 * Convert a wp_error into a String
	 *
	 * @param mixed $errors
	 * @return string
	 */
	static function errorToString($errors) {
		if(is_string($errors)){
			return $errors;
		}
		elseif(is_wp_error($errors) && $errors->get_error_code()){
			foreach($errors->get_error_messages() as $message){
				if($errors->get_error_data() )
					return $message . ' ' . $errors->get_error_data();
				else
					return $message;
			}
		}
	}

	/**
	 * Splits a string into positional and associative arguments.
	 *
	 * @param string
	 * @return array
	 */
	static function parse_args( $arguments ) {
		$regular_args = array();
		$assoc_args = array();

		foreach ( $arguments as $arg ) {
			if ( preg_match( '|^--(\w+)$|', $arg, $matches ) ) {
				$assoc_args[ $matches[1] ] = true;
			} elseif ( preg_match( '|^--(\w+)=(.+)|', $arg, $matches ) ) {
				$assoc_args[ $matches[1] ] = $matches[2];
			} else {
				$regular_args[] = $arg;
			}
		}

		return array( $regular_args, $assoc_args );
	}

	/**
	 * Composes positional and associative arguments into a string
	 *
	 * @param array
	 * @return string
	 */
	static function compose_args( $args, $assoc_args = array() ) {
		$str = implode( ' ', $args );

		foreach ( $assoc_args as $key => $value ) {
			if ( true == $value )
				$str .= " --$key";
			else
				$str .= " --$key=$value";
		}

		return $str;
	}

	/**
	 * Display a legend
	 *
	 * @param array( code => title ) $legend
	 * @return void
	 */
	static function legend($legend) {
		$legend['%yU'] = 'Update Available';

		$legend_line = array();
		foreach ( $legend as $key => $title )
			$legend_line[] = "$key = $title%n";

		WP_CLI::line( 'Legend: ' . implode( ', ', $legend_line ) );
	}

	/**
	 * Return the beginning of the status line for a certain plugin or theme
	 *
	 * @param string $item The plugin or theme name
	 * @param string $key The transient key
	 *
	 * @return string
	 */
	static function get_update_status( $item, $key ) {
		$update_list = get_site_transient( $key );

		if ( isset( $update_list->response[ $item ] ) )
			return true;

		return false;
	}
}

/**
 * A Upgrader Skin for WordPress that only generates plain-text
 *
 * @package wp-cli
 * @author Andreas Creten
 */
class CLI_Upgrader_Skin {
	var $upgrader;
	var $done_header = false;
	var $result = false;

	function __construct($args = array()) {
		$defaults = array('url' => '', 'nonce' => '', 'title' => '', 'context' => false);
		$this->options = wp_parse_args($args, $defaults);
	}

	function set_upgrader(&$upgrader) {
		if(is_object($upgrader)) {
			$this->upgrader =& $upgrader;
		}

		$this->add_strings();
	}

	function add_strings() {}

	function set_result($result) {
		$this->result = $result;
	}

	function request_filesystem_credentials($error = false) {
		$url = $this->options['url'];
		$context = $this->options['context'];
		if(!empty($this->options['nonce'])) {
			$url = wp_nonce_url($url, $this->options['nonce']);
		}

		// Possible to bring inline, Leaving as is for now.
		return request_filesystem_credentials($url, '', $error, $context);
	}

	function header() {}
	function footer() {}

	function error($errors) {
		$this->feedback(WP_CLI::errorToString($errors));
	}

	function feedback($string) {
		if(isset( $this->upgrader->strings[$string]))
			$string = $this->upgrader->strings[$string];

		if(strpos($string, '%') !== false) {
			$args = func_get_args();
			$args = array_splice($args, 1);
			if(!empty($args)) {
				$string = vsprintf($string, $args);
			}

		}
		if(empty($string)) {
			return;
		}

		echo $string;
	}

	function before() {}
	function after() {}
}
