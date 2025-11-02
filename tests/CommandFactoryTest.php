<?php

use WP_CLI\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class CommandFactoryTest extends TestCase {

	public static function set_up_before_class() {
		require_once dirname( __DIR__ ) . '/php/class-wp-cli-command.php';
	}

	/**
	 * @dataProvider dataProviderExtractLastDocComment
	 */
	#[DataProvider( 'dataProviderExtractLastDocComment' )] // phpcs:ignore PHPCompatibility.Attributes.NewAttributes.PHPUnitAttributeFound
	public function testExtractLastDocComment( $content, $expected ): void {
		// Save and set test env var.
		$is_windows = getenv( 'WP_CLI_TEST_IS_WINDOWS' );
		putenv( 'WP_CLI_TEST_IS_WINDOWS=0' );

		static $extract_last_doc_comment = null;
		if ( null === $extract_last_doc_comment ) {
			$extract_last_doc_comment = new \ReflectionMethod( 'WP_CLI\Dispatcher\CommandFactory', 'extract_last_doc_comment' );
			if ( PHP_VERSION_ID < 80100 ) {
				$extract_last_doc_comment->setAccessible( true );
			}
		}

		$actual = $extract_last_doc_comment->invoke( null, $content );
		$this->assertSame( $expected, $actual );

		// Restore.
		putenv( false === $is_windows ? 'WP_CLI_TEST_IS_WINDOWS' : "WP_CLI_TEST_IS_WINDOWS=$is_windows" );
	}

	/**
	 * @dataProvider dataProviderExtractLastDocComment
	 */
	#[DataProvider( 'dataProviderExtractLastDocComment' )] // phpcs:ignore PHPCompatibility.Attributes.NewAttributes.PHPUnitAttributeFound
	public function testExtractLastDocCommentWin( $content, $expected ): void {
		// Save and set test env var.
		$is_windows = getenv( 'WP_CLI_TEST_IS_WINDOWS' );
		putenv( 'WP_CLI_TEST_IS_WINDOWS=1' );

		static $extract_last_doc_comment = null;
		if ( null === $extract_last_doc_comment ) {
			$extract_last_doc_comment = new \ReflectionMethod( 'WP_CLI\Dispatcher\CommandFactory', 'extract_last_doc_comment' );
			if ( PHP_VERSION_ID < 80100 ) {
				$extract_last_doc_comment->setAccessible( true );
			}
		}

		$actual = $extract_last_doc_comment->invoke( null, $content );
		$this->assertSame( $expected, $actual );

		// Restore.
		putenv( false === $is_windows ? 'WP_CLI_TEST_IS_WINDOWS' : "WP_CLI_TEST_IS_WINDOWS=$is_windows" );
	}

	public static function dataProviderExtractLastDocComment(): array {
		return [
			[ '', false ],
			[ '*/', false ],
			[ '/*/  ', false ],
			[ '/**/', false ],
			[ '/***/ */', false ],
			[ '/***/', '/***/' ],
			[ "\n /**\n  \n  \t\n  */ \t\n \n ", "/**\n  \n  \t\n  */" ],
			[ "\r\n /**\r\n  \r\n  \t\r\n  */ \t\r\n \r\n ", "/**\r\n  \r\n  \t\r\n  */" ],
			[ '/**/ /***/ /***/', '/***/' ],
			[ 'asdfasdf/** /** */', '/** /** */' ],
			[ '*//** /** */', '/** /** */' ],
			[ '/** *//** /** */', '/** /** */' ],
			[ '*//** */ /** /** */', '/** /** */' ],
			[ '*//** *//** /** /** */', '/** /** /** */' ],

			[ '/** */class qwer', '/** */' ],
			[ '/**1*/class qwer{}/**2*/class asdf', '/**2*/' ],
			[ "/** */class qwer {}\nclass asdf", false ],
			[ "/** */class qwer {}\r\nclass asdf", false ],

			[ '/** */function qwer', '/** */' ],
			[ '/** */function qwer( $function ) {}', '/** */' ],
			[ '/**1*/function qwer() {}/**2*/function asdf()', '/**2*/' ],
			[ "/** */function qwer() {}\nfunction asdf()", false ],
			[ "/** */function qwer() {}\r\nfunction asdf()", false ],
			[ '/** */function qwer() {}function asdf()', false ],
			[ '/** */function qwer() {};function asdf( $function )', false ],
		];
	}

	public function testGetDocComment(): void {
		// Save and set test env var.
		$_get_doc_comment = getenv( 'WP_CLI_TEST_GET_DOC_COMMENT' );
		$_is_windows      = getenv( 'WP_CLI_TEST_IS_WINDOWS' );

		putenv( 'WP_CLI_TEST_GET_DOC_COMMENT=1' );
		putenv( 'WP_CLI_TEST_IS_WINDOWS=0' );

		// Make private function accessible.
		$get_doc_comment = new \ReflectionMethod( 'WP_CLI\Dispatcher\CommandFactory', 'get_doc_comment' );
		if ( PHP_VERSION_ID < 80100 ) {
			$get_doc_comment->setAccessible( true );
		}

		if ( ! class_exists( 'CommandFactoryTests_Get_Doc_Comment_1_Command', false ) ) {
			require __DIR__ . '/data/commandfactory-doc_comment-class.php';
		}
		if ( ! class_exists( 'CommandFactoryTests_Get_Doc_Comment_1_Command_Win', false ) ) {
			require __DIR__ . '/data/commandfactory-doc_comment-class-win.php';
		}

		// Class 1.

		// @phpstan-ignore argument.type
		$reflection = new \ReflectionClass( 'CommandFactoryTests_Get_Doc_Comment_1_Command' );
		$expected   = $reflection->getDocComment();

		$actual = $get_doc_comment->invoke( null, $reflection );
		$this->assertSame( $expected, $actual );

		// Class method 1.

		$reflection = new \ReflectionMethod( 'CommandFactoryTests_Get_Doc_Comment_1_Command', 'command1' );
		$expected   = $reflection->getDocComment();

		$actual = $get_doc_comment->invoke( null, $reflection );
		$this->assertSame( $expected, $actual );

		// Class method 2.

		$reflection = new \ReflectionMethod( 'CommandFactoryTests_Get_Doc_Comment_1_Command', 'command2' );
		$expected   = $reflection->getDocComment();

		$actual = $get_doc_comment->invoke( null, $reflection );
		$this->assertSame( $expected, $actual );

		// Class method 3.

		$reflection = new \ReflectionMethod( 'CommandFactoryTests_Get_Doc_Comment_1_Command', 'command3' );
		$expected   = $reflection->getDocComment();

		$actual = $get_doc_comment->invoke( null, $reflection );
		$this->assertSame( $expected, $actual );

		// Class method 4.

		$reflection = new \ReflectionMethod( 'CommandFactoryTests_Get_Doc_Comment_1_Command', 'command4' );
		$expected   = $reflection->getDocComment();

		$actual = $get_doc_comment->invoke( null, $reflection );
		$this->assertSame( $expected, $actual );
		$this->assertFalse( $actual );

		// Class 1 Windows.

		// @phpstan-ignore argument.type
		$reflection = new \ReflectionClass( 'CommandFactoryTests_Get_Doc_Comment_1_Command_Win' );
		$expected   = $reflection->getDocComment();

		$actual = $get_doc_comment->invoke( null, $reflection );
		$this->assertSame( $expected, $actual );

		// Class method 1.

		$reflection = new \ReflectionMethod( 'CommandFactoryTests_Get_Doc_Comment_1_Command_Win', 'command1' );
		$expected   = $reflection->getDocComment();

		$actual = $get_doc_comment->invoke( null, $reflection );
		$this->assertSame( $expected, $actual );

		// Class method 2.

		$reflection = new \ReflectionMethod( 'CommandFactoryTests_Get_Doc_Comment_1_Command_Win', 'command2' );
		$expected   = $reflection->getDocComment();

		$actual = $get_doc_comment->invoke( null, $reflection );
		$this->assertSame( $expected, $actual );

		// Class method 3.

		$reflection = new \ReflectionMethod( 'CommandFactoryTests_Get_Doc_Comment_1_Command_Win', 'command3' );
		$expected   = $reflection->getDocComment();

		$actual = $get_doc_comment->invoke( null, $reflection );
		$this->assertSame( $expected, $actual );

		// Class method 4.

		$reflection = new \ReflectionMethod( 'CommandFactoryTests_Get_Doc_Comment_1_Command_Win', 'command4' );
		$expected   = $reflection->getDocComment();

		$actual = $get_doc_comment->invoke( null, $reflection );
		$this->assertSame( $expected, $actual );
		$this->assertFalse( $actual );

		// Class 2.

		// @phpstan-ignore argument.type
		$reflection = new \ReflectionClass( 'CommandFactoryTests_Get_Doc_Comment_2_Command' );
		$expected   = $reflection->getDocComment();

		$actual = $get_doc_comment->invoke( null, $reflection );
		$this->assertSame( $expected, $actual );

		// Class method 1.

		$reflection = new \ReflectionMethod( 'CommandFactoryTests_Get_Doc_Comment_2_Command', 'command1' );
		$expected   = $reflection->getDocComment();

		$actual = $get_doc_comment->invoke( null, $reflection );
		$this->assertSame( $expected, $actual );
		$this->assertFalse( $actual );

		// Class 2 Windows.

		// @phpstan-ignore argument.type
		$reflection = new \ReflectionClass( 'CommandFactoryTests_Get_Doc_Comment_2_Command_Win' );
		$expected   = $reflection->getDocComment();

		$actual = $get_doc_comment->invoke( null, $reflection );
		$this->assertSame( $expected, $actual );

		// Class method 1.

		$reflection = new \ReflectionMethod( 'CommandFactoryTests_Get_Doc_Comment_2_Command_Win', 'command1' );
		$expected   = $reflection->getDocComment();

		$actual = $get_doc_comment->invoke( null, $reflection );
		$this->assertSame( $expected, $actual );
		$this->assertFalse( $actual );

		// Functions.

		require __DIR__ . '/data/commandfactory-doc_comment-function.php';

		// Function 1.

		$reflection = new \ReflectionFunction( 'commandfactorytests_get_doc_comment_func_1' );
		$expected   = $reflection->getDocComment();

		$actual = $get_doc_comment->invoke( null, $reflection );
		$this->assertSame( $expected, $actual );

		// Function 2.

		$reflection = new \ReflectionFunction( 'commandfactorytests_get_doc_comment_func_2' );
		$expected   = $reflection->getDocComment();

		$actual = $get_doc_comment->invoke( null, $reflection );
		$this->assertSame( $expected, $actual );

		// Function 3.

		// @phpstan-ignore variable.undefined
		$reflection = new \ReflectionFunction( $commandfactorytests_get_doc_comment_func_3 );
		$expected   = $reflection->getDocComment();

		$actual = $get_doc_comment->invoke( null, $reflection );
		$this->assertSame( $expected, $actual );

		// Restore.
		putenv( false === $_get_doc_comment ? 'WP_CLI_TEST_GET_DOC_COMMENT' : "WP_CLI_TEST_GET_DOC_COMMENT=$_get_doc_comment" );
		putenv( false === $_is_windows ? 'WP_CLI_TEST_IS_WINDOWS' : "WP_CLI_TEST_IS_WINDOWS=$_is_windows" );
	}

	public function testGetDocCommentWin(): void {
		// Save and set test env var.
		$_get_doc_comment = getenv( 'WP_CLI_TEST_GET_DOC_COMMENT' );
		$_is_windows      = getenv( 'WP_CLI_TEST_IS_WINDOWS' );

		putenv( 'WP_CLI_TEST_GET_DOC_COMMENT=1' );
		putenv( 'WP_CLI_TEST_IS_WINDOWS=1' );

		// Make private function accessible.
		$get_doc_comment = new \ReflectionMethod( 'WP_CLI\Dispatcher\CommandFactory', 'get_doc_comment' );
		if ( PHP_VERSION_ID < 80100 ) {
			$get_doc_comment->setAccessible( true );
		}

		if ( ! class_exists( 'CommandFactoryTests_Get_Doc_Comment_1_Command', false ) ) {
			require __DIR__ . '/data/commandfactory-doc_comment-class.php';
		}
		if ( ! class_exists( 'CommandFactoryTests_Get_Doc_Comment_1_Command_Win', false ) ) {
			require __DIR__ . '/data/commandfactory-doc_comment-class-win.php';
		}

		// Class 1.

		// @phpstan-ignore argument.type
		$reflection = new \ReflectionClass( 'CommandFactoryTests_Get_Doc_Comment_1_Command' );
		$expected   = $reflection->getDocComment();

		$actual = $get_doc_comment->invoke( null, $reflection );
		$this->assertSame( $expected, $actual );

		// Class method 1.

		$reflection = new \ReflectionMethod( 'CommandFactoryTests_Get_Doc_Comment_1_Command', 'command1' );
		$expected   = $reflection->getDocComment();

		$actual = $get_doc_comment->invoke( null, $reflection );
		$this->assertSame( $expected, $actual );

		// Class method 2.

		$reflection = new \ReflectionMethod( 'CommandFactoryTests_Get_Doc_Comment_1_Command', 'command2' );
		$expected   = $reflection->getDocComment();

		$actual = $get_doc_comment->invoke( null, $reflection );
		$this->assertSame( $expected, $actual );

		// Class method 3.

		$reflection = new \ReflectionMethod( 'CommandFactoryTests_Get_Doc_Comment_1_Command', 'command3' );
		$expected   = $reflection->getDocComment();

		$actual = $get_doc_comment->invoke( null, $reflection );
		$this->assertSame( $expected, $actual );

		// Class method 4.

		$reflection = new \ReflectionMethod( 'CommandFactoryTests_Get_Doc_Comment_1_Command', 'command4' );
		$expected   = $reflection->getDocComment();

		$actual = $get_doc_comment->invoke( null, $reflection );
		$this->assertSame( $expected, $actual );
		$this->assertFalse( $actual );

		// Class 1 Windows.

		// @phpstan-ignore argument.type
		$reflection = new \ReflectionClass( 'CommandFactoryTests_Get_Doc_Comment_1_Command_Win' );
		$expected   = $reflection->getDocComment();

		$actual = $get_doc_comment->invoke( null, $reflection );
		$this->assertSame( $expected, $actual );

		// Class method 1.

		$reflection = new \ReflectionMethod( 'CommandFactoryTests_Get_Doc_Comment_1_Command_Win', 'command1' );
		$expected   = $reflection->getDocComment();

		$actual = $get_doc_comment->invoke( null, $reflection );
		$this->assertSame( $expected, $actual );

		// Class method 2.

		$reflection = new \ReflectionMethod( 'CommandFactoryTests_Get_Doc_Comment_1_Command_Win', 'command2' );
		$expected   = $reflection->getDocComment();

		$actual = $get_doc_comment->invoke( null, $reflection );
		$this->assertSame( $expected, $actual );

		// Class method 3.

		$reflection = new \ReflectionMethod( 'CommandFactoryTests_Get_Doc_Comment_1_Command_Win', 'command3' );
		$expected   = $reflection->getDocComment();

		$actual = $get_doc_comment->invoke( null, $reflection );
		$this->assertSame( $expected, $actual );

		// Class method 4.

		$reflection = new \ReflectionMethod( 'CommandFactoryTests_Get_Doc_Comment_1_Command_Win', 'command4' );
		$expected   = $reflection->getDocComment();

		$actual = $get_doc_comment->invoke( null, $reflection );
		$this->assertSame( $expected, $actual );
		$this->assertFalse( $actual );

		// Class 2.

		// @phpstan-ignore argument.type
		$reflection = new \ReflectionClass( 'CommandFactoryTests_Get_Doc_Comment_2_Command' );
		$expected   = $reflection->getDocComment();

		$actual = $get_doc_comment->invoke( null, $reflection );
		$this->assertSame( $expected, $actual );

		// Class method 1.

		$reflection = new \ReflectionMethod( 'CommandFactoryTests_Get_Doc_Comment_2_Command', 'command1' );
		$expected   = $reflection->getDocComment();

		$actual = $get_doc_comment->invoke( null, $reflection );
		$this->assertSame( $expected, $actual );
		$this->assertFalse( $actual );

		// Class 2 Windows.

		// @phpstan-ignore argument.type
		$reflection = new \ReflectionClass( 'CommandFactoryTests_Get_Doc_Comment_2_Command_Win' );
		$expected   = $reflection->getDocComment();

		$actual = $get_doc_comment->invoke( null, $reflection );
		$this->assertSame( $expected, $actual );

		// Class method 1.

		$reflection = new \ReflectionMethod( 'CommandFactoryTests_Get_Doc_Comment_2_Command_Win', 'command1' );
		$expected   = $reflection->getDocComment();

		$actual = $get_doc_comment->invoke( null, $reflection );
		$this->assertSame( $expected, $actual );
		$this->assertFalse( $actual );

		// Functions.

		require __DIR__ . '/data/commandfactory-doc_comment-function-win.php';

		// Function 1 Windows.

		$reflection = new \ReflectionFunction( 'commandfactorytests_get_doc_comment_func_1_win' );
		$expected   = $reflection->getDocComment();

		$actual = $get_doc_comment->invoke( null, $reflection );
		$this->assertSame( $expected, $actual );

		// Function 2.

		$reflection = new \ReflectionFunction( 'commandfactorytests_get_doc_comment_func_2_win' );
		$expected   = $reflection->getDocComment();

		$actual = $get_doc_comment->invoke( null, $reflection );
		$this->assertSame( $expected, $actual );

		// Function 3.

		// @phpstan-ignore variable.undefined
		$reflection = new \ReflectionFunction( $commandfactorytests_get_doc_comment_func_3_win );
		$expected   = $reflection->getDocComment();

		$actual = $get_doc_comment->invoke( null, $reflection );
		$this->assertSame( $expected, $actual );

		// Restore.
		putenv( false === $_get_doc_comment ? 'WP_CLI_TEST_GET_DOC_COMMENT' : "WP_CLI_TEST_GET_DOC_COMMENT=$_get_doc_comment" );
		putenv( false === $_is_windows ? 'WP_CLI_TEST_IS_WINDOWS' : "WP_CLI_TEST_IS_WINDOWS=$_is_windows" );
	}
}
