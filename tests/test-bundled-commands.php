<?php

use WP_CLI\Utils;
use WP_CLI\Dispatcher\CompositeCommand;

// Mock class to test whether `CLI_Command` will be found.
class CLI_Command extends CompositeCommand {

	public function __construct() { }
}

// Mock class to test whether `Random_Unknown_Command` will be found.
class Random_Unknown_Command extends CompositeCommand {

	public function __construct() { }
}

class BundledCommandTest extends PHPUnit_Framework_TestCase {

	/** @dataProvider dataProviderIsBundledCommands */
	public function testIsBundledCommand( $command, $expected_result ) {
		$result = Utils\is_bundled_command( $command );
		$this->assertEquals( $expected_result, $result );
	}

	public function dataProviderIsBundledCommands() {
		return array(
			// Bundled commands.
			array( 'CLI_Command', true ),
			array( new CLI_Command(), true ),

			// Commands not bundled.
			array( 'Random_Unknown_Command', false ),
			array( new Random_Unknown_Command(), false ),

			// Wrong data types.
			array( array( 'CLI_Command' ), false ),
			array( new stdClass(), false ),
			array( 42, false ),
			array( null, false ),
		);
	}
}
