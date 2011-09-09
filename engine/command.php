<?php

class WP_CLI {
	static $commands = array();
	
	function add_command($name, $class) {
		self::$commands[$name] = $class;
	}
}

class WP_Cli_Command {
	function __construct($args) {
		$sub_command = array_shift($args);
		
		if(method_exists($this, $sub_command)) {
			$this->$sub_command($args);
		}
		else {
			$this->help($args);
		}
	}
	
	public function help() {
		print_r(get_class_methods($this));
	}
	
	protected function _echo($string) {
		echo $string."\n";
	}
}