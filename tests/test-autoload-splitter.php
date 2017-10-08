<?php
/**
 * Test AutoloadSplitter class
 *
 * @package   WP_CLI\Tests\Unit
 * @author    WP-CLI Contributors
 * @copyright 2017 WP-CLI
 * @license   GPL-2.0+
 */

namespace WP_CLI\Tests\Unit;

use PHPUnit_Framework_TestCase;
use WP_CLI\AutoloadSplitter;

/**
 * Class AutoloadSplitterTest.
 *
 * @group autoloadsplitter
 */
class AutoloadSplitterTest extends PHPUnit_Framework_TestCase {
	/**
	 * Test that AutoloadSplitter returns correct login
	 *
	 * @dataProvider dataCodePaths
	 */
	public function testAutoloadSplitter( $code, $expected ) {
		$autoload_splitter = new AutoloadSplitter();

		$this->assertSame( $expected, $autoload_splitter('foo', $code) );
	}

	/**
	 * Data provider of code paths.
	 *
	 * @return array
	 */
	public function dataCodePaths() {
		return array(
			array( '/wp-cli/a-command/', true ),
			array( '/wp-cli/abcd-command/', true ),
			array( '/wp-cli/a-b-c-d-e-f-g-h-i-j-k-l-m-n-o-p-q-r-s-t-u-v-w-x-y-z-command/', true ),
			array( 'xyz/wp-cli/abcd-command/zxy', true ),

			array( '/php/commands/src/', true ),
			array( 'xyz/php/commands/src/zyx', true ),

			array( '/wp-cli/-command/', false ), // No command name.
			array( '/wp-cli/--command/', false ), // No command name.
			array( '/wp-cli/abcd-command-/', false ), // End is not '-command/`
			array( '/wp-cli/abcd-/', false ), // End is not '-command/'.
			array( '/wp-cli/abcd-command', false ), // End is not '-command/'.
			array( 'wp-cli/abcd-command/', false ), // Start is not '/wp-cli/'.
			array( '/wp--cli/abcd-command/', false ),  // Start is not '/wp-cli/'.
			array( '/wp-cliabcd-command/', false ),  // Start is not '/wp-cli/'.
			array( '/wp-cli//abcd-command/', false ),  // Middle contains two '/'.

			array( '/php-/commands/src/', false ),  // Start is not '/php/'.
			array( 'php/commands/src/', false ), // Start is not '/php/'.
			array( '/php/commands/src', false ), // End is not '/src/'.
			array( '/php/commands/srcs/', false ),  // End is not '/src/'.
			array( '/php/commandssrc/', false ),  // End is not '/src/'.
		);
	}
}
