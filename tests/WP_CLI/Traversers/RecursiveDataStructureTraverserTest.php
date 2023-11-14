<?php

namespace WP_CLI\Tests\Traverser;

use WP_CLI\Tests\TestCase;
use WP_CLI\Traverser\RecursiveDataStructureTraverser;

class RecursiveDataStructureTraverserTest extends TestCase {

	public function test_it_can_get_a_top_level_array_value() {
		$array = array(
			'foo' => 'bar',
		);

		$traverser = new RecursiveDataStructureTraverser( $array );

		$this->assertEquals( 'bar', $traverser->get( 'foo' ) );
	}

	public function test_it_can_get_a_top_level_object_value() {
		$object = (object) array(
			'foo' => 'bar',
		);

		$traverser = new RecursiveDataStructureTraverser( $object );

		$this->assertEquals( 'bar', $traverser->get( 'foo' ) );
	}

	public function test_it_can_get_a_nested_array_value() {
		$array = array(
			'foo' => array(
				'bar' => array(
					'baz' => 'value',
				),
			),
		);

		$traverser = new RecursiveDataStructureTraverser( $array );

		$this->assertEquals( 'value', $traverser->get( array( 'foo', 'bar', 'baz' ) ) );
	}

	public function test_it_can_get_a_nested_object_value() {
		$object = (object) array(
			'foo' => (object) array(
				'bar' => 'baz',
			),
		);

		$traverser = new RecursiveDataStructureTraverser( $object );

		$this->assertEquals( 'baz', $traverser->get( array( 'foo', 'bar' ) ) );
	}

	public function test_it_can_set_a_nested_array_value() {
		$array = array(
			'foo' => array(
				'bar' => 'baz',
			),
		);
		$this->assertEquals( 'baz', $array['foo']['bar'] );

		$traverser = new RecursiveDataStructureTraverser( $array );
		$traverser->update( array( 'foo', 'bar' ), 'new' );

		$this->assertEquals( 'new', $array['foo']['bar'] );
	}

	public function test_it_can_set_a_nested_object_value() {
		$object = (object) array(
			'foo' => (object) array(
				'bar' => 'baz',
			),
		);
		$this->assertEquals( 'baz', $object->foo->bar );

		$traverser = new RecursiveDataStructureTraverser( $object );
		$traverser->update( array( 'foo', 'bar' ), 'new' );

		$this->assertEquals( 'new', $object->foo->bar );
	}

	public function test_it_can_update_an_integer_object_value() {
		$object = (object) array(
			'test_mode' => 0,
		);
		$this->assertEquals( 0, $object->test_mode );

		$traverser = new RecursiveDataStructureTraverser( $object );
		$traverser->update( array( 'test_mode' ), 1 );

		$this->assertEquals( 1, $object->test_mode );
	}

	public function test_it_can_delete_a_nested_array_value() {
		$array = array(
			'foo' => array(
				'bar' => 'baz',
			),
		);
		$this->assertArrayHasKey( 'bar', $array['foo'] );

		$traverser = new RecursiveDataStructureTraverser( $array );
		$traverser->delete( array( 'foo', 'bar' ) );

		$this->assertArrayNotHasKey( 'bar', $array['foo'] );
	}

	public function test_it_can_delete_a_nested_object_value() {
		$object = (object) array(
			'foo' => (object) array(
				'bar' => 'baz',
			),
		);
		$this->assertObjectHasProperty( 'bar', $object->foo );

		$traverser = new RecursiveDataStructureTraverser( $object );
		$traverser->delete( array( 'foo', 'bar' ) );

		$this->assertObjectNotHasProperty( 'bar', $object->foo );
	}

	public function test_it_can_insert_a_key_into_a_nested_array() {
		$array = array(
			'foo' => array(
				'bar' => 'baz',
			),
		);

		$traverser = new RecursiveDataStructureTraverser( $array );
		$traverser->insert( array( 'foo', 'new' ), 'new value' );

		$this->assertArrayHasKey( 'new', $array['foo'] );
		$this->assertEquals( 'new value', $array['foo']['new'] );
	}

	public function test_it_throws_an_exception_when_attempting_to_create_a_key_on_an_invalid_type() {
		$data      = 'a string';
		$traverser = new RecursiveDataStructureTraverser( $data );

		try {
			$traverser->insert( array( 'key' ), 'value' );
		} catch ( \Exception $e ) {
			$this->assertSame( 'a string', $data );
			return;
		}

		$this->fail( 'Failed to assert that an exception was thrown when inserting a key into a string.' );
	}
}
