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
 * @subcommand revoke-md5-passwords
 */
EOB
		);

		$this->assertEquals( '', $doc->get_shortdesc() );
		$this->assertEquals( '', $doc->get_longdesc() );
		$this->assertEquals( '', $doc->get_synopsis() );
		$this->assertEquals( '', $doc->get_tag('foo') );
		$this->assertEquals( 'rock-on', $doc->get_tag('alias') );
		$this->assertEquals( 'revoke-md5-passwords', $doc->get_tag('subcommand') );
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
 * This is a [reference link](https://wordpress.org/) and [second link](http://wp-cli.org/). It should be displayed very nice!
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
This is a [reference link][1] and [second link][2]. It should be displayed very nice!
---
[1] https://wordpress.org/
[2] http://wp-cli.org/

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

	public function test_desc_parses_yaml() {
		$longdesc = <<<EOB
Play some music loudly

```
# Here's an example of how you might run the command
wp rock-on electronic --volume=11
```

## OPTIONS

<genre>...
: Start with one or more genres.
---
options:
  - rock
  - electronic
default: rock
---

--volume=<number>
: Sets the volume.
---
default: 10
---

--artist=<artist-name>
: Limit to a specific artist.

## EXAMPLES

wp rock-on electronic --volume=11

EOB;
		$doc = new DocParser( $longdesc );
		$this->assertEquals( 'Start with one or more genres.', $doc->get_arg_desc( 'genre' ) );
		$this->assertEquals( 'Sets the volume.', $doc->get_param_desc( 'volume' ) );
		$this->assertEquals( array(
			'options' => array( 'rock', 'electronic' ),
			'default' => 'rock',
		), $doc->get_arg_args( 'genre' ) );
		$this->assertEquals( array(
			'default' => 10,
		), $doc->get_param_args( 'volume' ) );
		$this->assertNull( $doc->get_param_args( 'artist' ) );
	}

	public function test_desc_doesnt_parse_far_params_yaml() {
		$longdesc = <<<EOB
## OPTIONS

<hook>
: The name of the action or filter.

[--format=<format>]
: List callbacks as a table, JSON, CSV, or YAML.
---
default: table
options:
  - table
  - json
  - csv
  - yaml
---
EOB;
		$doc = new DocParser( $longdesc );
		$this->assertEquals( array(
			'default' => 'table',
			'options' => array( 'table', 'json', 'csv', 'yaml' ),
		), $doc->get_param_args( 'format' ) );
		$this->assertNull( $doc->get_arg_args( 'hook' ) );
	}

	public function test_desc_doesnt_parse_far_args_yaml() {
		$longdesc = <<<EOB
## OPTIONS

<hook>
: The name of the action or filter.

<format>
: List callbacks as a table, JSON, CSV, or YAML.
---
default: table
options:
  - table
  - json
  - csv
  - yaml
---
EOB;
		$doc = new DocParser( $longdesc );
		$this->assertEquals( array(
			'default' => 'table',
			'options' => array( 'table', 'json', 'csv', 'yaml' ),
		), $doc->get_arg_args( 'format' ) );
		$this->assertNull( $doc->get_arg_args( 'hook' ) );
	}

	function test_reference_link_in_long_desc_should_be_parsed() {
		$doc = new DocParser( '' );
		$test_class = new ReflectionClass( $doc );
		$method = $test_class->getMethod( 'parse_reference_links' );
		$method->setAccessible( true );

		$desc = 'This is a [reference link](https://wordpress.org/) and [second link](http://wp-cli.org/). It should be displayed very nice!';
		$result = $method->invokeArgs( null, array( $desc ) );

		$expect =<<<EOB
This is a [reference link][1] and [second link][2]. It should be displayed very nice!
---
[1] https://wordpress.org/
[2] http://wp-cli.org/
EOB;

		$this->assertSame( $expect, $result );
	}

	function test_long_desc_which_does_not_have_link() {
		$doc = new DocParser( '' );
		$test_class = new ReflectionClass( $doc );
		$method = $test_class->getMethod( 'parse_reference_links' );
		$method->setAccessible( true );

		$desc = 'It should be displayed very nice!';
		$result = $method->invokeArgs( null, array( $desc ) );

		$expect = "It should be displayed very nice!";

		$this->assertSame( $expect, $result );
	}

	function test_long_desc_which_has_not_url() {
		$doc = new DocParser( '' );
		$test_class = new ReflectionClass( $doc );
		$method = $test_class->getMethod( 'parse_reference_links' );
		$method->setAccessible( true );

		$desc = 'It should be [displayed](Hello) very nice!';
		$result = $method->invokeArgs( null, array( $desc ) );

		$expect = "It should be [displayed](Hello) very nice!";

		$this->assertSame( $expect, $result );
	}
}

