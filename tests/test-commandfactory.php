<?php

use WP_CLI\Dispatcher\CommandFactory;

require_once dirname( __DIR__ ) . '/php/class-wp-cli-command.php';

class CommandFactoryTests extends PHPUnit_Framework_TestCase {

	/**
	 * @dataProvider dataProviderExtractLastDocComment
	 */
	function testExtractLastDocComment( $content, $expected ) {
		static $extract_last_doc_comment = null;
		if ( null === $extract_last_doc_comment ) {
			$extract_last_doc_comment = new \ReflectionMethod( 'WP_CLI\Dispatcher\CommandFactory', 'extract_last_doc_comment' );
			$extract_last_doc_comment->setAccessible( true );
		}

		$actual = $extract_last_doc_comment->invoke( null, $content );
		$this->assertSame( $expected, $actual );
	}

	function dataProviderExtractLastDocComment() {
		return array(
			array( "", false ),
			array( "*/", false ),
			array( "/*/  ", false ),
			array( "/**/", false ),
			array( "/***/ */", false ),
			array( "/***/", "/***/" ),
			array( "\n /**\n  \n  \t\n  */ \t\n \n ", "/**\n  \n  \t\n  */" ),
			array( "/**/ /***/ /***/", "/***/" ),
			array( "asdfasdf/** /** */", "/** /** */" ),
			array( "*//** /** */", "/** /** */" ),
			array( "/** *//** /** */", "/** /** */" ),
			array( "*//** */ /** /** */", "/** /** */" ),
			array( "*//** *//** /** /** */", "/** /** /** */" ),

			array( "/** */class qwer", "/** */" ),
			array( "/**1*/class qwer{}/**2*/class asdf", "/**2*/" ),
			array( "/** */class qwer {}\nclass asdf", false ),

			array( "/** */function qwer", "/** */" ),
			array( "/** */function qwer( \$function ) {}", "/** */" ),
			array( "/**1*/function qwer() {}/**2*/function asdf()", "/**2*/" ),
			array( "/** */function qwer() {}\nfunction asdf()", false ),
			array( "/** */function qwer() {}function asdf()", false ),
			array( "/** */function qwer() {};function asdf( \$function )", false ),
		);
	}

	function testGetDocComment() {
		// Save and set test env var.
		$prev_test_get_doc_comment = getenv( 'WP_CLI_TEST_GET_DOC_COMMENT' );
		putenv( 'WP_CLI_TEST_GET_DOC_COMMENT=1' );

		// Make private function accessible.
		$get_doc_comment = new \ReflectionMethod( 'WP_CLI\Dispatcher\CommandFactory', 'get_doc_comment' );
		$get_doc_comment->setAccessible( true );

		require __DIR__ . '/data/commandfactory-doc_comment-class.php';

		// Class 1

		$reflection = new \ReflectionClass( 'CommandFactoryTests_Get_Doc_Comment_1_Command' );
		$expected = $reflection->getDocComment();

		$actual = $get_doc_comment->invoke( null, $reflection );
		$this->assertSame( $expected, $actual );

		// Class method 1

		$reflection = new \ReflectionMethod( 'CommandFactoryTests_Get_Doc_Comment_1_Command', 'command1' );
		$expected = $reflection->getDocComment();

		$actual = $get_doc_comment->invoke( null, $reflection );
		$this->assertSame( $expected, $actual );

		// Class method 2

		$reflection = new \ReflectionMethod( 'CommandFactoryTests_Get_Doc_Comment_1_Command', 'command2' );
		$expected = $reflection->getDocComment();

		$actual = $get_doc_comment->invoke( null, $reflection );
		$this->assertSame( $expected, $actual );

		// Class method 3

		$reflection = new \ReflectionMethod( 'CommandFactoryTests_Get_Doc_Comment_1_Command', 'command3' );
		$expected = $reflection->getDocComment();

		$actual = $get_doc_comment->invoke( null, $reflection );
		$this->assertSame( $expected, $actual );

		// Class method 4

		$reflection = new \ReflectionMethod( 'CommandFactoryTests_Get_Doc_Comment_1_Command', 'command4' );
		$expected = $reflection->getDocComment();

		$actual = $get_doc_comment->invoke( null, $reflection );
		$this->assertSame( $expected, $actual );
		$this->assertFalse( $actual );

		// Class 2

		$reflection = new \ReflectionClass( 'CommandFactoryTests_Get_Doc_Comment_2_Command' );
		$expected = $reflection->getDocComment();

		$actual = $get_doc_comment->invoke( null, $reflection );
		$this->assertSame( $expected, $actual );

		// Class method 1

		$reflection = new \ReflectionMethod( 'CommandFactoryTests_Get_Doc_Comment_2_Command', 'command1' );
		$expected = $reflection->getDocComment();

		$actual = $get_doc_comment->invoke( null, $reflection );
		$this->assertSame( $expected, $actual );
		$this->assertFalse( $actual );

		// Functions

		require __DIR__ . '/data/commandfactory-doc_comment-function.php';

		// Function 1

		$reflection = new \ReflectionFunction( 'commandfactorytests_get_doc_comment_func_1' );
		$expected = $reflection->getDocComment();

		$actual = $get_doc_comment->invoke( null, $reflection );
		$this->assertSame( $expected, $actual );

		// Function 2

		$reflection = new \ReflectionFunction( 'commandfactorytests_get_doc_comment_func_2' );
		$expected = $reflection->getDocComment();

		$actual = $get_doc_comment->invoke( null, $reflection );
		$this->assertSame( $expected, $actual );

		// Function 3

		$reflection = new \ReflectionFunction( $commandfactorytests_get_doc_comment_func_3 );
		$expected = $reflection->getDocComment();

		$actual = $get_doc_comment->invoke( null, $reflection );
		$this->assertSame( $expected, $actual );

		// Restore.

		putenv( false === $prev_test_get_doc_comment ? 'WP_CLI_TEST_GET_DOC_COMMENT' : "WP_CLI_TEST_GET_DOC_COMMENT=$prev_test_get_doc_comment" );
		$this->assertTrue( true );
	}
}
