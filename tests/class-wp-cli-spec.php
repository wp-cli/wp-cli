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
				$installer->create_config( self::$db_settings );
			}
			break;

			case 'wp install': {
				$installer = new Wordpress_Installer( $world['temp_dir'] );
				$installer->download_wordpress_files();
				$installer->create_config( self::$db_settings );
				$installer->run_install();
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

