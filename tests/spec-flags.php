<?php

class FlagsSpec extends WP_CLI_Spec {

	/** @scenario */
	public function quietRun() {
		$this
			->given( 'wp install' )

			->when( 'invoking', '' )
			->then( 'return code should be', 0 )
			->and( 'should have output' )

			->when( 'invoking', '--quiet' )
			->then( 'return code should be', 0 )
			->and( 'stdout', '' )

			->when( 'invoking', 'non-existing-command --quiet' )
			->then( 'return code should be', 1 )
			->and( 'stderr',
				"Error: 'non-existing-command' is not a registered wp command. See 'wp help'.\n"
			);
	}
}

