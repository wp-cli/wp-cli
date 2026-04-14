<?php

use PHPUnit\Framework\TestCase;
use WP_CLI\Runner;

/**
 * Test Windows PowerShell argument splitting
 */
class WindowsArgsTest extends TestCase {

	/**
	 * Test that space-separated numeric IDs are split on Windows
	 *
	 * @dataProvider provideWindowsArguments
	 */
	#[PHPUnit\Framework\Attributes\DataProvider( 'provideWindowsArguments' )] // phpcs:ignore PHPCompatibility.Attributes.NewAttributes.PHPUnitAttributeFound
	public function testWindowsArgumentSplitting( $is_windows, $input_args, $expected_count, $expected_values ) {
		// Set the Windows environment variable
		putenv( $is_windows ? 'WP_CLI_TEST_IS_WINDOWS=1' : 'WP_CLI_TEST_IS_WINDOWS=0' );

		$reflection = new ReflectionClass( Runner::class );
		$method     = $reflection->getMethod( 'back_compat_conversions' );
		if ( PHP_VERSION_ID < 80100 ) {
			// @phpstan-ignore method.deprecated
			$method->setAccessible( true );
		}

		/**
		 * @var array{0: array<string>, 1: array<string, string>} $result
		 */
		$result              = $method->invoke( null, $input_args, [] );
		[ $result_args, $_ ] = $result;

		// Verify the results
		$this->assertCount( $expected_count, $result_args, 'Unexpected number of arguments' );

		foreach ( $expected_values as $index => $expected_value ) {
			$this->assertEquals( $expected_value, $result_args[ $index ], "Argument at index $index doesn't match" );
		}
	}

	public static function provideWindowsArguments() {
		return [
			// is_windows, input_args, expected_count, expected_values
			'Windows: space-separated IDs should be split' => [
				true,
				[ 'post', 'delete', '123 456 789' ],
				5,
				[ 'post', 'delete', '123', '456', '789' ],
			],
			'Windows: single ID should not be split'       => [
				true,
				[ 'post', 'delete', '123' ],
				3,
				[ 'post', 'delete', '123' ],
			],
			'Windows: non-numeric strings should not be split' => [
				true,
				[ 'post', 'delete', 'hello world' ],
				3,
				[ 'post', 'delete', 'hello world' ],
			],
			'Windows: mixed args (numeric at start)'       => [
				true,
				[ 'post', 'delete', '123 456', 'some-slug' ],
				5,
				[ 'post', 'delete', '123', '456', 'some-slug' ],
			],
			'Non-Windows: space-separated IDs should not split' => [
				false,
				[ 'post', 'delete', '123 456' ],
				3,
				[ 'post', 'delete', '123 456' ],
			],
			'Windows: IDs with tabs and spaces'            => [
				true,
				[ 'post', 'delete', "123\t456  789" ],
				5,
				[ 'post', 'delete', '123', '456', '789' ],
			],
			'Windows: normal case without leading/trailing spaces' => [
				true,
				[ 'post', 'delete', '123 456' ],
				4,
				[ 'post', 'delete', '123', '456' ],
			],
			'Windows: leading/trailing spaces prevent splitting' => [
				true,
				[ 'post', 'delete', '  123 456  ' ],
				3,
				[ 'post', 'delete', '  123 456  ' ],
			],
		];
	}

	/**
	 * Cleanup after each test
	 */
	public function tearDown(): void {
		putenv( 'WP_CLI_TEST_IS_WINDOWS' );
		parent::tearDown();
	}
}
