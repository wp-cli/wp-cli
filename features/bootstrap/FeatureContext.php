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

	private static $cache_dir, $suite_cache_dir;

	private static $db_settings = array(
		'dbname' => 'wp_cli_test',
		'dbuser' => 'wp_cli_test',
		'dbpass' => 'password1',
		'dbhost' => '127.0.0.1',
	);

	private $running_procs = array();

	public $variables = array();

	/**
	 * Get the environment variables required for launched `wp` processes
	 * @beforeSuite
	 */
	private static function get_process_env_variables() {
		// Ensure we're using the expected `wp` binary
		$bin_dir = getenv( 'WP_CLI_BIN_DIR' ) ?: realpath( __DIR__ . '/../../bin' );
		$vendor_dir = realpath( __DIR__ . '/../../vendor/bin' );
		$env = array(
			'PATH' =>  $bin_dir . ':' . $vendor_dir . ':' . getenv( 'PATH' ),
			'BEHAT_RUN' => 1,
			'HOME' => '/tmp/wp-cli-home',
		);
		if ( $config_path = getenv( 'WP_CLI_CONFIG_PATH' ) ) {
			$env['WP_CLI_CONFIG_PATH'] = $config_path;
		}
		if ( $term = getenv( 'TERM' ) ) {
			$env['TERM'] = $term;
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
		if ( getenv( 'WP_VERSION' ) ) {
			$cmd .= Utils\esc_cmd( ' --version=%s', getenv( 'WP_VERSION' ) );
		}
		Process::create( $cmd, null, self::get_process_env_variables() )->run_check();
	}

	/**
	 * @BeforeSuite
	 */
	public static function prepare( SuiteEvent $event ) {
		$result = Process::create( 'wp cli info', null, self::get_process_env_variables() )->run_check();
		echo PHP_EOL;
		echo $result->stdout;
		echo PHP_EOL;
		self::cache_wp_files();
		$result = Process::create( Utils\esc_cmd( 'wp core version --path=%s', self::$cache_dir ) , null, self::get_process_env_variables() )->run_check();
		echo 'WordPress ' . $result->stdout;
		echo PHP_EOL;
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
		if ( isset( $this->variables['RUN_DIR'] ) ) {
			// remove altered WP install, unless there's an error
			if ( $event->getResult() < 4 && 0 === strpos( $this->variables['RUN_DIR'], sys_get_temp_dir() ) ) {
				$this->proc( Utils\esc_cmd( 'rm -rf %s', $this->variables['RUN_DIR'] ) )->run();
			}
		}

		// Remove WP-CLI package directory
		if ( isset( $this->variables['PACKAGE_PATH'] ) ) {
			$this->proc( Utils\esc_cmd( 'rm -rf %s', $this->variables['PACKAGE_PATH'] ) )->run();
		}

		foreach ( $this->running_procs as $proc ) {
			$status = proc_get_status( $proc );
			self::terminate_proc( $status['pid'] );
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

	public function replace_variables( $str ) {
		$ret = preg_replace_callback( '/\{([A-Z_]+)\}/', array( $this, '_replace_var' ), $str );
		if ( false !== strpos( $str, '{WP_VERSION-' ) ) {
			$ret = $this->_replace_wp_versions( $ret );
		}
		return $ret;
	}

	private function _replace_var( $matches ) {
		$cmd = $matches[0];

		foreach ( array_slice( $matches, 1 ) as $key ) {
			$cmd = str_replace( '{' . $key . '}', $this->variables[ $key ], $cmd );
		}

		return $cmd;
	}

	// Substitute "{WP_VERSION-version-latest}" variables.
	private function _replace_wp_versions( $str ) {
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

	public function create_run_dir() {
		if ( !isset( $this->variables['RUN_DIR'] ) ) {
			$this->variables['RUN_DIR'] = sys_get_temp_dir() . '/' . uniqid( "wp-cli-test-run-", TRUE );
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

		Process::create( \WP_CLI\Utils\esc_cmd(
			'curl -sSL %s > %s',
			$download_url,
			$this->variables['PHAR_PATH']
		) )->run_check();

		Process::create( \WP_CLI\Utils\esc_cmd(
			'chmod +x %s',
			$this->variables['PHAR_PATH']
		) )->run_check();
	}

	private function set_cache_dir() {
		$path = sys_get_temp_dir() . '/wp-cli-test-cache';
		$this->proc( Utils\esc_cmd( 'mkdir -p %s', $path ) )->run_check();
		$this->variables['CACHE_DIR'] = $path;
	}

	private static function run_sql( $sql ) {
		Utils\run_mysql_command( '/usr/bin/env mysql --no-defaults', array(
			'execute' => $sql,
			'host' => self::$db_settings['dbhost'],
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
			throw new RuntimeException( stream_get_contents( $pipes[2] ) );
		} else {
			$this->running_procs[] = $proc;
		}
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

		$this->proc( Utils\esc_cmd( "cp -r %s/* %s", self::$cache_dir, $dest_dir ) )->run_check();

		// disable emailing
		mkdir( $dest_dir . '/wp-content/mu-plugins' );
		copy( __DIR__ . '/../extra/no-mail.php', $dest_dir . '/wp-content/mu-plugins/no-mail.php' );
	}

	public function create_config( $subdir = '' ) {
		$params = self::$db_settings;
		// Replaces all characters that are not alphanumeric or an underscore into an underscore.
		$params['dbprefix'] = $subdir ? preg_replace( '#[^a-zA-Z\_0-9]#', '_', $subdir ) : 'wp_';

		$params['skip-salts'] = true;
		$this->proc( 'wp core config', $params, $subdir )->run_check();
	}

	public function install_wp( $subdir = '' ) {
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
			'admin_password' => 'password1'
		);

		$this->proc( 'wp core install', $install_args, $subdir )->run_check();
	}
}

