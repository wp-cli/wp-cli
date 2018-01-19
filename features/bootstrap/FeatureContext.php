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
	require_once __DIR__ . '/ProcessRun.php';
	$project_composer = dirname( dirname( dirname( __FILE__ ) ) ) . '/composer.json';
	if ( file_exists( $project_composer ) ) {
		$composer = json_decode( file_get_contents( $project_composer ) );
		if ( ! empty( $composer->autoload->files ) ) {
			$contents = 'require:' . PHP_EOL;
			foreach( $composer->autoload->files as $file ) {
				$contents .= '  - ' . dirname( dirname( dirname( __FILE__ ) ) ) . '/' . $file . PHP_EOL;
			}
			@mkdir( sys_get_temp_dir() . '/wp-cli-package-test/' );
			$project_config = sys_get_temp_dir() . '/wp-cli-package-test/config.yml';
			file_put_contents( $project_config, $contents );
			putenv( 'WP_CLI_CONFIG_PATH=' . $project_config );
		}
	}
// Inside WP-CLI
} else {
	require_once __DIR__ . '/../../php/utils.php';
	require_once __DIR__ . '/../../php/WP_CLI/Process.php';
	require_once __DIR__ . '/../../php/WP_CLI/ProcessRun.php';
	if ( file_exists( __DIR__ . '/../../vendor/autoload.php' ) ) {
		require_once __DIR__ . '/../../vendor/autoload.php';
	} else if ( file_exists( __DIR__ . '/../../../../autoload.php' ) ) {
		require_once __DIR__ . '/../../../../autoload.php';
	}
}

/**
 * Features context.
 */
class FeatureContext extends BehatContext implements ClosuredContextInterface {

	/**
	 * The current working directory for scenarios that have a "Given a WP installation" or "Given an empty directory" step. Variable RUN_DIR. Lives until the end of the scenario.
	 */
	private static $run_dir;

	/**
	 * Where WordPress core is downloaded to for caching, and which is copied to RUN_DIR during a "Given a WP installation" step. Lives until manually deleted.
	 */
	private static $cache_dir;

	/**
	 * The directory that holds the install cache, and which is copied to RUN_DIR during a "Given a WP installation" step. Recreated on each suite run.
	 */
	private static $install_cache_dir;

	/**
	 * The directory that the WP-CLI cache (WP_CLI_CACHE_DIR, normally "$HOME/.wp-cli/cache") is set to on a "Given an empty cache" step.
	 * Variable SUITE_CACHE_DIR. Lives until the end of the scenario (or until another "Given an empty cache" step within the scenario).
	 */
	private static $suite_cache_dir;

	/**
	 * Where the current WP-CLI source repository is copied to for Composer-based tests with a "Given a dependency on current wp-cli" step.
	 * Variable COMPOSER_LOCAL_REPOSITORY. Lives until the end of the suite.
	 */
	private static $composer_local_repository;

	/**
	 * The test database settings. All but `dbname` can be set via environment variables. The database is dropped at the start of each scenario and created on a "Given a WP installation" step.
	 */
	private static $db_settings = array(
		'dbname' => 'wp_cli_test',
		'dbuser' => 'wp_cli_test',
		'dbpass' => 'password1',
		'dbhost' => '127.0.0.1',
	);

	/**
	 * Array of background process ids started by the current scenario. Used to terminate them at the end of the scenario.
	 */
	private $running_procs = array();

	/**
	 * Array of variables available as {VARIABLE_NAME}. Some are always set: CORE_CONFIG_SETTINGS, SRC_DIR, CACHE_DIR, WP_VERSION-version-latest.
	 * Some are step-dependent: RUN_DIR, SUITE_CACHE_DIR, COMPOSER_LOCAL_REPOSITORY, PHAR_PATH. One is set on use: INVOKE_WP_CLI_WITH_PHP_ARGS-args.
	 * Scenarios can define their own variables using "Given save" steps. Variables are reset for each scenario.
	 */
	public $variables = array();

	/**
	 * The current feature file and scenario line number as '<file>.<line>'. Used in RUN_DIR and SUITE_CACHE_DIR directory names. Set at the start of each scenario.
	 */
	private static $temp_dir_infix;

	/**
	 * Settings and variables for WP_CLI_TEST_LOG_RUN_TIMES run time logging.
	 */
	private static $log_run_times; // Whether to log run times - WP_CLI_TEST_LOG_RUN_TIMES env var. Set on `@BeforeScenario'.
	private static $suite_start_time; // When the suite started, set on `@BeforeScenario'.
	private static $output_to; // Where to output log - stdout|error_log. Set on `@BeforeSuite`.
	private static $num_top_processes; // Number of processes/methods to output by longest run times. Set on `@BeforeSuite`.
	private static $num_top_scenarios; // Number of scenarios to output by longest run times. Set on `@BeforeSuite`.

	private static $scenario_run_times = array(); // Scenario run times (top `self::$num_top_scenarios` only).
	private static $scenario_count = 0; // Scenario count, incremented on `@AfterScenario`.
	private static $proc_method_run_times = array(); // Array of run time info for proc methods, keyed by method name and arg, each a 2-element array containing run time and run count.

	/**
	 * Get the environment variables required for launched `wp` processes
	 */
	private static function get_process_env_variables() {
		// Ensure we're using the expected `wp` binary
		$bin_dir = getenv( 'WP_CLI_BIN_DIR' ) ?: realpath( __DIR__ . '/../../bin' );
		$vendor_dir = realpath( __DIR__ . '/../../vendor/bin' );
		$path_separator = Utils\is_windows() ? ';' : ':';
		$env = array(
			'PATH' =>  $bin_dir . $path_separator . $vendor_dir . $path_separator . getenv( 'PATH' ),
			'BEHAT_RUN' => 1,
			'HOME' => sys_get_temp_dir() . '/wp-cli-home',
		);
		if ( $config_path = getenv( 'WP_CLI_CONFIG_PATH' ) ) {
			$env['WP_CLI_CONFIG_PATH'] = $config_path;
		}
		if ( $term = getenv( 'TERM' ) ) {
			$env['TERM'] = $term;
		}
		if ( $php_args = getenv( 'WP_CLI_PHP_ARGS' ) ) {
			$env['WP_CLI_PHP_ARGS'] = $php_args;
		}
		if ( $php_used = getenv( 'WP_CLI_PHP_USED' ) ) {
			$env['WP_CLI_PHP_USED'] = $php_used;
		}
		if ( $php = getenv( 'WP_CLI_PHP' ) ) {
			$env['WP_CLI_PHP'] = $php;
		}
		if ( $travis_build_dir = getenv( 'TRAVIS_BUILD_DIR' ) ) {
			$env['TRAVIS_BUILD_DIR'] = $travis_build_dir;
		}
		if ( $github_token = getenv( 'GITHUB_TOKEN' ) ) {
			$env['GITHUB_TOKEN'] = $github_token;
		}
		return $env;
	}

	/**
	 * We cache the results of `wp core download` to improve test performance.
	 * Ideally, we'd cache at the HTTP layer for more reliable tests.
	 */
	private static function cache_wp_files() {
		$wp_version_suffix = ( $wp_version = getenv( 'WP_VERSION' ) ) ? "-$wp_version" : '';
		self::$cache_dir = sys_get_temp_dir() . '/wp-cli-test-core-download-cache' . $wp_version_suffix;

		if ( is_readable( self::$cache_dir . '/wp-config-sample.php' ) )
			return;

		$cmd = Utils\esc_cmd( 'wp core download --force --path=%s', self::$cache_dir );
		if ( $wp_version ) {
			$cmd .= Utils\esc_cmd( ' --version=%s', $wp_version );
		}
		Process::create( $cmd, null, self::get_process_env_variables() )->run_check();
	}

	/**
	 * @BeforeSuite
	 */
	public static function prepare( SuiteEvent $event ) {
		// Test performance statistics - useful for detecting slow tests.
		if ( self::$log_run_times = getenv( 'WP_CLI_TEST_LOG_RUN_TIMES' ) ) {
			self::log_run_times_before_suite( $event );
		}

		$result = Process::create( 'wp cli info', null, self::get_process_env_variables() )->run_check();
		echo PHP_EOL;
		echo $result->stdout;
		echo PHP_EOL;
		self::cache_wp_files();
		$result = Process::create( Utils\esc_cmd( 'wp core version --path=%s', self::$cache_dir ) , null, self::get_process_env_variables() )->run_check();
		echo 'WordPress ' . $result->stdout;
		echo PHP_EOL;

		// Remove install cache if any (not setting the static var).
		$wp_version_suffix = ( $wp_version = getenv( 'WP_VERSION' ) ) ? "-$wp_version" : '';
		$install_cache_dir = sys_get_temp_dir() . '/wp-cli-test-core-install-cache' . $wp_version_suffix;
		if ( file_exists( $install_cache_dir ) ) {
			self::remove_dir( $install_cache_dir );
		}
	}

	/**
	 * @AfterSuite
	 */
	public static function afterSuite( SuiteEvent $event ) {
		if ( self::$composer_local_repository ) {
			self::remove_dir( self::$composer_local_repository );
			self::$composer_local_repository = null;
		}

		if ( self::$log_run_times ) {
			self::log_run_times_after_suite( $event );
		}
	}

	/**
	 * @BeforeScenario
	 */
	public function beforeScenario( $event ) {
		if ( self::$log_run_times ) {
			self::log_run_times_before_scenario( $event );
		}

		$this->variables['SRC_DIR'] = realpath( __DIR__ . '/../..' );

		// Used in the names of the RUN_DIR and SUITE_CACHE_DIR directories.
		self::$temp_dir_infix = null;
		if ( $file = self::get_event_file( $event, $line ) ) {
			self::$temp_dir_infix = basename( $file ) . '.' . $line;
		}
	}

	/**
	 * @AfterScenario
	 */
	public function afterScenario( $event ) {

		if ( self::$run_dir ) {
			// remove altered WP install, unless there's an error
			if ( $event->getResult() < 4 ) {
				self::remove_dir( self::$run_dir );
			}
			self::$run_dir = null;
		}

		// Remove WP-CLI package directory if any. Set to `wp package path` by package-command and scaffold-package-command features, and by cli-info.feature.
		if ( isset( $this->variables['PACKAGE_PATH'] ) ) {
			self::remove_dir( $this->variables['PACKAGE_PATH'] );
		}

		// Remove SUITE_CACHE_DIR if any.
		if ( self::$suite_cache_dir ) {
			self::remove_dir( self::$suite_cache_dir );
			self::$suite_cache_dir = null;
		}

		// Remove any background processes.
		foreach ( $this->running_procs as $proc ) {
			$status = proc_get_status( $proc );
			self::terminate_proc( $status['pid'] );
		}

		if ( self::$log_run_times ) {
			self::log_run_times_after_scenario( $event );
		}
	}

	/**
	 * Terminate a process and any of its children.
	 */
	private static function terminate_proc( $master_pid ) {

		$output = `ps -o ppid,pid,command | grep $master_pid`;

		foreach ( explode( PHP_EOL, $output ) as $line ) {
			if ( preg_match( '/^\s*(\d+)\s+(\d+)/', $line, $matches ) ) {
				$parent = $matches[1];
				$child = $matches[2];

				if ( $parent == $master_pid ) {
					self::terminate_proc( $child );
				}
			}
		}

		if ( ! posix_kill( (int) $master_pid, 9 ) ) {
			$errno = posix_get_last_error();
			// Ignore "No such process" error as that's what we want.
			if ( 3 /*ESRCH*/ !== $errno ) {
				throw new RuntimeException( posix_strerror( $errno ) );
			}
		}
	}

	/**
	 * Create a temporary WP_CLI_CACHE_DIR. Exposed as SUITE_CACHE_DIR in "Given an empty cache" step.
	 */
	public static function create_cache_dir() {
		if ( self::$suite_cache_dir ) {
			self::remove_dir( self::$suite_cache_dir );
		}
		self::$suite_cache_dir = sys_get_temp_dir() . '/' . uniqid( 'wp-cli-test-suite-cache-' . self::$temp_dir_infix . '-', TRUE );
		mkdir( self::$suite_cache_dir );
		return self::$suite_cache_dir;
	}

	/**
	 * Initializes context.
	 * Every scenario gets its own context object.
	 *
	 * @param array $parameters context parameters (set them up through behat.yml)
	 */
	public function __construct( array $parameters ) {
		if ( getenv( 'WP_CLI_TEST_DBUSER' ) ) {
			self::$db_settings['dbuser'] = getenv( 'WP_CLI_TEST_DBUSER' );
		}

		if ( false !== getenv( 'WP_CLI_TEST_DBPASS' ) ) {
			self::$db_settings['dbpass'] = getenv( 'WP_CLI_TEST_DBPASS' );
		}

		if ( getenv( 'WP_CLI_TEST_DBHOST' ) ) {
			self::$db_settings['dbhost'] = getenv( 'WP_CLI_TEST_DBHOST' );
		}

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

	/**
	 * Replace standard {VARIABLE_NAME} variables and the special {INVOKE_WP_CLI_WITH_PHP_ARGS-args} and {WP_VERSION-version-latest} variables.
	 * Note that standard variable names can only contain uppercase letters, digits and underscores and cannot begin with a digit.
	 */
	public function replace_variables( $str ) {
		if ( false !== strpos( $str, '{INVOKE_WP_CLI_WITH_PHP_ARGS-' ) ) {
			$str = $this->replace_invoke_wp_cli_with_php_args( $str );
		}
		$str = preg_replace_callback( '/\{([A-Z_][A-Z_0-9]*)\}/', array( $this, 'replace_var' ), $str );
		if ( false !== strpos( $str, '{WP_VERSION-' ) ) {
			$str = $this->replace_wp_versions( $str );
		}
		return $str;
	}

	/**
	 * Substitute {INVOKE_WP_CLI_WITH_PHP_ARGS-args} variables.
	 */
	private function replace_invoke_wp_cli_with_php_args( $str ) {
		static $phar_path = null, $shell_path = null;

		if ( null === $phar_path ) {
			$phar_path = false;
			$phar_begin = '#!/usr/bin/env php';
			$phar_begin_len = strlen( $phar_begin );
			if ( ( $bin_dir = getenv( 'WP_CLI_BIN_DIR' ) ) && file_exists( $bin_dir . '/wp' ) && $phar_begin === file_get_contents( $bin_dir . '/wp', false, null, 0, $phar_begin_len ) ) {
				$phar_path = $bin_dir . '/wp';
			} else {
				$src_dir = dirname( dirname( __DIR__ ) );
				$bin_path = $src_dir . '/bin/wp';
				$vendor_bin_path = $src_dir . '/vendor/bin/wp';
				if ( file_exists( $bin_path ) && is_executable( $bin_path ) ) {
					$shell_path = $bin_path;
				} elseif ( file_exists( $vendor_bin_path ) && is_executable( $vendor_bin_path ) ) {
					$shell_path = $vendor_bin_path;
				} else {
					$shell_path = 'wp';
				}
			}
		}

		$str = preg_replace_callback( '/{INVOKE_WP_CLI_WITH_PHP_ARGS-([^}]*)}/', function ( $matches ) use ( $phar_path, $shell_path ) {
			return $phar_path ? "php {$matches[1]} {$phar_path}" : ( 'WP_CLI_PHP_ARGS=' . escapeshellarg( $matches[1] ) . ' ' . $shell_path );
		}, $str );

		return $str;
	}

	/**
	 * Replace variables callback.
	 */
	private function replace_var( $matches ) {
		$cmd = $matches[0];

		foreach ( array_slice( $matches, 1 ) as $key ) {
			$cmd = str_replace( '{' . $key . '}', $this->variables[ $key ], $cmd );
		}

		return $cmd;
	}

	/**
	 * Substitute {WP_VERSION-version-latest} variables.
	 */
	private function replace_wp_versions( $str ) {
		static $wp_versions = null;
		if ( null === $wp_versions ) {
			$wp_versions = array();

			$response = Requests::get( 'https://api.wordpress.org/core/version-check/1.7/', null, array( 'timeout' => 30 ) );
			if ( 200 === $response->status_code && ( $body = json_decode( $response->body ) ) && is_object( $body ) && isset( $body->offers ) && is_array( $body->offers ) ) {
				// Latest version alias.
				$wp_versions["{WP_VERSION-latest}"] = count( $body->offers ) ? $body->offers[0]->version : '';
				foreach ( $body->offers as $offer ) {
					$sub_ver = preg_replace( '/(^[0-9]+\.[0-9]+)\.[0-9]+$/', '$1', $offer->version );
					$sub_ver_key = "{WP_VERSION-{$sub_ver}-latest}";

					$main_ver = preg_replace( '/(^[0-9]+)\.[0-9]+$/', '$1', $sub_ver );
					$main_ver_key = "{WP_VERSION-{$main_ver}-latest}";

					if ( ! isset( $wp_versions[ $main_ver_key ] ) ) {
						$wp_versions[ $main_ver_key ] = $offer->version;
					}
					if ( ! isset( $wp_versions[ $sub_ver_key ] ) ) {
						$wp_versions[ $sub_ver_key ] = $offer->version;
					}
				}
			}
		}
		return strtr( $str, $wp_versions );
	}

	/**
	 * Get the file and line number for the current behat event.
	 */
	private static function get_event_file( $event, &$line ) {
		if ( method_exists( $event, 'getScenario' ) ) {
			$scenario_feature = $event->getScenario();
		} elseif ( method_exists( $event, 'getFeature' ) ) {
			$scenario_feature = $event->getFeature();
		} elseif ( method_exists( $event, 'getOutline' ) ) {
			$scenario_feature = $event->getOutline();
		} else {
			return null;
		}
		$line = $scenario_feature->getLine();
		return $scenario_feature->getFile();
	}

	/**
	 * Create the RUN_DIR directory, unless already set for this scenario.
	 */
	public function create_run_dir() {
		if ( !isset( $this->variables['RUN_DIR'] ) ) {
			self::$run_dir = $this->variables['RUN_DIR'] = sys_get_temp_dir() . '/' . uniqid( 'wp-cli-test-run-' . self::$temp_dir_infix . '-', TRUE );
			mkdir( $this->variables['RUN_DIR'] );
		}
	}

	public function build_phar( $version = 'same' ) {
		$this->variables['PHAR_PATH'] = $this->variables['RUN_DIR'] . '/' . uniqid( "wp-cli-build-", TRUE ) . '.phar';

		// Test running against a package installed as a WP-CLI dependency
		// WP-CLI installed as a project dependency
		$make_phar_path = __DIR__ . '/../../../../../utils/make-phar.php';
		if ( ! file_exists( $make_phar_path ) ) {
			// Test running against WP-CLI proper
			$make_phar_path = __DIR__ . '/../../utils/make-phar.php';
			if ( ! file_exists( $make_phar_path ) ) {
				// WP-CLI as a dependency of this project
				$make_phar_path = __DIR__ . '/../../vendor/wp-cli/wp-cli/utils/make-phar.php';
			}
		}

		$this->proc( Utils\esc_cmd(
			'php -dphar.readonly=0 %1$s %2$s --version=%3$s && chmod +x %2$s',
			$make_phar_path,
			$this->variables['PHAR_PATH'],
			$version
		) )->run_check();
	}

	public function download_phar( $version = 'same' ) {
		if ( 'same' === $version ) {
			$version = WP_CLI_VERSION;
		}

		$download_url = sprintf(
			'https://github.com/wp-cli/wp-cli/releases/download/v%1$s/wp-cli-%1$s.phar',
			$version
		);

		$this->variables['PHAR_PATH'] = $this->variables['RUN_DIR'] . '/'
		                                . uniqid( 'wp-cli-download-', true )
		                                . '.phar';

		Process::create( Utils\esc_cmd(
			'curl -sSfL %1$s > %2$s && chmod +x %2$s',
			$download_url,
			$this->variables['PHAR_PATH']
		) )->run_check();
	}

	/**
	 * CACHE_DIR is a cache for downloaded test data such as images. Lives until manually deleted.
	 */
	private function set_cache_dir() {
		$path = sys_get_temp_dir() . '/wp-cli-test-cache';
		if ( ! file_exists( $path ) ) {
			mkdir( $path );
		}
		$this->variables['CACHE_DIR'] = $path;
	}

	/**
	 * Run a MySQL command with `$db_settings`.
	 *
	 * @param string $sql_cmd Command to run.
	 * @param array $assoc_args Optional. Associative array of options. Default empty.
	 * @param bool $add_database Optional. Whether to add dbname to the $sql_cmd. Default false.
	 */
	private static function run_sql( $sql_cmd, $assoc_args = array(), $add_database = false ) {
		$default_assoc_args = array(
			'host' => self::$db_settings['dbhost'],
			'user' => self::$db_settings['dbuser'],
			'pass' => self::$db_settings['dbpass'],
		);
		if ( $add_database ) {
			$sql_cmd .= ' ' . escapeshellarg( self::$db_settings['dbname'] );
		}
		$start_time = microtime( true );
		Utils\run_mysql_command( $sql_cmd, array_merge( $assoc_args, $default_assoc_args ) );
		if ( self::$log_run_times ) {
			self::log_proc_method_run_time( 'run_sql ' . $sql_cmd, $start_time );
		}
	}

	public function create_db() {
		$dbname = self::$db_settings['dbname'];
		self::run_sql( 'mysql --no-defaults', array( 'execute' => "CREATE DATABASE IF NOT EXISTS $dbname" ) );
	}

	public function drop_db() {
		$dbname = self::$db_settings['dbname'];
		self::run_sql( 'mysql --no-defaults', array( 'execute' => "DROP DATABASE IF EXISTS $dbname" ) );
	}

	public function proc( $command, $assoc_args = array(), $path = '' ) {
		if ( !empty( $assoc_args ) )
			$command .= Utils\assoc_args_to_str( $assoc_args );

		$env = self::get_process_env_variables();
		if ( isset( $this->variables['SUITE_CACHE_DIR'] ) ) {
			$env['WP_CLI_CACHE_DIR'] = $this->variables['SUITE_CACHE_DIR'];
		}

		if ( isset( $this->variables['RUN_DIR'] ) ) {
			$cwd = "{$this->variables['RUN_DIR']}/{$path}";
		} else {
			$cwd = null;
		}

		return Process::create( $command, $cwd, $env );
	}

	/**
	 * Start a background process. Will automatically be closed when the tests finish.
	 */
	public function background_proc( $cmd ) {
		$descriptors = array(
			0 => STDIN,
			1 => array( 'pipe', 'w' ),
			2 => array( 'pipe', 'w' ),
		);

		$proc = proc_open( $cmd, $descriptors, $pipes, $this->variables['RUN_DIR'], self::get_process_env_variables() );

		sleep(1);

		$status = proc_get_status( $proc );

		if ( !$status['running'] ) {
			$stderr = is_resource( $pipes[2] ) ? ( ': ' . stream_get_contents( $pipes[2] ) ) : '';
			throw new RuntimeException( sprintf( "Failed to start background process '%s'%s.", $cmd, $stderr ) );
		} else {
			$this->running_procs[] = $proc;
		}
	}

	public function move_files( $src, $dest ) {
		rename( $this->variables['RUN_DIR'] . "/$src", $this->variables['RUN_DIR'] . "/$dest" );
	}

	/**
	 * Remove a directory (recursive).
	 */
	public static function remove_dir( $dir ) {
		Process::create( Utils\esc_cmd( 'rm -rf %s', $dir ) )->run_check();
	}

	/**
	 * Copy a directory (recursive). Destination directory must exist.
	 */
	public static function copy_dir( $src_dir, $dest_dir ) {
		Process::create( Utils\esc_cmd( "cp -r %s/* %s", $src_dir, $dest_dir ) )->run_check();
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

		self::copy_dir( self::$cache_dir, $dest_dir );

		// disable emailing
		mkdir( $dest_dir . '/wp-content/mu-plugins' );
		copy( __DIR__ . '/../extra/no-mail.php', $dest_dir . '/wp-content/mu-plugins/no-mail.php' );
	}

	public function create_config( $subdir = '', $extra_php = false ) {
		$params = self::$db_settings;

		// Replaces all characters that are not alphanumeric or an underscore into an underscore.
		$params['dbprefix'] = $subdir ? preg_replace( '#[^a-zA-Z\_0-9]#', '_', $subdir ) : 'wp_';

		$params['skip-salts'] = true;

		if( false !== $extra_php ) {
			$params['extra-php'] = $extra_php;
		}

		$config_cache_path = '';
		if ( self::$install_cache_dir ) {
			$config_cache_path = self::$install_cache_dir . '/config_' . md5( implode( ':', $params ) . ':subdir=' . $subdir );
			$run_dir = '' !== $subdir ? ( $this->variables['RUN_DIR'] . "/$subdir" ) : $this->variables['RUN_DIR'];
		}

		if ( $config_cache_path && file_exists( $config_cache_path ) ) {
			copy( $config_cache_path, $run_dir . '/wp-config.php' );
		} else {
			$this->proc( 'wp config create', $params, $subdir )->run_check();
			if ( $config_cache_path && file_exists( $run_dir . '/wp-config.php' ) ) {
				copy( $run_dir . '/wp-config.php', $config_cache_path );
			}
		}
	}

	public function install_wp( $subdir = '' ) {
		$wp_version_suffix = ( $wp_version = getenv( 'WP_VERSION' ) ) ? "-$wp_version" : '';
		self::$install_cache_dir = sys_get_temp_dir() . '/wp-cli-test-core-install-cache' . $wp_version_suffix;
		if ( ! file_exists( self::$install_cache_dir ) ) {
			mkdir( self::$install_cache_dir );
		}

		$subdir = $this->replace_variables( $subdir );

		$this->create_db();
		$this->create_run_dir();
		$this->download_wp( $subdir );
		$this->create_config( $subdir );

		$install_args = array(
			'url' => 'http://example.com',
			'title' => 'WP CLI Site',
			'admin_user' => 'admin',
			'admin_email' => 'admin@example.com',
			'admin_password' => 'password1',
			'skip-email' => true,
		);

		$install_cache_path = '';
		if ( self::$install_cache_dir ) {
			$install_cache_path = self::$install_cache_dir . '/install_' . md5( implode( ':', $install_args ) . ':subdir=' . $subdir );
			$run_dir = '' !== $subdir ? ( $this->variables['RUN_DIR'] . "/$subdir" ) : $this->variables['RUN_DIR'];
		}

		if ( $install_cache_path && file_exists( $install_cache_path ) ) {
			self::copy_dir( $install_cache_path, $run_dir );
			self::run_sql( 'mysql --no-defaults', array( 'execute' => "source {$install_cache_path}.sql" ), true /*add_database*/ );
		} else {
			$this->proc( 'wp core install', $install_args, $subdir )->run_check();
			if ( $install_cache_path ) {
				mkdir( $install_cache_path );
				self::dir_diff_copy( $run_dir, self::$cache_dir, $install_cache_path );
				self::run_sql( 'mysqldump --no-defaults', array( 'result-file' => "{$install_cache_path}.sql" ), true /*add_database*/ );
			}
		}
	}

	public function install_wp_with_composer( $vendor_directory = 'vendor' ) {
		$this->create_run_dir();
		$this->create_db();

		$yml_path = $this->variables['RUN_DIR'] . "/wp-cli.yml";
		file_put_contents( $yml_path, 'path: wordpress' );

		$this->composer_command( 'init --name="wp-cli/composer-test" --type="project" --no-interaction' );
		$this->composer_command( 'config vendor-dir ' . $vendor_directory );
		$this->composer_command( 'require johnpbloch/wordpress --optimize-autoloader --no-interaction' );

		$config_extra_php = "require_once dirname(__DIR__) . '/" . $vendor_directory . "/autoload.php';";
		$this->create_config( 'wordpress', $config_extra_php );

		$install_args = array(
			'url' => 'http://localhost:8080',
			'title' => 'WP CLI Site with both WordPress and wp-cli as Composer dependencies',
			'admin_user' => 'admin',
			'admin_email' => 'admin@example.com',
			'admin_password' => 'password1',
			'skip-email' => true,
		);

		$this->proc( 'wp core install', $install_args )->run_check();
	}

	public function composer_add_wp_cli_local_repository() {
		if ( ! self::$composer_local_repository ) {
			self::$composer_local_repository = sys_get_temp_dir() . '/' . uniqid( "wp-cli-composer-local-", TRUE );
			mkdir( self::$composer_local_repository );

			$env = self::get_process_env_variables();
			$src = isset( $env['TRAVIS_BUILD_DIR'] ) ? $env['TRAVIS_BUILD_DIR'] : realpath( __DIR__ . '/../../' );

			self::copy_dir( $src, self::$composer_local_repository . '/' );
			self::remove_dir( self::$composer_local_repository . '/.git' );
			self::remove_dir( self::$composer_local_repository . '/vendor' );
		}
		$dest = self::$composer_local_repository . '/';
		$this->composer_command( "config repositories.wp-cli '{\"type\": \"path\", \"url\": \"$dest\", \"options\": {\"symlink\": false}}'" );
		$this->variables['COMPOSER_LOCAL_REPOSITORY'] = self::$composer_local_repository;
	}

	public function composer_require_current_wp_cli() {
		$this->composer_add_wp_cli_local_repository();
		$this->composer_command( 'require wp-cli/wp-cli:dev-master --optimize-autoloader --no-interaction' );
	}

	public function start_php_server( $subdir = '' ) {
		$dir = $this->variables['RUN_DIR'] . '/';
		if ( $subdir ) {
			$dir .= trim( $subdir, '/' ) . '/';
		}
		$cmd = Utils\esc_cmd( '%s -S %s -t %s -c %s %s',
			Utils\get_php_binary(),
			'localhost:8080',
			$dir,
			get_cfg_var( 'cfg_file_path' ),
			$this->variables['RUN_DIR'] . '/vendor/wp-cli/server-command/router.php'
		);
		$this->background_proc( $cmd );
	}

	private function composer_command($cmd) {
		if ( !isset( $this->variables['COMPOSER_PATH'] ) ) {
			$this->variables['COMPOSER_PATH'] = exec('which composer');
		}
		$this->proc( $this->variables['COMPOSER_PATH'] . ' ' . $cmd )->run_check();
	}

	/**
	 * Initialize run time logging.
	 */
	private static function log_run_times_before_suite( $event ) {
		self::$suite_start_time = microtime( true );

		Process::$log_run_times = true;

		$travis = getenv( 'TRAVIS' );

		// Default output settings.
		self::$output_to = 'stdout';
		self::$num_top_processes = $travis ? 10 : 40;
		self::$num_top_scenarios = $travis ? 10 : 20;

		// Allow setting of above with "WP_CLI_TEST_LOG_RUN_TIMES=<output_to>[,<num_top_processes>][,<num_top_scenarios>]" formatted env var.
		if ( preg_match( '/^(stdout|error_log)?(,[0-9]+)?(,[0-9]+)?$/i', self::$log_run_times, $matches ) ) {
			if ( isset( $matches[1] ) ) {
				self::$output_to = strtolower( $matches[1] );
			}
			if ( isset( $matches[2] ) ) {
				self::$num_top_processes = max( (int) substr( $matches[2], 1 ), 1 );
			}
			if ( isset( $matches[3] ) ) {
				self::$num_top_scenarios = max( (int) substr( $matches[3], 1 ), 1 );
			}
		}
	}

	/**
	 * Record the start time of the scenario into the `$scenario_run_times` array.
	 */
	private static function log_run_times_before_scenario( $event ) {
		if ( $scenario_key = self::get_scenario_key( $event ) ) {
			self::$scenario_run_times[ $scenario_key ] = -microtime( true );
		}
	}

	/**
	 * Save the run time of the scenario into the `$scenario_run_times` array. Only the top `self::$num_top_scenarios` are kept.
	 */
	private static function log_run_times_after_scenario( $event ) {
		if ( $scenario_key = self::get_scenario_key( $event ) ) {
			self::$scenario_run_times[ $scenario_key ] += microtime( true );
			self::$scenario_count++;
			if ( count( self::$scenario_run_times ) > self::$num_top_scenarios ) {
				arsort( self::$scenario_run_times );
				array_pop( self::$scenario_run_times );
			}
		}
	}

	/**
	 * Copy files in updated directory that are not in source directory to copy directory. ("Incremental backup".)
	 * Note: does not deal with changed files (ie does not compare file contents for changes), for speed reasons.
	 *
	 * @param string $upd_dir The directory to search looking for files/directories not in `$src_dir`.
	 * @param string $src_dir The directory to be compared to `$upd_dir`.
	 * @param string $cop_dir Where to copy any files/directories in `$upd_dir` but not in `$src_dir` to.
	 */
	private static function dir_diff_copy( $upd_dir, $src_dir, $cop_dir ) {
		if ( false === ( $files = scandir( $upd_dir ) ) ) {
			$error = error_get_last();
			throw new \RuntimeException( sprintf( "Failed to open updated directory '%s': %s. " . __FILE__ . ':' . __LINE__, $upd_dir, $error['message'] ) );
		}
		foreach ( array_diff( $files, array( '.', '..' ) ) as $file ) {
			$upd_file = $upd_dir . '/' . $file;
			$src_file = $src_dir . '/' . $file;
			$cop_file = $cop_dir . '/' . $file;
			if ( ! file_exists( $src_file ) ) {
				if ( is_dir( $upd_file ) ) {
					if ( ! file_exists( $cop_file ) && ! mkdir( $cop_file, 0777, true /*recursive*/ ) ) {
						$error = error_get_last();
						throw new \RuntimeException( sprintf( "Failed to create copy directory '%s': %s. " . __FILE__ . ':' . __LINE__, $cop_file, $error['message'] ) );
					}
					self::copy_dir( $upd_file, $cop_file );
				} else {
					if ( ! copy( $upd_file, $cop_file ) ) {
						$error = error_get_last();
						throw new \RuntimeException( sprintf( "Failed to copy '%s' to '%s': %s. " . __FILE__ . ':' . __LINE__, $upd_file, $cop_file, $error['message'] ) );
					}
				}
			} elseif ( is_dir( $upd_file ) ) {
				self::dir_diff_copy( $upd_file, $src_file, $cop_file );
			}
		}
	}

	/**
	 * Get the scenario key used for `$scenario_run_times` array.
	 * Format "<grandparent-dir> <feature-file>:<line-number>", eg "core-command core-update.feature:221".
	 */
	private static function get_scenario_key( $event ) {
		$scenario_key = '';
		if ( $file = self::get_event_file( $event, $line ) ) {
			$scenario_grandparent = Utils\basename( dirname( dirname( $file ) ) );
			$scenario_key = $scenario_grandparent . ' ' . Utils\basename( $file ) . ':' . $line;
		}
		return $scenario_key;
	}

	/**
	 * Print out stats on the run times of processes and scenarios.
	 */
	private static function log_run_times_after_suite( $event ) {

		$suite = '';
		if ( self::$scenario_run_times ) {
			// Grandparent directory is first part of key.
			$keys = array_keys( self::$scenario_run_times );
			$suite = substr( $keys[0], 0, strpos( $keys[0], ' ' ) );
		}

		$run_from = Utils\basename( dirname( dirname( __DIR__ ) ) );

		// Format same as Behat, if have minutes.
		$fmt = function ( $time ) {
			$mins = floor( $time / 60 );
			return round( $time, 3 ) . ( $mins ? ( ' (' . $mins . 'm' . round( $time - ( $mins * 60 ), 3 ) . 's)' ) : '' );
		};

		$time = microtime( true ) - self::$suite_start_time;

		$log = PHP_EOL . str_repeat( '(', 80 ) . PHP_EOL;

		// Process and proc method run times.
		$run_times = array_merge( Process::$run_times, self::$proc_method_run_times );

		list( $ptime, $calls ) = array_reduce( $run_times, function ( $carry, $item ) {
			return array( $carry[0] + $item[0], $carry[1] + $item[1] );
		}, array( 0, 0 ) );

		$overhead = $time - $ptime;
		$pct = round( ( $overhead / $time ) * 100 );
		$unique = count( $run_times );

		$log .= sprintf(
			PHP_EOL . "Total process run time %s (tests %s, overhead %.3f %d%%), calls %d (%d unique) for '%s' run from '%s'" . PHP_EOL,
			$fmt( $ptime ), $fmt( $time ), $overhead, $pct, $calls, $unique, $suite, $run_from
		);

		uasort( $run_times, function ( $a, $b ) {
			return $a[0] === $b[0] ? 0 : ( $a[0] < $b[0] ? 1 : -1 ); // Reverse sort.
		} );

		$tops = array_slice( $run_times, 0, self::$num_top_processes, true );

		$log .= PHP_EOL . "Top " . self::$num_top_processes . " process run times for '$suite'";
		$log .= PHP_EOL . implode( PHP_EOL, array_map( function ( $k, $v, $i ) {
			return sprintf( ' %3d. %7.3f %3d %s', $i + 1, round( $v[0], 3 ), $v[1], $k );
		}, array_keys( $tops ), $tops, array_keys( array_keys( $tops ) ) ) ) . PHP_EOL;

		// Scenario run times.
		arsort( self::$scenario_run_times );

		$tops = array_slice( self::$scenario_run_times, 0, self::$num_top_scenarios, true );

		$log .= PHP_EOL . "Top " . self::$num_top_scenarios . " (of " . self::$scenario_count . ") scenario run times for '$suite'";
		$log .= PHP_EOL . implode( PHP_EOL, array_map( function ( $k, $v, $i ) {
			return sprintf( ' %3d. %7.3f %s', $i + 1, round( $v, 3 ), substr( $k, strpos( $k, ' ' ) + 1 ) );
		}, array_keys( $tops ), $tops, array_keys( array_keys( $tops ) ) ) ) . PHP_EOL;

		$log .= PHP_EOL . str_repeat( ')', 80 );

		if ( 'error_log' === self::$output_to ) {
			error_log( $log );
		} else {
			echo PHP_EOL . $log;
		}
	}

	/**
	 * Log the run time of a proc method (one that doesn't use Process but does (use a function that does) a `proc_open()`).
	 */
	private static function log_proc_method_run_time( $key, $start_time ) {
		$run_time = microtime( true ) - $start_time;
		if ( ! isset( self::$proc_method_run_times[ $key ] ) ) {
			self::$proc_method_run_times[ $key ] = array( 0, 0 );
		}
		self::$proc_method_run_times[ $key ][0] += $run_time;
		self::$proc_method_run_times[ $key ][1]++;
	}

}
