<?php

use WP_CLI\Configurator;
use WP_CLI\Loggers;
use WP_CLI\Tests\TestCase;

class ConfiguratorTest extends TestCase {

	public function testExtractAssoc(): void {
		$args = Configurator::extract_assoc( [ 'foo', '--bar', '--baz=text' ] );

		$this->assertCount( 1, $args[0] );
		$this->assertCount( 2, $args[1] );

		$this->assertEquals( 'foo', $args[0][0] );

		$this->assertEquals( 'bar', $args[1][0][0] );
		$this->assertTrue( $args[1][0][1] );

		$this->assertEquals( 'baz', $args[1][1][0] );
		$this->assertEquals( 'text', $args[1][1][1] );
	}

	public function testExtractAssocNoValue(): void {
		$args = Configurator::extract_assoc( [ 'foo', '--bar=', '--baz=text' ] );

		$this->assertCount( 1, $args[0] );
		$this->assertCount( 2, $args[1] );

		$this->assertEquals( 'foo', $args[0][0] );

		$this->assertEquals( 'bar', $args[1][0][0] );
		$this->assertEmpty( $args[1][0][1] );

		$this->assertEquals( 'baz', $args[1][1][0] );
		$this->assertEquals( 'text', $args[1][1][1] );
	}

	public function testExtractAssocGlobalLocal(): void {
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

	public function testExtractAssocDoubleDashInValue(): void {
		$args = Configurator::extract_assoc( [ '--test=text--text' ] );

		$this->assertCount( 0, $args[0] );
		$this->assertCount( 1, $args[1] );

		$this->assertEquals( 'test', $args[1][0][0] );
		$this->assertEquals( 'text--text', $args[1][0][1] );
	}

	public function testExtractAssocDoubleDashDelimiter(): void {
		// Arguments after `--` should be treated as positional.
		$args = Configurator::extract_assoc( [ 'foo', '--bar', '--', '--baz=text' ] );

		$this->assertCount( 2, $args[0] );
		$this->assertCount( 1, $args[1] );

		$this->assertEquals( 'foo', $args[0][0] );
		$this->assertEquals( '--baz=text', $args[0][1] );

		$this->assertEquals( 'bar', $args[1][0][0] );
		$this->assertTrue( $args[1][0][1] );
	}

	public function testExtractAssocDoubleDashDelimiterWithGlobalAssoc(): void {
		// Global assoc args before `--` should still be captured.
		$args = Configurator::extract_assoc( [ '--url=foo.dev', 'command', '--', '--require=/blah' ] );

		$this->assertCount( 2, $args[0] );
		$this->assertCount( 1, $args[1] );
		$this->assertCount( 1, $args[2] );
		$this->assertCount( 0, $args[3] );

		$this->assertEquals( 'command', $args[0][0] );
		$this->assertEquals( '--require=/blah', $args[0][1] );

		$this->assertEquals( 'url', $args[2][0][0] );
		$this->assertEquals( 'foo.dev', $args[2][0][1] );
	}

	public function testExtractAssocDoubleDashDelimiterAtStart(): void {
		// `--` at the beginning should make all following args positional.
		$args = Configurator::extract_assoc( [ '--', 'command', '--option=value' ] );

		$this->assertCount( 2, $args[0] );
		$this->assertCount( 0, $args[1] );
		$this->assertCount( 0, $args[2] );
		$this->assertCount( 0, $args[3] );

		$this->assertEquals( 'command', $args[0][0] );
		$this->assertEquals( '--option=value', $args[0][1] );
	}

	public function testExtractAssocDoubleDashDelimiterMultipleArgs(): void {
		// Multiple option-like arguments after `--` should all be positional.
		$args = Configurator::extract_assoc( [ 'option', 'get', 'home', '--', '--require=/blah', '--no-color' ] );

		$this->assertCount( 5, $args[0] );
		$this->assertCount( 0, $args[1] );

		$this->assertEquals( 'option', $args[0][0] );
		$this->assertEquals( 'get', $args[0][1] );
		$this->assertEquals( 'home', $args[0][2] );
		$this->assertEquals( '--require=/blah', $args[0][3] );
		$this->assertEquals( '--no-color', $args[0][4] );
	}

	/**
	 * WP_CLI::get_config does not show warnings for null values.
	 */
	public function testNullGetConfig(): void {
		// Init config so there is a config to check.
		$runner = WP_CLI::get_runner();
		$runner->init_config();

		// Previous.
		$prev_logger = WP_CLI::get_logger();

		$logger = new Loggers\Execution();
		WP_CLI::set_logger( $logger );

		$has_config = WP_CLI::has_config( 'url' );
		$get_config = WP_CLI::get_config( 'url' );

		$this->assertTrue( $has_config, 'has_config() is not true' );
		$this->assertTrue( false === strpos( $logger->stderr, 'Warning' ), 'Logger contains a "Warning"' );
		$this->assertNull( $get_config, 'get_config() is not null' );

		// Restore.
		WP_CLI::set_logger( $prev_logger );
	}
}
