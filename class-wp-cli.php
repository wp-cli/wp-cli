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
	 * @author Andreas Creten
	 */
	public function addCommand($name, $class) {
		self::$commands[$name] = $class;
	}
	
	/**
	 * Display a message in the cli
	 *
	 * @param string $message 
	 * @return void
	 * @author Andreas Creten
	 */
	static function out($message) {
		\cli\out($message);
	}
	
	/**
	 * Display a message in the CLI and end with a newline
	 *
	 * @param string $message 
	 * @return void
	 * @author Andreas Creten
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
	 * @author Andreas Creten
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
	 * @author Andreas Creten
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
	 * @author Andreas Creten
	 */
	static function warning($message, $label = 'Warning') {
		\cli\line('%C'.$label.': %n'.$message);
	}
	
	/**
	 * Convert a wp_error into a String
	 *
	 * @param mixed $errors 
	 * @return string
	 * @author Andreas Creten
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
	 * Display the help function for the wp-cli
	 *
	 * @return void
	 * @author Andreas Creten
	 */
	static function generalHelp() {
		self::line('Example usage:');
		foreach(self::$commands as $name => $command) {
			self::out('    wp '.$name);
			$methods = WP_CLI_Command::getMethods($command);
			if(!empty($methods)) {
				self::out(' ['.implode('|', $methods).']');
			}
			self::line(' ...');
		}
	}
}

/**
 * A Upgrader Skin for Wordpress that only generates plain-text
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
     	
        return request_filesystem_credentials($url, '', $error, $context); //Possible to bring inline, Leaving as is for now.
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