<?php

use WP_CLI\Configurator;
use WP_CLI\Tests\TestCase;

class ConfiguratorTest extends TestCase {

	public function testExtractAssoc() {
		$args = Configurator::extract_assoc( [ 'foo', '--bar', '--baz=text' ] );

		$this->assertCount( 1, $args[0] );
		$this->assertCount( 2, $args[1] );

		$this->assertEquals( 'foo', $args[0][0] );

		$this->assertEquals( 'bar', $args[1][0][0] );
		$this->assertTrue( $args[1][0][1] );

		$this->assertEquals( 'baz', $args[1][1][0] );
		$this->assertEquals( 'text', $args[1][1][1] );

	}

	public function testExtractAssocNoValue() {
		$args = Configurator::extract_assoc( [ 'foo', '--bar=', '--baz=text' ] );

		$this->assertCount( 1, $args[0] );
		$this->assertCount( 2, $args[1] );

		$this->assertEquals( 'foo', $args[0][0] );

		$this->assertEquals( 'bar', $args[1][0][0] );
		$this->assertEmpty( $args[1][0][1] );

		$this->assertEquals( 'baz', $args[1][1][0] );
		$this->assertEquals( 'text', $args[1][1][1] );

	}

	public function testExtractAssocGlobalLocal() {
		$args = Configurator::extract_assoc( [ '--url=foo.dev', '--path=wp', 'foo', '--bar=', '--baz=text', '--url=bar.dev' ] );

		$this->assertCount( 1, $args[0] );
		$this->assertCount( 5, $args[1] );
		$this->assertCount( 2, $args[2] );
		$this->assertCount( 3, $args[3] );

		$this->assertEquals( 'url', $args[2][0][0] );
		$this->assertEquals( 'foo.dev', $args[2][0][1] );
		$this->assertEquals( 'url', $args[3][2][0] );
		$this->assertEquals( 'bar.dev', $args[3][2][1] );
	}

	public function testExtractAssocDoubleDashInValue() {
		$args = Configurator::extract_assoc( [ '--test=text--text' ] );

		$this->assertCount( 0, $args[0] );
		$this->assertCount( 1, $args[1] );

		$this->assertEquals( 'test', $args[1][0][0] );
		$this->assertEquals( 'text--text', $args[1][0][1] );
	}
}
