<?php

use WP_CLI\Request;

class RequestTest extends PHPUnit_Framework_TestCase {

	public function testArgParse() {
		$argv = array(
			'--user=wpcli',
			'shell',
		);
		$request = new Request( $argv );
		$this->assertEquals( 'shell', $request->get_arg( 0 ) );
		$this->assertEquals( 'wpcli', $request->get_arg( 'user' ) );
	}

	public function testDefaultRuntimeArgs() {
		$argv = array(
			'shell',
		);
		$request = new Request( $argv );
		$this->assertEquals( array(
			'color'        => 'auto',
			'skip-plugins' => '',
			'skip-themes'  => '',
			'require'      => array(),
			'disabled_commands' => array(),
			'debug'        => false,
			'prompt'       => false,
			'quiet'        => false,
			'apache_modules' => array(),
		), $request->get_runtime_args() );
	}

	public function testMultipleRuntimeArgs() {
		$argv = array(
			'--require=foo.php',
			'--require=bar.php',
			'--user=foo',
			'--user=bar',
		);
		$request = new Request( $argv );
		$runtime_args = $request->get_runtime_args();
		$this->assertEquals( array( 'foo.php', 'bar.php' ), $runtime_args['require'] );
		$this->assertEquals( array( 'foo.php', 'bar.php' ), $request['require'] );
		$this->assertEquals( 'bar', $runtime_args['user'] );
		$this->assertEquals( 'bar', $request['user'] );
	}

	public function testRuntimeArgPriorityOverAssoc() {
		$argv = array(
			'--user=wpcli',
			'shell',
			'--user=burrito',
		);
		$request = new Request( $argv );
		$this->assertEquals( 'wpcli', $request['user'] );
		$this->assertEquals( 'wpcli', $request->get_runtime_arg( 'user' ) );
		$this->assertEquals( 'burrito', $request->get_assoc_arg( 'user' ) );
	}

	public function testArrayAccessOffsetExists() {
		$argv = array(
			'--user=wpcli',
			'shell',
			'--bar=burrito',
		);
		$request = new Request( $argv );
		$this->assertTrue( isset( $request[0] ) );
		$this->assertEquals( 'shell', $request[0] );
		$this->assertTrue( isset( $request['user'] ) );
		$this->assertEquals( 'wpcli', $request['user'] );
		$this->assertTrue( isset( $request['bar'] ) );
		$this->assertEquals( 'burrito', $request['bar'] );
		$this->assertFalse( isset( $request[1] ) );
		$this->assertFalse( isset( $request['foo'] ) );
	}

	public function testArrayAccessUnsetOffset() {
		$argv = array(
			'--user=daniel',
			'shell',
			'--bar=burrito',
			'--user=wpcli',
		);
		$request = new Request( $argv );
		$this->assertTrue( isset( $request[0] ) );
		$this->assertTrue( isset( $request['user'] ) );
		$this->assertTrue( isset( $request['bar'] ) );
		unset( $request[0] );
		unset( $request['user'] ); // should offset both runtime and positional
		$this->assertFalse( isset( $request[0] ) );
		$this->assertFalse( isset( $request['user'] ) );
		$this->assertTrue( isset( $request['bar'] ) );
	}

}
