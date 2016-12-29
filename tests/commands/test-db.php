<?php
$root = __DIR__ . '/../..';
require_once "$root/vendor/autoload.php";
require_once "$root/php/class-wp-cli.php";
require_once "$root/php/class-wp-cli-command.php";
require_once "$root/php/commands/db.php";

define( 'DB_NAME', 'test_db_name' );
define( 'DB_HOST', 'test_db_host' );
define( 'DB_USER', 'test_db_user' );
define( 'DB_PASSWORD', 'test_db_password' );

class DBCommandTests extends PHPUnit_Framework_TestCase {
	private $instance;

	function testCli() {
		$expected_args = [
			'database' => DB_NAME,
			'host' => DB_HOST,
			'user' => DB_USER,
			'pass' => DB_PASSWORD,
		];
		$this->assertCli( [], $expected_args );

		$expected_args['database'] = 'some_other_database';
		$this->assertCli( ['database' => 'some_other_database'], $expected_args );
		$expected_args['database'] = DB_NAME;

		define( 'DB_CHARSET', 'some_charset' );
		$expected_args['default-character-set'] = 'some_charset';
		$args = $this->assertCli( [], $expected_args );

		$expected_args['default-character-set'] = 'some_other_charset';
		$args = $this->assertCli( ['default-character-set' => 'some_other_charset'], $expected_args );
	}

	private function getInstance() {
		if($this->instance == null){
			$this->instance = new TestCommandInstance();
		}

		return $this->instance;
	}

	private function assertCli( $assoc_args, $expected_args ) {
		$instance = $this->getInstance();
		
		$instance->cli( [], $assoc_args );
		$this->assertEquals( 'mysql --no-defaults --no-auto-rehash', $instance->getLastCmd() );
		
		$args = $instance->getLastArgs();
		$this->assertEquals( count( $expected_args ), count( $args ) );

		foreach($expected_args as $key => $value){
			$this->assertEquals( $value, $args[$key] );
		}
	}
}

class TestCommandInstance extends DB_Command {
	private static $lastCmd;
	private static $lastArgs;

	protected static function run_mysql_command( $cmd, $final_args, $descriptors ) {
		static::$lastCmd = $cmd;
		static::$lastArgs = $final_args;
	}

	public static function getLastCmd() {
		return static::$lastCmd;
	}

	public static function getLastArgs() {
		return static::$lastArgs;
	}
}

