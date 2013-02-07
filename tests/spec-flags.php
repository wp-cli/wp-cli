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
			->and( 'output should be', '' )

			->when( 'invoking', 'wp non-existing-command --quiet' )
			->then( 'return code should be', 1 )
			->and( 'should have output' );
	}
}

