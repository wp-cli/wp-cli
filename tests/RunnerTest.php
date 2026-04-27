<?php

namespace WP_CLI\Tests;

use PHPUnit\Framework\Attributes\DataProvider;

use ReflectionClass;
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
}
