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
	 * @dataProvider dataGenerateSshCommandHyphenValidation
	 */
	#[DataProvider( 'dataGenerateSshCommandHyphenValidation' )] // phpcs:ignore PHPCompatibility.Attributes.NewAttributes.PHPUnitAttributeFound
	public function testGenerateSshCommandHyphenValidation( array $bits, string $invalid_bit ): void {
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
			$this->assertStringContainsString( sprintf( 'Invalid SSH %s: value cannot start with a hyphen.', $invalid_bit ), $logger->stderr );
		} finally {
			$class_wp_cli_capture_exit->setValue( null, $prev_capture_exit );
			\WP_CLI::set_logger( $prev_logger );
		}
	}

	public static function dataGenerateSshCommandHyphenValidation(): array {
		return [
			[ [ 'host' => '-oProxyCommand=id' ], 'host' ],
			[
				[
					'user' => '-oProxyCommand=id',
					'host' => 'example.com',
				],
				'user',
			],
			[
				[
					'host' => 'example.com',
					'key'  => '-oProxyCommand=id',
				],
				'key',
			],
			[
				[
					'host'      => 'example.com',
					'proxyjump' => '-oProxyCommand=id',
				],
				'proxyjump',
			],
			[
				[
					'host'       => 'example.com',
					'ssh_config' => '-oProxyCommand=id',
				],
				'ssh_config',
			],
		];
	}
}
