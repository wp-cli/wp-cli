<?php

use WP_CLI\Tests\TestCase;

class HelpTest extends TestCase {

	public static function set_up_before_class() {
		require_once dirname( __DIR__ ) . '/php/class-wp-cli.php';
		require_once dirname( __DIR__ ) . '/php/class-wp-cli-command.php';
		require_once dirname( __DIR__ ) . '/php/commands/help.php';
	}

	public function test_parse_reference_links(): void {
		$original_force_hyperlink = getenv( 'FORCE_HYPERLINK' );
		putenv( 'FORCE_HYPERLINK=0' );

		try {
			$test_class = new ReflectionClass( 'Help_Command' );
			$method     = $test_class->getMethod( 'parse_reference_links' );
			if ( PHP_VERSION_ID < 80100 ) {
				// @phpstan-ignore method.deprecated
				$method->setAccessible( true );
			}

			$desc   = 'This is a [reference link](https://wordpress.org/). It should be displayed very nice!';
			$result = $method->invokeArgs( null, [ $desc ] );

			$expected = <<<'EOL'
This is a [reference link][1]. It should be displayed very nice!

---
[1] https://wordpress.org/
EOL;
			$this->assertSame( $expected, $result );

			$desc   = 'This is a [reference link](https://wordpress.org/) and [second link](http://wp-cli.org/). It should be displayed very nice!';
			$result = $method->invokeArgs( null, [ $desc ] );

			$expected = <<<'EOL'
This is a [reference link][1] and [second link][2]. It should be displayed very nice!

---
[1] https://wordpress.org/
[2] http://wp-cli.org/
EOL;
			$this->assertSame( $expected, $result );

			$desc   = <<<'EOL'
This is a [reference link](https://wordpress.org/) and [second link](http://wp-cli.org/).
It should be displayed very nice!
EOL;
			$result = $method->invokeArgs( null, [ $desc ] );

			$expected = <<<'EOL'
This is a [reference link][1] and [second link][2].
It should be displayed very nice!

---
[1] https://wordpress.org/
[2] http://wp-cli.org/
EOL;

			$this->assertSame( $expected, $result );

			$desc   = <<<'EOL'
This is a [reference link](https://wordpress.org/) and [second link](http://wp-cli.org/).
It should be displayed very nice!

## Example

It doesn't expect to be link here like [reference link](https://wordpress.org/).
EOL;
			$result = $method->invokeArgs( null, [ $desc ] );

			$expected = <<<'EOL'
This is a [reference link][1] and [second link][2].
It should be displayed very nice!

---
[1] https://wordpress.org/
[2] http://wp-cli.org/

## Example

It doesn't expect to be link here like [reference link](https://wordpress.org/).
EOL;

			$this->assertSame( $expected, $result );

			$desc   = <<<'EOL'
## Example

It doesn't expect to be link here like [reference link](https://wordpress.org/).
EOL;
			$result = $method->invokeArgs( null, [ $desc ] );

			$expected = <<<'EOL'
## Example

It doesn't expect to be link here like [reference link](https://wordpress.org/).
EOL;

			$this->assertSame( $expected, $result );

			$desc   = <<<'EOL'
This is a long description.
It doesn't have any link.

## Example

It doesn't expect to be link here like [reference link](https://wordpress.org/).
EOL;
			$result = $method->invokeArgs( null, [ $desc ] );

			$expected = <<<'EOL'
This is a long description.
It doesn't have any link.

## Example

It doesn't expect to be link here like [reference link](https://wordpress.org/).
EOL;

			$this->assertSame( $expected, $result );
		} finally {
			putenv( false === $original_force_hyperlink ? 'FORCE_HYPERLINK' : "FORCE_HYPERLINK=$original_force_hyperlink" );
		}
	}

	public function test_parse_reference_links_with_forced_hyperlinks(): void {
		$original_force_hyperlink = getenv( 'FORCE_HYPERLINK' );
		putenv( 'FORCE_HYPERLINK=1' );

		try {
			$test_class = new ReflectionClass( 'Help_Command' );
			$method     = $test_class->getMethod( 'parse_reference_links' );
			if ( PHP_VERSION_ID < 80100 ) {
				// @phpstan-ignore method.deprecated
				$method->setAccessible( true );
			}

			$desc   = 'This is a [reference link](https://wordpress.org/). It should be displayed very nice!';
			$result = $method->invokeArgs( null, [ $desc ] );

			$expected_link = "\033]8;;https://wordpress.org/\033\\reference link\033]8;;\033\\";
			$expected      = "This is a {$expected_link}. It should be displayed very nice!";

			$this->assertSame( $expected, $result );

			$desc   = <<<'EOL'
This is a [reference link](https://wordpress.org/) and [second link](http://wp-cli.org/).
It should be displayed very nice!

## Example

It doesn't expect to be link here like [reference link](https://wordpress.org/).
EOL;
			$result = $method->invokeArgs( null, [ $desc ] );

			$expected_link2 = "\033]8;;http://wp-cli.org/\033\\second link\033]8;;\033\\";
			$expected       = <<<EOL
This is a {$expected_link} and {$expected_link2}.
It should be displayed very nice!

## Example

It doesn't expect to be link here like [reference link](https://wordpress.org/).
EOL;

			$this->assertSame( $expected, $result );
		} finally {
			putenv( false === $original_force_hyperlink ? 'FORCE_HYPERLINK' : "FORCE_HYPERLINK=$original_force_hyperlink" );
		}
	}
}
