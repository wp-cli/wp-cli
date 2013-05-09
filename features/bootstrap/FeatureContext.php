<?php

use Behat\Behat\Context\ClosuredContextInterface,
    Behat\Behat\Context\TranslatedContextInterface,
    Behat\Behat\Context\BehatContext,
    Behat\Behat\Event\SuiteEvent;

use \WP_CLI\Utils;

require_once 'PHPUnit/Framework/Assert/Functions.php';

require_once __DIR__ . '/../../php/utils.php';

/**
 * Features context.
 */
class FeatureContext extends BehatContext implements ClosuredContextInterface {

	private static $cache_dir;

	private static $db_settings = array(
		'dbname' => 'wp_cli_test',
		'dbuser' => 'wp_cli_test',
		'dbpass' => 'password1'
	);

	private static $additional_args;

	private $install_dir;

	public $variables = array();

	private static function wp_cli( $command ) {
		return __DIR__ . "/../../bin/wp $command";
	}

	// We cache the results of `wp core download` to improve test performance
	// Ideally, we'd cache at the HTTP layer for more reliable tests
	private static function cache_wp_files() {
		self::$cache_dir = sys_get_temp_dir() . '/wp-cli-test-core-download-cache';

		if ( is_readable( self::$cache_dir . '/wp-config-sample.php' ) )
			return;

		$cmd = Utils\esc_cmd( 'core download --force --path=%s', self::$cache_dir );
		Process::create( self::wp_cli( $cmd ) )->run_check();
	}

	/**
	 * @BeforeSuite
	 */
	public static function prepare( SuiteEvent $event ) {
		self::cache_wp_files();

		self::$additional_args = array(
			'core config' => self::$db_settings,

			'core install' => array(
				'url' => 'http://example.com',
				'title' => 'WP CLI Site',
				'admin_email' => 'admin@example.com',
				'admin_password' => 'password1'
			),

			'core install-network' => array(
				'title' => 'WP CLI Network'
			)
		);
	}

	/**
	 * Initializes context.
	 * Every scenario gets it's own context object.
	 *
	 * @param array $parameters context parameters (set them up through behat.yml)
	 */
	public function __construct( array $parameters ) {
		$this->drop_db();
	}

	public function getStepDefinitionResources() {
		return array( __DIR__ . '/../steps/basic_steps.php' );
	}

	public function getHookDefinitionResources() {
		return array();
	}

	public function replace_variables( $str ) {
		return preg_replace_callback( '/\{([A-Z_]+)\}/', array( $this, '_replace_var' ), $str );
	}

	private function _replace_var( $matches ) {
		$cmd = $matches[0];

		foreach ( array_slice( $matches, 1 ) as $key ) {
			$cmd = str_replace( '{' . $key . '}', $this->variables[ $key ], $cmd );
		}

		return $cmd;
	}

	public function create_empty_dir() {
		$this->install_dir = sys_get_temp_dir() . '/' . uniqid( "wp-cli-test-", TRUE );
		mkdir( $this->install_dir );
	}

	public function get_path( $file ) {
		return $this->install_dir . '/' . $file;
	}

	public function get_cache_path( $file ) {
		static $path;

		if ( !$path ) {
			$path = sys_get_temp_dir() . '/wp-cli-test-cache';
			Process::create( Utils\esc_cmd( 'mkdir -p %s', $path ) )->run_check();
		}

		return $path . '/' . $file;
	}

	public function download_file( $url, $path ) {
		Process::create( Utils\esc_cmd( 'curl -sSL %s > %s', $url, $path ) )->run_check();
	}

	private static function run_sql( $sql ) {
		Utils\run_mysql_query( $sql, array(
			'host' => 'localhost',
			'user' => self::$db_settings['dbuser'],
			'pass' => self::$db_settings['dbpass'],
		) );
	}

	public function create_db() {
		$dbname = self::$db_settings['dbname'];
		self::run_sql( "CREATE DATABASE IF NOT EXISTS $dbname" );
	}

	public function drop_db() {
		$dbname = self::$db_settings['dbname'];
		self::run_sql( "DROP DATABASE IF EXISTS $dbname" );
	}

	public function proc( $command, $assoc_args = array() ) {
		if ( isset( self::$additional_args[ $command ] ) ) {
			$assoc_args = array_merge( self::$additional_args[ $command ],
				$assoc_args );
		}

		if ( !empty( $assoc_args ) )
			$command .= Utils\assoc_args_to_str( $assoc_args );

		return Process::create( self::wp_cli( $command ), $this->install_dir );
	}

	public function move_files( $src, $dest ) {
		rename( $this->get_path( $src ), $this->get_path( $dest ) );
	}

	public function add_line_to_wp_config( &$wp_config_code, $line ) {
		$token = "/* That's all, stop editing!";

		$wp_config_code = str_replace( $token, "$line\n\n$token", $wp_config_code );
	}

	public function download_wordpress_files( $subdir = '' ) {
		$dest_dir = $this->get_path( $subdir );

		if ( $subdir ) mkdir( $dest_dir );

		Process::create( Utils\esc_cmd( "cp -r %s/* %s", self::$cache_dir, $dest_dir ) )->run_check();
	}

	public function wp_install( $subdir = '' ) {
		$this->create_db();
		$this->create_empty_dir();
		$this->download_wordpress_files( $subdir );

		$this->proc( 'core config', array( 'dbprefix' => $subdir ? $subdir : 'wp_' ) )->run_check( $subdir );

		$this->proc( 'core install' )->run_check( $subdir );
	}
}

