<?php

class WP_CLI {
	static $commands = array();
	
	public function addCommand($name, $class) {
		self::$commands[$name] = $class;
	}
}