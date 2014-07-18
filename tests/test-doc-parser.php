<?php

use WP_CLI\DocParser;

class DocParserTests extends PHPUnit_Framework_TestCase {

	function test_empty() {
		$doc = new DocParser( '' );

		$this->assertEquals( '', $doc->get_shortdesc() );
		$this->assertEquals( '', $doc->get_longdesc() );
		$this->assertEquals( '', $doc->get_synopsis() );
		$this->assertEquals( '', $doc->get_tag('alias') );
	}

	function test_only_tags() {
		$doc = new DocParser( <<<EOB
/**
 * @alias rock-on
 */
EOB
		);

		$this->assertEquals( '', $doc->get_shortdesc() );
		$this->assertEquals( '', $doc->get_longdesc() );
		$this->assertEquals( '', $doc->get_synopsis() );
		$this->assertEquals( '', $doc->get_tag('foo') );
		$this->assertEquals( 'rock-on', $doc->get_tag('alias') );
	}

	function test_no_longdesc() {
		$doc = new DocParser( <<<EOB
/**
 * Rock and roll!
 * @alias rock-on
 */
EOB
		);

		$this->assertEquals( 'Rock and roll!', $doc->get_shortdesc() );
		$this->assertEquals( '', $doc->get_longdesc() );
		$this->assertEquals( '', $doc->get_synopsis() );
		$this->assertEquals( 'rock-on', $doc->get_tag('alias') );
	}

	function test_complete() {
		$doc = new DocParser( <<<EOB
/**
 * Rock and roll!
 *
 * ## OPTIONS
 *
 * <genre>...
 * : Start with one or more genres.
 *
 * --volume=<number>
 * : Sets the volume.
 *
 * --artist=<artist-name>
 * : Limit to a specific artist.
 *
 * ## EXAMPLES
 *
 * wp rock-on --volume=11
 *
 * @synopsis [--volume=<number>]
 * @alias rock-on
 */
EOB
		);

		$this->assertEquals( 'Rock and roll!', $doc->get_shortdesc() );
		$this->assertEquals( '[--volume=<number>]', $doc->get_synopsis() );
		$this->assertEquals( 'Start with one or more genres.', $doc->get_arg_desc( 'genre' ) );
		$this->assertEquals( 'Sets the volume.', $doc->get_param_desc( 'volume' ) );
		$this->assertEquals( 'rock-on', $doc->get_tag('alias') );

		$longdesc = <<<EOB
## OPTIONS

<genre>...
: Start with one or more genres.

--volume=<number>
: Sets the volume.

--artist=<artist-name>
: Limit to a specific artist.

## EXAMPLES

wp rock-on --volume=11
EOB
		;
		$this->assertEquals( $longdesc, $doc->get_longdesc() );
	}
}

