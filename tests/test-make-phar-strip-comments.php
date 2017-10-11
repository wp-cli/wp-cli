<?php

require_once dirname( __DIR__ ) . '/utils/make-phar-strip-comments.php';

class MakePharStripCommentsTest extends PHPUnit_Framework_TestCase {

	/**
	 * Test basic functionality.
	 */
	function testBasic() {
		
		// Just doc comment.
		$source = <<<'EOD'
<?php
/**
 * Blah
 */
class Blah_Command extends WP_CLI_Command {
}
EOD;
		$actual = make_phar_strip_comments( $source, true /*keep_doc_comments*/ );
		$this->assertSame( $source, $actual );
		$this->assertSame( substr_count( $actual, "\n" ), substr_count( $source, "\n" ) );
		$expected = <<<'EOD'
<?php



class Blah_Command extends WP_CLI_Command {
}
EOD;
		$actual = make_phar_strip_comments( $source, false /*keep_doc_comments*/ );
		$this->assertSame( $expected, $actual );
		$this->assertSame( substr_count( $actual, "\n" ), substr_count( $source, "\n" ) );

	}

	/**
	 * Test inline comment behaviour.
	 */
	function testInlineComments() {

		// Doc comment and inline comments.
		$source = <<<'EOD'
<?php
/**
 * Blah
 */
class Blah_Command extends WP_CLI_Command {
	function __construct() {
		$blah =  'blah';  // 1 space left at end of line.
		if (      'blah' === $blah ) {
			/* 3 tabs kept on this line. */
		}
	}
}
EOD;

		$expected = <<<'EOD'
<?php
/**
 * Blah
 */
class Blah_Command extends WP_CLI_Command {
	function __construct() {
		$blah = 'blah'; 
		if ( 'blah' === $blah ) {
			
		}
	}
}
EOD;
		$actual = make_phar_strip_comments( $source, true /*keep_doc_comments*/ );
		$this->assertSame( $expected, $actual );
		$this->assertSame( substr_count( $actual, "\n" ), substr_count( $source, "\n" ) );

		$expected = <<<'EOD'
<?php



class Blah_Command extends WP_CLI_Command {
	function __construct() {
		$blah = 'blah'; 
		if ( 'blah' === $blah ) {
			
		}
	}
}
EOD;
		$actual = make_phar_strip_comments( $source, false /*keep_doc_comments*/ );
		$this->assertSame( $expected, $actual );
		$this->assertSame( substr_count( $actual, "\n" ), substr_count( $source, "\n" ) );
	}

	/**
	 * Test keeps copyright comments.
	 */
	function testCopyright() {

		// Doc comment and inline copyrights.
		$source = <<<'EOD'
<?php
/**
 * Public domain
 */
class Blah_Command extends WP_CLI_Command {
	function __construct() {
		$blah =  'blah';  // Copyright
		if (      'blah' === $blah ) { /* Brit licence. */
			/* (c) */
		}
		/**
		 * License.
		 */
	}
}
EOD;

		$expected = <<<'EOD'
<?php
/**
 * Public domain
 */
class Blah_Command extends WP_CLI_Command {
	function __construct() {
		$blah = 'blah'; // Copyright
		if ( 'blah' === $blah ) { /* Brit licence. */
			/* (c) */
		}
		/**
		 * License.
		 */
	}
}
EOD;
		$actual = make_phar_strip_comments( $source, true /*keep_doc_comments*/ );
		$this->assertSame( $expected, $actual );

		$expected = <<<'EOD'
<?php



class Blah_Command extends WP_CLI_Command {
	function __construct() {
		$blah = 'blah'; // Copyright
		if ( 'blah' === $blah ) { /* Brit licence. */
			/* (c) */
		}
		/**
		 * License.
		 */
	}
}
EOD;

		$actual = make_phar_strip_comments( $source, false /*keep_doc_comments*/ );
		$this->assertSame( $expected, $actual );
	}

	/**
	 * Test keeps Behat comments.
	 */
	public function testBehat() {

		$source = <<<'EOD'
<?php

/**
 * Features context.
 */
class FeatureContext extends BehatContext implements ClosuredContextInterface {

	/**
	 * @BeforeScenario
	 */
	public function beforeScenario( $event ) {
EOD;
		$expected = <<<'EOD'
<?php




class FeatureContext extends BehatContext implements ClosuredContextInterface {

	/**
	 * @BeforeScenario
	 */
	public function beforeScenario( $event ) {
EOD;
		$actual = make_phar_strip_comments( $source, false /*strip doc comments*/ );
		$this->assertSame( $expected, $actual );
		$this->assertSame( substr_count( $actual, "\n" ), substr_count( $source, "\n" ) );
	}

}
