<?php

abstract class WP_CLI_Spec extends PHPUnit_Extensions_Story_TestCase {

	protected static $db_settings = array(
		'dbname' => 'wp_cli_test',
		'dbuser' => 'wp_cli_test',
		'dbpass' => 'password1'
	);

	private static function run_sql( $sql ) {
		$dbuser = self::$db_settings['dbuser'];
		$dbpass = self::$db_settings['dbpass'];

		exec( "mysql -u$dbuser -p$dbpass -e '$sql'" );
	}

	protected function setUp() {
		$dbname = self::$db_settings['dbname'];
		$this->run_sql( "DROP DATABASE $dbname" );
		$this->run_sql( "CREATE DATABASE $dbname" );
	}

	public function runGiven( &$world, $action, $arguments ) {
		switch ( $action ) {
			case 'empty dir': {
				$world['runner'] = new WP_CLI_Command_Runner;
			}
			break;

			case 'wp files': {
				$world['runner']->download_wordpress_files();
			}
			break;

			case 'wp config': {
				$world['runner']->create_config( self::$db_settings );
			}
			break;

			case 'wp install': {
				$world['runner']->download_wordpress_files();
				$world['runner']->create_config( self::$db_settings );
				$world['runner']->run_install();
			}
			break;

			default: {
				return $this->notImplemented( $action );
			}
		}
	}

	public function runWhen( &$world, $action, $arguments ) {
		$cmd = str_replace( 'invoking ', '', $action );

		$world['result'] = $world['runner']->run( $cmd );
	}

	public function runThen( &$world, $action, $arguments ) {
		switch ( $action ) {
			case 'return code should be': {
				$this->assertEquals( $arguments[0], $world['result']->return_code );
			}
			break;

			case 'output should be': {
				$this->assertEquals( $arguments[0], $world['result']->output );
			}
			break;

			default: {
				return $this->notImplemented( $action );
			}
		}
	}
}

