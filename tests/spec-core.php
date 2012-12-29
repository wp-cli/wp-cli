<?php

class CoreCommandSpec extends PHPUnit_Extensions_Story_TestCase {

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
			->then( 'return code should be', 1 );
	}

	/**
	 * @scenario
	 */
	public function notInstalled() {
		$this
			->given( 'empty dir' )
			->and( 'wp files' )
			->and( 'wp config' )
			->when( 'invoking core is-installed' )
			->then( 'return code should be', 1 );
	}

	public function runGiven( &$world, $action, $arguments ) {
		switch ( $action ) {
			case 'empty dir': {
				$world['temp_dir'] = sys_get_temp_dir() . '/' . uniqid( "wp-cli-test-", TRUE );
				mkdir( $world['temp_dir'] );
			}
			break;

			case 'wp files': {
				$installer = new Wordpress_Installer( $world['temp_dir'] );
				$installer->download_wordpress_files();
			}
			break;

			case 'wp config': {
				$installer = new Wordpress_Installer( $world['temp_dir'] );
				$installer->create_config( array(
					'dbname' => 'wp_cli_test',
					'dbuser' => 'wp_cli_test',
					'dbpass' => 'password1'
				) );
			}
			break;

			default: {
				return $this->notImplemented( $action );
			}
		}
	}

	public function runWhen( &$world, $action, $arguments ) {
		$cmd = str_replace( 'invoking ', '', $action );

		$runner = new Command_Runner( $world['temp_dir'] );
		$world['result'] = $runner->run_wp_cli( $cmd );
	}

	public function runThen( &$world, $action, $arguments ) {
		switch ( $action ) {
			case 'return code should be': {
				$this->assertEquals( $arguments[0], $world['result']->return_code );
			}
			break;

			default: {
				return $this->notImplemented( $action );
			}
		}
	}
}

