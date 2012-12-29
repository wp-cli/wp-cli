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
				$installer->full_install( self::$db_settings );
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

