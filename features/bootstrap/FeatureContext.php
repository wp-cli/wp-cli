<?php

use Behat\Behat\Context\ClosuredContextInterface,
    Behat\Behat\Context\TranslatedContextInterface,
    Behat\Behat\Context\BehatContext,
    Behat\Behat\Event\SuiteEvent;

use \WP_CLI\Process;
use \WP_CLI\Utils;

// Inside a community package
if ( file_exists( __DIR__ . '/utils.php' ) ) {
	require_once __DIR__ . '/utils.php';
	require_once __DIR__ . '/Process.php';
// Inside WP-CLI
} else {
	require_once __DIR__ . '/../../php/utils.php';
	require_once __DIR__ . '/../../php/WP_CLI/Process.php';
}

/**
 * Features context.
 */
class FeatureContext extends BehatContext implements ClosuredContextInterface {

	private static $cache_dir, $suite_cache_dir;

	private static $db_settings = array(
		'dbname' => 'wp_cli_test',
		'dbuser' => 'wp_cli_test',
		'dbpass' => 'password1'
	);

	public $variables = array();

	/**
	 * Get the environment variables required for launched `wp` processes
	 * @beforeSuite
	 */
	private static function get_process_env_variables() {
		// Ensure we're using the expected `wp` binary
		$bin_dir = getenv( 'WP_CLI_BIN_DIR' ) ?: realpath( __DIR__ . "/../../bin" );
		$env = array(
			'PATH' =>  $bin_dir . ':' . getenv( 'PATH' ),
			'BEHAT_RUN' => 1
		);
		if ( $config_path = getenv( 'WP_CLI_CONFIG_PATH' ) ) {
			$env['WP_CLI_CONFIG_PATH'] = $config_path;
		}
		return $env;
	}

	// We cache the results of `wp core download` to improve test performance
	// Ideally, we'd cache at the HTTP layer for more reliable tests
	private static function cache_wp_files() {
		self::$cache_dir = sys_get_temp_dir() . '/wp-cli-test core-download-cache';

		if ( is_readable( self::$cache_dir . '/wp-config-sample.php' ) )
			return;

		$cmd = Utils\esc_cmd( 'wp core download --force --path=%s', self::$cache_dir );
		Process::create( $cmd, null, self::get_process_env_variables() )->run_check();
	}

	/**
	 * @BeforeSuite
	 */
	public static function prepare( SuiteEvent $event ) {
		self::cache_wp_files();
	}

	/**
	 * @AfterSuite
	 */
	public static function afterSuite( SuiteEvent $event ) {
		if ( self::$suite_cache_dir ) {
			Process::create( Utils\esc_cmd( 'rm -r %s', self::$suite_cache_dir ), null, self::get_process_env_variables() )->run();
		}
	}

	/**
	 * @BeforeScenario
	 */
	public function beforeScenario( $event ) {
		$this->variables['SRC_DIR'] = realpath( __DIR__ . '/../..' );
	}

	/**
	 * @AfterScenario
	 */
	public function afterScenario( $event ) {
		if ( !isset( $this->variables['RUN_DIR'] ) )
			return;

		// remove altered WP install, unless there's an error
		if ( $event->getResult() < 4 ) {
			Process::create( Utils\esc_cmd( 'rm -r %s', $this->variables['RUN_DIR'] ), null, self::get_process_env_variables() )->run();
		}
	}

	public static function create_cache_dir() {
		self::$suite_cache_dir = sys_get_temp_dir() . '/' . uniqid( "wp-cli-test-suite-cache-", TRUE );
		mkdir( self::$suite_cache_dir );
		return self::$suite_cache_dir;
	}

	/**
	 * Initializes context.
	 * Every scenario gets it's own context object.
	 *
	 * @param array $parameters context parameters (set them up through behat.yml)
	 */
	public function __construct( array $parameters ) {
		$this->drop_db();
		$this->set_cache_dir();
		$this->variables['CORE_CONFIG_SETTINGS'] = Utils\assoc_args_to_str( self::$db_settings );
	}

	public function getStepDefinitionResources() {
		return glob( __DIR__ . '/../steps/*.php' );
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

	public function create_run_dir() {
		if ( !isset( $this->variables['RUN_DIR'] ) ) {
			$this->variables['RUN_DIR'] = sys_get_temp_dir() . '/' . uniqid( "wp-cli-test-run-", TRUE );
			mkdir( $this->variables['RUN_DIR'] );
		}
	}

	public function build_phar( $version = 'same' ) {
		$this->variables['PHAR_PATH'] = $this->variables['RUN_DIR'] . '/' . uniqid( "wp-cli-build-", TRUE ) . '.phar';

		Process::create(
			Utils\esc_cmd(
				'php -dphar.readonly=0 %1$s %2$s --version=%3$s && chmod +x %2$s',
				__DIR__ . '/../../utils/make-phar.php',
				$this->variables['PHAR_PATH'],
				$version
			),
			null,
			self::get_process_env_variables()
		)->run_check();
	}

	private function set_cache_dir() {
		$path = sys_get_temp_dir() . '/wp-cli-test-cache';
		Process::create( Utils\esc_cmd( 'mkdir -p %s', $path ), null, self::get_process_env_variables() )->run_check();
		$this->variables['CACHE_DIR'] = $path;
	}

	private static function run_sql( $sql ) {
		Utils\run_mysql_command( 'mysql --no-defaults', array(
			'execute' => $sql,
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

	public function proc( $command, $assoc_args = array(), $path = '' ) {
		if ( !empty( $assoc_args ) )
			$command .= Utils\assoc_args_to_str( $assoc_args );

		$env = self::get_process_env_variables();
		if ( isset( $this->variables['SUITE_CACHE_DIR'] ) ) {
			$env['WP_CLI_CACHE_DIR'] = $this->variables['SUITE_CACHE_DIR'];
		}

		$path = "{$this->variables['RUN_DIR']}/{$path}";
		return Process::create( $command, $path, $env );
	}

	public function move_files( $src, $dest ) {
		rename( $this->variables['RUN_DIR'] . "/$src", $this->variables['RUN_DIR'] . "/$dest" );
	}

	public function add_line_to_wp_config( &$wp_config_code, $line ) {
		$token = "/* That's all, stop editing!";

		$wp_config_code = str_replace( $token, "$line\n\n$token", $wp_config_code );
	}

	public function download_wp( $subdir = '' ) {
		$dest_dir = $this->variables['RUN_DIR'] . "/$subdir";

		if ( $subdir ) {
			mkdir( $dest_dir );
		}

		Process::create( Utils\esc_cmd( "cp -r %s/* %s", self::$cache_dir, $dest_dir ), null, self::get_process_env_variables() )->run_check();

		// disable emailing
		mkdir( $dest_dir . '/wp-content/mu-plugins' );
		copy( __DIR__ . '/../extra/no-mail.php', $dest_dir . '/wp-content/mu-plugins/no-mail.php' );
	}

	public function create_config( $subdir = '' ) {
		$params = self::$db_settings;
		$params['dbprefix'] = $subdir ?: 'wp_';

		$params['skip-salts'] = true;
		$this->proc( 'wp core config', $params, $subdir )->run_check();
	}

	public function install_wp( $subdir = '' ) {
		$this->create_db();
		$this->create_run_dir();
		$this->download_wp( $subdir );

		$this->create_config( $subdir );

		$install_args = array(
			'url' => 'http://example.com',
			'title' => 'WP CLI Site',
			'admin_user' => 'admin',
			'admin_email' => 'admin@example.com',
			'admin_password' => 'password1'
		);

		$this->proc( 'wp core install', $install_args, $subdir )->run_check();
	}
}

