<?php

use WP_CLI\Configurator;
use WP_CLI\Loggers;
use WP_CLI\Tests\TestCase;

class ConfiguratorTest extends TestCase {

	public function testExtractAssoc(): void {
		$args = Configurator::extract_assoc( [ 'foo', '--bar', '--baz=text' ] );

		$this->assertCount( 1, $args[0] );
		$this->assertCount( 2, $args[1] );

		$this->assertSame( 'foo', $args[0][0] );

		$this->assertSame( 'bar', $args[1][0][0] );
		$this->assertTrue( $args[1][0][1] );

		$this->assertSame( 'baz', $args[1][1][0] );
		$this->assertSame( 'text', $args[1][1][1] );
	}

	public function testExtractAssocNoValue(): void {
		$args = Configurator::extract_assoc( [ 'foo', '--bar=', '--baz=text' ] );

		$this->assertCount( 1, $args[0] );
		$this->assertCount( 2, $args[1] );

		$this->assertSame( 'foo', $args[0][0] );

		$this->assertSame( 'bar', $args[1][0][0] );
		$this->assertEmpty( $args[1][0][1] );

		$this->assertSame( 'baz', $args[1][1][0] );
		$this->assertSame( 'text', $args[1][1][1] );
	}

	public function testExtractAssocGlobalLocal(): void {
		$args = Configurator::extract_assoc( [ '--url=foo.dev', '--path=wp', 'foo', '--bar=', '--baz=text', '--url=bar.dev' ] );

		$this->assertCount( 1, $args[0] );
		$this->assertCount( 5, $args[1] );
		$this->assertCount( 2, $args[2] );
		$this->assertCount( 3, $args[3] );

		$this->assertSame( 'url', $args[2][0][0] );
		$this->assertSame( 'foo.dev', $args[2][0][1] );
		$this->assertSame( 'url', $args[3][2][0] );
		$this->assertSame( 'bar.dev', $args[3][2][1] );
	}

	public function testExtractAssocDoubleDashInValue(): void {
		$args = Configurator::extract_assoc( [ '--test=text--text' ] );

		$this->assertCount( 0, $args[0] );
		$this->assertCount( 1, $args[1] );

		$this->assertSame( 'test', $args[1][0][0] );
		$this->assertSame( 'text--text', $args[1][0][1] );
	}

	public function testExtractAssocDoubleDashDelimiter(): void {
		// Arguments after `--` should be treated as positional.
		$args = Configurator::extract_assoc( [ 'foo', '--bar', '--', '--baz=text' ] );

		$this->assertCount( 2, $args[0] );
		$this->assertCount( 1, $args[1] );

		$this->assertSame( 'foo', $args[0][0] );
		$this->assertSame( '--baz=text', $args[0][1] );

		$this->assertSame( 'bar', $args[1][0][0] );
		$this->assertTrue( $args[1][0][1] );
	}

	public function testExtractAssocDoubleDashDelimiterWithGlobalAssoc(): void {
		// Global assoc args before `--` should still be captured.
		$args = Configurator::extract_assoc( [ '--url=foo.dev', 'command', '--', '--require=/blah' ] );

		$this->assertCount( 2, $args[0] );
		$this->assertCount( 1, $args[1] );
		$this->assertCount( 1, $args[2] );
		$this->assertCount( 0, $args[3] );

		$this->assertSame( 'command', $args[0][0] );
		$this->assertSame( '--require=/blah', $args[0][1] );

		$this->assertSame( 'url', $args[2][0][0] );
		$this->assertSame( 'foo.dev', $args[2][0][1] );
	}

	public function testExtractAssocDoubleDashDelimiterAtStart(): void {
		// `--` at the beginning should make all following args positional.
		$args = Configurator::extract_assoc( [ '--', 'command', '--option=value' ] );

		$this->assertCount( 2, $args[0] );
		$this->assertCount( 0, $args[1] );
		$this->assertCount( 0, $args[2] );
		$this->assertCount( 0, $args[3] );

		$this->assertSame( 'command', $args[0][0] );
		$this->assertSame( '--option=value', $args[0][1] );
	}

	public function testExtractAssocDoubleDashDelimiterMultipleArgs(): void {
		// Multiple option-like arguments after `--` should all be positional.
		$args = Configurator::extract_assoc( [ 'option', 'get', 'home', '--', '--require=/blah', '--no-color' ] );

		$this->assertCount( 5, $args[0] );
		$this->assertCount( 0, $args[1] );

		$this->assertSame( 'option', $args[0][0] );
		$this->assertSame( 'get', $args[0][1] );
		$this->assertSame( 'home', $args[0][2] );
		$this->assertSame( '--require=/blah', $args[0][3] );
		$this->assertSame( '--no-color', $args[0][4] );
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

	public function testExtractAssocMultipleValues(): void {
		$args = Configurator::extract_assoc( [ 'list', '--status=active', '--status=parent' ] );

		$this->assertCount( 1, $args[0] );
		$this->assertCount( 2, $args[1] );

		$this->assertSame( 'list', $args[0][0] );

		$this->assertSame( 'status', $args[1][0][0] );
		$this->assertSame( 'active', $args[1][0][1] );

		$this->assertSame( 'status', $args[1][1][0] );
		$this->assertSame( 'parent', $args[1][1][1] );
	}

	public function testParseArgsAggregatesMultipleValues(): void {
		$argv = [ 'list', '--status=active', '--status=parent', '--field=name' ];

		$configurator = new Configurator( __DIR__ . '/../php/config-spec.php' );
		$parsed       = $configurator->parse_args( $argv );

		// Positional arguments should remain unchanged.
		$this->assertSame( [ 'list' ], $parsed[0] );

		// Repeated flags should be aggregated into an array.
		$this->assertArrayHasKey( 'status', $parsed[1] );
		$this->assertSame( [ 'active', 'parent' ], $parsed[1]['status'] );

		// Non-repeating parameters should collapse to their last value.
		$this->assertArrayHasKey( 'field', $parsed[1] );
		$this->assertSame( 'name', $parsed[1]['field'] );
	}

	public function testParseArgsBooleanFlagsUseLastWins(): void {
		$argv  = [ 'command', '--verbose', '--no-verbose', '--verbose' ];
		$argv2 = [ 'command', '--verbose', '--verbose', '--no-verbose' ];

		$configurator = new Configurator( __DIR__ . '/../php/config-spec.php' );
		$parsed       = $configurator->parse_args( $argv );

		// Positional arguments should remain unchanged.
		$this->assertSame( [ 'command' ], $parsed[0] );

		// The last --verbose should win, so verbose should be true.
		$this->assertArrayHasKey( 'verbose', $parsed[1] );
		$this->assertTrue( $parsed[1]['verbose'] );

		$parsed2     = $configurator->parse_args( $argv2 );
		$assoc_args2 = $parsed2[1];

		// The last --no-verbose should win, so verbose should be false.
		$this->assertArrayHasKey( 'verbose', $assoc_args2 );
		$this->assertFalse( $assoc_args2['verbose'] );
	}

	public function testMergeYmlSelfInheritRecursionGuard(): void {
		$temp_dir = sys_get_temp_dir();
		$file     = tempnam( $temp_dir, 'wp-cli-test-self-' );
		file_put_contents( $file, "_:\n  inherit: " . basename( $file ) . "\nfoo: bar\n" );

		$configurator = new Configurator( __DIR__ . '/../php/config-spec.php' );
		$configurator->merge_yml( $file );

		[ $config, $extra_config ] = $configurator->to_array();

		unlink( $file );

		$this->assertEquals( 'bar', $extra_config['foo'] );
	}

	public function testMergeYmlCircularInheritRecursionGuard(): void {
		$temp_dir = sys_get_temp_dir();
		$file1    = tempnam( $temp_dir, 'wp-cli-test-1-' ) . '.yml';
		$file2    = tempnam( $temp_dir, 'wp-cli-test-2-' ) . '.yml';

		file_put_contents( $file1, "_:\n  inherit: " . basename( $file2 ) . "\nfoo: bar\n" );
		file_put_contents( $file2, "_:\n  inherit: " . basename( $file1 ) . "\nfoo: baz\nbaz: qux\n" );

		$configurator = new Configurator( __DIR__ . '/../php/config-spec.php' );
		$configurator->merge_yml( $file1 );

		[ $config, $extra_config ] = $configurator->to_array();

		unlink( $file1 );
		unlink( $file2 );

		$this->assertEquals( 'bar', $extra_config['foo'] );
		$this->assertEquals( 'qux', $extra_config['baz'] );
	}
}
