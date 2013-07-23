<?php

class UnserializeReplaceTest extends PHPUnit_Framework_TestCase {

	function testPrivateConstructor() {
		$old_obj = ClassWithPrivateConstructor::get_instance();

		$new_obj = WP_CLI\Utils\recursive_unserialize_replace( 'foo', 'bar', $old_obj );
		$this->assertEquals( 'bar', $new_obj->prop );
	}
}


class ClassWithPrivateConstructor {

	public $prop = 'foo';

	private function __construct() {}

	public static function get_instance() {
		return new self;
	}
}

