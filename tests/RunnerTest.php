<?php

namespace WP_CLI\Tests;

use PHPUnit\Framework\Attributes\DataProvider;

use ReflectionClass;
use ReflectionProperty;
use WP_CLI\Runner;

/**
 * Tests for the WP_CLI\Runner class.
 */
final class RunnerTest extends TestCase {

	/**
	 * @dataProvider dataSafeParsePath
	 */
	#[DataProvider( 'dataSafeParsePath' )] // phpcs:ignore PHPCompatibility.Attributes.NewAttributes.PHPUnitAttributeFound
	public function testSafeParsePath( $expression, $expected ): void {
		$runner = new ReflectionClass( Runner::class );
		$method = $runner->getMethod( 'safe_parse_path' );
		if ( PHP_VERSION_ID < 80100 ) {
			// @phpstan-ignore method.deprecated
			$method->setAccessible( true );
		}

		$this->assertSame( $expected, $method->invoke( null, $expression ) );
	}

	public static function dataSafeParsePath(): array {
		return [
			// Simple single-quoted relative paths.
			[ "'./foo'", './foo' ],
			[ "  './foo'  ", './foo' ],

			// Simple double-quoted paths.
			[ '"./foo"', './foo' ],
			[ '"/absolute/path"', '/absolute/path' ],

			// String concatenation.
			[ "'/base' . '/sub'", '/base/sub' ],
			[ "'/base' . '/sub' . '/deep'", '/base/sub/deep' ],

			// dirname() with a single-quoted string.
			[ "dirname('/path/to/index.php') . '/wp'", '/path/to/wp' ],
			[ "dirname( '/path/to/index.php' ) . '/wp'", '/path/to/wp' ],

			// dirname() with a double-quoted string.
			[ 'dirname("/path/to/index.php") . "/wp"', '/path/to/wp' ],

			// Nested dirname().
			[ "dirname(dirname('/path/to/index.php')) . '/wp'", '/path/wp' ],

			// Single-quoted escape sequences.
			[ "'foo\\'s'", "foo's" ],
			[ "'back\\\\slash'", 'back\\slash' ],

			// Double-quoted escape sequences.
			[ '"tab\\there"', "tab\there" ],

			// Malicious / unsupported expressions must return false.
			[ 'system("id")', false ],
			[ 'SOME_CONSTANT', false ],
			[ "'path' + '/foo'", false ],
			[ '', false ],
		];
	}

	/**
	 * @dataProvider dataGenerateSshCommandValidation
	 */
	#[DataProvider( 'dataGenerateSshCommandValidation' )] // phpcs:ignore PHPCompatibility.Attributes.NewAttributes.PHPUnitAttributeFound
	public function testGenerateSshCommandValidation( array $bits, string $expected_error ): void {
		$class_wp_cli_capture_exit = new ReflectionProperty( 'WP_CLI', 'capture_exit' );
		if ( PHP_VERSION_ID < 80100 ) {
			// @phpstan-ignore method.deprecated
			$class_wp_cli_capture_exit->setAccessible( true );
		}
		$prev_capture_exit = $class_wp_cli_capture_exit->getValue();
		$class_wp_cli_capture_exit->setValue( null, true );

		$prev_logger = \WP_CLI::get_logger();
		$logger      = new \WP_CLI\Loggers\Execution();
		\WP_CLI::set_logger( $logger );

		try {
			$runner_class = new ReflectionClass( Runner::class );
			$runner       = $runner_class->newInstanceWithoutConstructor();
			$method       = $runner_class->getMethod( 'generate_ssh_command' );
			if ( PHP_VERSION_ID < 80100 ) {
				// @phpstan-ignore method.deprecated
				$method->setAccessible( true );
			}

			$method->invoke( $runner, $bits, 'wp status' );
			$this->fail( 'Should have thrown ExitException' );
		} catch ( \WP_CLI\ExitException $e ) {
			$this->assertStringContainsString( $expected_error, $logger->stderr );
		} finally {
			$class_wp_cli_capture_exit->setValue( null, $prev_capture_exit );
			\WP_CLI::set_logger( $prev_logger );
		}
	}

	public static function dataGenerateSshCommandValidation(): array {
		return [
			[ [ 'host' => '-oProxyCommand=id' ], 'Invalid SSH host: value cannot start with a hyphen.' ],
			[
				[
					'user' => '-oProxyCommand=id',
					'host' => 'example.com',
				],
				'Invalid SSH user: value cannot start with a hyphen.',
			],
			[
				[
					'host' => 'example.com',
					'key'  => '-oProxyCommand=id',
				],
				'Invalid SSH key: value cannot start with a hyphen.',
			],
			[
				[
					'host'      => 'example.com',
					'proxyjump' => '-oProxyCommand=id',
				],
				'Invalid SSH proxyjump: value cannot start with a hyphen.',
			],
			[
				[
					'host'       => 'example.com',
					'ssh_config' => '-oProxyCommand=id',
				],
				'Invalid SSH ssh_config: value cannot start with a hyphen.',
			],
			[
				[ 'host' => [ 'example.com' ] ],
				'Invalid SSH host: value must be a string.',
			],
			[
				[
					'host' => 'example.com',
					'key'  => [ 'identityfile.key' ],
				],
				'Invalid SSH key: value must be a string.',
			],
		];
	}

	public function testGenerateSshCommandValidHost(): void {
		$runner_class = new ReflectionClass( Runner::class );
		$runner       = $runner_class->newInstanceWithoutConstructor();
		$method       = $runner_class->getMethod( 'generate_ssh_command' );
		if ( PHP_VERSION_ID < 80100 ) {
			// @phpstan-ignore method.deprecated
			$method->setAccessible( true );
		}

		$command = $method->invoke( $runner, [ 'host' => 'example.com' ], 'wp status' );
		$this->assertIsString( $command );
		$this->assertStringContainsString( 'example.com', $command );
	}

	/**
	 * @dataProvider dataGenerateSshCommandAliasValidation
	 */
	#[DataProvider( 'dataGenerateSshCommandAliasValidation' )] // phpcs:ignore PHPCompatibility.Attributes.NewAttributes.PHPUnitAttributeFound
	public function testGenerateSshCommandAliasValidation( array $alias_config, string $expected_error ): void {
		$class_wp_cli_capture_exit = new ReflectionProperty( 'WP_CLI', 'capture_exit' );
		if ( PHP_VERSION_ID < 80100 ) {
			// @phpstan-ignore method.deprecated
			$class_wp_cli_capture_exit->setAccessible( true );
		}
		$prev_capture_exit = $class_wp_cli_capture_exit->getValue();
		$class_wp_cli_capture_exit->setValue( null, true );

		$prev_logger = \WP_CLI::get_logger();
		$logger      = new \WP_CLI\Loggers\Execution();
		\WP_CLI::set_logger( $logger );

		try {
			$runner_class = new ReflectionClass( Runner::class );
			$runner       = $runner_class->newInstanceWithoutConstructor();

			$prop_alias = $runner_class->getProperty( 'alias' );
			if ( PHP_VERSION_ID < 80100 ) {
				// @phpstan-ignore method.deprecated
				$prop_alias->setAccessible( true );
			}
			$prop_alias->setValue( $runner, 'testalias' );

			$prop_aliases = $runner_class->getProperty( 'aliases' );
			if ( PHP_VERSION_ID < 80100 ) {
				// @phpstan-ignore method.deprecated
				$prop_aliases->setAccessible( true );
			}
			$prop_aliases->setValue( $runner, [ 'testalias' => $alias_config ] );

			$method = $runner_class->getMethod( 'generate_ssh_command' );
			if ( PHP_VERSION_ID < 80100 ) {
				// @phpstan-ignore method.deprecated
				$method->setAccessible( true );
			}

			$method->invoke( $runner, [ 'host' => 'example.com' ], 'wp status' );
			$this->fail( 'Should have thrown ExitException' );
		} catch ( \WP_CLI\ExitException $e ) {
			$this->assertStringContainsString( $expected_error, $logger->stderr );
		} finally {
			$class_wp_cli_capture_exit->setValue( null, $prev_capture_exit );
			\WP_CLI::set_logger( $prev_logger );
		}
	}

	public static function dataGenerateSshCommandAliasValidation(): array {
		return [
			[ [ 'key' => '-oProxyCommand=id' ], 'Invalid SSH key: value cannot start with a hyphen.' ],
			[ [ 'proxyjump' => '-oProxyCommand=id' ], 'Invalid SSH proxyjump: value cannot start with a hyphen.' ],
			[ [ 'ssh_config' => '-oProxyCommand=id' ], 'Invalid SSH ssh_config: value cannot start with a hyphen.' ],
			// Alias values are not guaranteed to be strings, e.g. when a YAML list is used.
			[ [ 'key' => [ 'identityfile.key' ] ], 'Invalid SSH key: value must be a string.' ],
			[ [ 'proxyjump' => [ 'proxyhost' ] ], 'Invalid SSH proxyjump: value must be a string.' ],
			[ [ 'ssh_config' => [ '/path/to/ssh/config' ] ], 'Invalid SSH ssh_config: value must be a string.' ],
		];
	}
}
