<?php

require_once getcwd() . '/php/class-wp-cli.php';
require_once getcwd() . '/php/class-wp-cli-command.php';
require_once getcwd() . '/php/commands/search-replace.php';

class UnserializeReplaceTest extends PHPUnit_Framework_TestCase {

	function testReplaceString() {
		$orig = 'foo';
		$replacement = WP_CLI\Utils\recursive_unserialize_replace( 'foo', 'bar', $orig );
		$this->assertEquals( 'bar', $replacement );
	}

	function testPrivateConstructor() {
		$old_obj = ClassWithPrivateConstructor::get_instance();

		$new_obj = WP_CLI\Utils\recursive_unserialize_replace( 'foo', 'bar', $old_obj, false, true );
		$this->assertEquals( 'bar', $new_obj->prop );
	}

	function testObjectLoop() {
		$old_object = new stdClass();
		$old_object->prop = 'foo';
		$old_object->self = $old_object;

		$new_obj = WP_CLI\Utils\recursive_unserialize_replace( 'foo', 'bar', $old_object, false, true );
		$this->assertEquals( 'bar', $new_obj->prop );
	}

	function testArrayLoop() {
		$old_array = array( 'prop' => 'foo' );
		$old_array['self'] = &$old_array;

		$new_array = WP_CLI\Utils\recursive_unserialize_replace( 'foo', 'bar', $old_array, false, true );
		$this->assertEquals( 'bar', $new_array['prop'] );
	}

	function testMixedObjectArrayLoop() {
		$old_object = new stdClass();
		$old_object->prop = 'foo';
		$old_object->array = array('prop' => 'foo');
		$old_object->array['object'] = $old_object;

		$new_object = WP_CLI\Utils\recursive_unserialize_replace( 'foo', 'bar', $old_object, false, true );
		$this->assertEquals( 'bar', $new_object->prop );
		$this->assertEquals( 'bar', $new_object->array['prop']);
	}

	function testMixedArrayObjectLoop() {
		$old_array = array( 'prop' => 'foo', 'object' => new stdClass() );
		$old_array['object']->prop = 'foo';
		$old_array['object']->array = &$old_array;

		$new_array = WP_CLI\Utils\recursive_unserialize_replace( 'foo', 'bar', $old_array, false, true );
		$this->assertEquals( 'bar', $new_array['prop'] );
		$this->assertEquals( 'bar', $new_array['object']->prop);
	}
}


class ClassWithPrivateConstructor {

	public $prop = 'foo';

	private function __construct() {}

	public static function get_instance() {
		return new self;
	}
}
