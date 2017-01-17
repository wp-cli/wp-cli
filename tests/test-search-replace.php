<?php

class UnserializeReplaceTest extends PHPUnit_Framework_TestCase {

	private static function recursive_unserialize_replace( $from, $to, $data, $serialised = false, $recurse_objects = false ) {
		$replacer = new \WP_CLI\SearchReplacer( $from, $to, $recurse_objects );
		return $replacer->run( $data, $serialised );
	}

	function testReplaceString() {
		$orig = 'foo';
		$replacement = self::recursive_unserialize_replace( 'foo', 'bar', $orig );
		$this->assertEquals( 'bar', $replacement );
	}

	function testPrivateConstructor() {
		$old_obj = ClassWithPrivateConstructor::get_instance();

		$new_obj = self::recursive_unserialize_replace( 'foo', 'bar', $old_obj, false, true );
		$this->assertEquals( 'bar', $new_obj->prop );
	}

	function testObjectLoop() {
		$old_object = new stdClass();
		$old_object->prop = 'foo';
		$old_object->self = $old_object;

		$new_obj = self::recursive_unserialize_replace( 'foo', 'bar', $old_object, false, true );
		$this->assertEquals( 'bar', $new_obj->prop );
	}

	function testArrayLoop() {
		$old_array = array( 'prop' => 'foo' );
		$old_array['self'] = &$old_array;

		$new_array = self::recursive_unserialize_replace( 'foo', 'bar', $old_array, false, true );
		$this->assertEquals( 'bar', $new_array['prop'] );
	}

	function testArraySameValues() {
		$old_array = array(
			'prop1' => array(
				'foo',
			),
			'prop2' => array(
				'foo',
			),
		);
		$new_array = self::recursive_unserialize_replace( 'foo', 'bar', $old_array, false, true );
		$this->assertEquals( 'bar', $new_array['prop1'][0] );
		$this->assertEquals( 'bar', $new_array['prop2'][0] );
	}

	function testMixedObjectArrayLoop() {
		$old_object = new stdClass();
		$old_object->prop = 'foo';
		$old_object->array = array('prop' => 'foo');
		$old_object->array['object'] = $old_object;

		$new_object = self::recursive_unserialize_replace( 'foo', 'bar', $old_object, false, true );
		$this->assertEquals( 'bar', $new_object->prop );
		$this->assertEquals( 'bar', $new_object->array['prop']);
	}

	function testMixedArrayObjectLoop() {
		$old_array = array( 'prop' => 'foo', 'object' => new stdClass() );
		$old_array['object']->prop = 'foo';
		$old_array['object']->array = &$old_array;

		$new_array = self::recursive_unserialize_replace( 'foo', 'bar', $old_array, false, true );
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
