<?php

/**
 * Basic class
 *
 * ## EXAMPLES
 *
 *     # Foo.
 *     $ wp foo
 */
class CommandFactoryTests_Get_Doc_Comment_1_Command_Win extends WP_CLI_Command {
	/**
	 * Command1 method
	 *
	 * ## OPTIONS
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp foo command1 public
	 */
	function command1() {
	}

	/**
	 * Command2 function
	 *
	 * ## OPTIONS
	 *
	 * [--path=<path>]
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp foo command2 --path=/**a/**b/**c/**
	 */

final
			protected
			static
	function
			command2() {
	}

	/**
	 * Command3 function
	 *
	 * ## OPTIONS
	 *
	 * [--path=<path>]
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp foo command3 --path=/**a/**b/**c/**
	 function*/public function command3( $function ) {}

	function command4() {}
}

/**
 * Basic class
 *
 * ## EXAMPLES
 *
 *     # Foo.
 *     $ wp foo --final abstract
 class*/abstract class
  CommandFactoryTests_Get_Doc_Comment_2_Command_Win
 extends              WP_CLI_Command
    {
		function command1() {}
	}
