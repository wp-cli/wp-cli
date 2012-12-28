<?php

WP_CLI::add_command( 'eval', new Eval_Command );

class Eval_Command extends WP_CLI_Command {

	/**
	 * Executes arbitrary PHP code after loading WordPress.
	 *
	 * @synopsis <php-code>
	 */
	public function __invoke( $args, $assoc_args ) {
		eval( $args[0] );
	}
}

