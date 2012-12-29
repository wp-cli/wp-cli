<?php

class CoreCommandSpec extends WP_CLI_Spec {

	/**
	 * @scenario
	 */
	public function emptyDir() {
		$this
			->given( 'empty dir' )

			->when( 'invoking core is-installed' )
			->then( 'return code should be', 1 );
	}

	/**
	 * @scenario
	 */
	public function noWpConfig() {
		$this
			->given( 'empty dir' )
			->and( 'wp files' )

			->when( 'invoking core is-installed' )
			->then( 'return code should be', 1 )

			->when( 'invoking core install' )
			->then( 'output should be',
				"Error: wp-config.php not found.\n" .
				"Either create one manually or use `wp core config`.\n"
			);
	}

	/**
	 * @scenario
	 */
	public function dbTablesNotInstalled() {
		$this
			->given( 'empty dir' )
			->and( 'wp files' )
			->and( 'wp config' )

			->when( 'invoking core is-installed' )
			->then( 'return code should be', 1 );
	}

	/**
	 * @scenario
	 */
	public function fullInstall() {
		$this
			->given( 'empty dir' )
			->and( 'wp install' )

			->when( 'invoking core is-installed' )
			->then( 'return code should be', 0 )

			->when( 'invoking post list --ids' )
			->then( 'output should be', 1 );
	}
}

