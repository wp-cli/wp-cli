<?php

use WP_CLI\ExitException;
use WP_CLI\Loggers;
use WP_CLI\Tests\TestCase;
use WP_CLI\Utils;

require_once dirname( __DIR__ ) . '/php/class-wp-cli.php';
require_once __DIR__ . '/mock-requests-transport.php';

class UtilsTest extends TestCase {

	public function testIncrementVersion() {
		// keyword increments
		$this->assertEquals(
			Utils\increment_version( '1.2.3-pre', 'same' ),
			'1.2.3-pre'
		);

		$this->assertEquals(
			Utils\increment_version( '1.2.3-pre', 'patch' ),
			'1.2.4'
		);

		$this->assertEquals(
			Utils\increment_version( '1.2.3-pre', 'minor' ),
			'1.3.0'
		);

		$this->assertEquals(
			Utils\increment_version( '1.2.3-pre', 'major' ),
			'2.0.0'
		);

		// custom version string
		$this->assertEquals(
			Utils\increment_version( '1.2.3-pre', '4.5.6-alpha1' ),
			'4.5.6-alpha1'
		);
	}

	public function testGetSemVer() {
		$original_version = '0.19.1';
		$this->assertEmpty( Utils\get_named_sem_ver( '0.18.0', $original_version ) );
		$this->assertEmpty( Utils\get_named_sem_ver( '0.19.1', $original_version ) );
		$this->assertEmpty( Utils\get_named_sem_ver( 'nonsense', $original_version ) );
		$this->assertEmpty( Utils\get_named_sem_ver( '0.18.1-beta3', $original_version ) );
		$this->assertEmpty( Utils\get_named_sem_ver( '0.19.1-dev1', $original_version ) );
		$this->assertEmpty( Utils\get_named_sem_ver( '0.19.1-beta3', $original_version ) );
		$this->assertEmpty( Utils\get_named_sem_ver( '0.19.2-dev1', $original_version ) ); // -dev suffix not accepted by SemVer.
		$this->assertEquals( 'patch', Utils\get_named_sem_ver( '0.19.2-beta3', $original_version ) );
		$this->assertEquals( 'patch', Utils\get_named_sem_ver( '0.19.2', $original_version ) );
		$this->assertEquals( 'minor', Utils\get_named_sem_ver( '0.20.0', $original_version ) );
		$this->assertEquals( 'minor', Utils\get_named_sem_ver( '0.20.3', $original_version ) );
		$this->assertEquals( 'major', Utils\get_named_sem_ver( '1.0.0', $original_version ) );
		$this->assertEquals( 'major', Utils\get_named_sem_ver( '1.1.1', $original_version ) );
	}

	public function testGetSemVerWP() {
		$original_version = '3.0';
		$this->assertEmpty( Utils\get_named_sem_ver( '2.8', $original_version ) );
		$this->assertEmpty( Utils\get_named_sem_ver( '2.9.1', $original_version ) );
		$this->assertEmpty( Utils\get_named_sem_ver( 'nonsense', $original_version ) );
		$this->assertEmpty( Utils\get_named_sem_ver( '2.0-beta3', $original_version ) );
		$this->assertEmpty( Utils\get_named_sem_ver( '3.0-dev1', $original_version ) );
		$this->assertEmpty( Utils\get_named_sem_ver( '3.0-beta3', $original_version ) );
		$this->assertEmpty( Utils\get_named_sem_ver( '3.0.1-dev1', $original_version ) ); // -dev suffix not accepted by SemVer.
		$this->assertEquals( 'patch', Utils\get_named_sem_ver( '3.0.1-beta3', $original_version ) );
		$this->assertEquals( 'patch', Utils\get_named_sem_ver( '3.0.1', $original_version ) );
		$this->assertEquals( 'minor', Utils\get_named_sem_ver( '3.1-beta3', $original_version ) );
		$this->assertEquals( 'minor', Utils\get_named_sem_ver( '3.1', $original_version ) );
		$this->assertEquals( 'minor', Utils\get_named_sem_ver( '3.1.1', $original_version ) );
		$this->assertEquals( 'major', Utils\get_named_sem_ver( '4.0', $original_version ) );
		$this->assertEquals( 'major', Utils\get_named_sem_ver( '4.1.1', $original_version ) );
	}

	public function testParseSSHUrl() {
		$testcase = 'foo';
		$this->assertEquals( [ 'host' => 'foo' ], Utils\parse_ssh_url( $testcase ) );
		$this->assertEquals( null, Utils\parse_ssh_url( $testcase, PHP_URL_SCHEME ) );
		$this->assertEquals( null, Utils\parse_ssh_url( $testcase, PHP_URL_USER ) );
		$this->assertEquals( 'foo', Utils\parse_ssh_url( $testcase, PHP_URL_HOST ) );
		$this->assertEquals( null, Utils\parse_ssh_url( $testcase, PHP_URL_PORT ) );
		$this->assertEquals( null, Utils\parse_ssh_url( $testcase, PHP_URL_PATH ) );

		$testcase = 'foo.com';
		$this->assertEquals( [ 'host' => 'foo.com' ], Utils\parse_ssh_url( $testcase ) );
		$this->assertEquals( null, Utils\parse_ssh_url( $testcase, PHP_URL_SCHEME ) );
		$this->assertEquals( null, Utils\parse_ssh_url( $testcase, PHP_URL_USER ) );
		$this->assertEquals( 'foo.com', Utils\parse_ssh_url( $testcase, PHP_URL_HOST ) );
		$this->assertEquals( null, Utils\parse_ssh_url( $testcase, PHP_URL_PORT ) );
		$this->assertEquals( null, Utils\parse_ssh_url( $testcase, PHP_URL_PATH ) );

		$testcase = 'foo.com:2222';
		$expected = [
			'host' => 'foo.com',
			'port' => 2222,
		];
		$this->assertEquals( $expected, Utils\parse_ssh_url( $testcase ) );
		$this->assertEquals( null, Utils\parse_ssh_url( $testcase, PHP_URL_SCHEME ) );
		$this->assertEquals( null, Utils\parse_ssh_url( $testcase, PHP_URL_USER ) );
		$this->assertEquals( 'foo.com', Utils\parse_ssh_url( $testcase, PHP_URL_HOST ) );
		$this->assertEquals( 2222, Utils\parse_ssh_url( $testcase, PHP_URL_PORT ) );
		$this->assertEquals( null, Utils\parse_ssh_url( $testcase, PHP_URL_PATH ) );

		$testcase = 'foo.com:2222/path/to/dir';
		$expected = [
			'host' => 'foo.com',
			'port' => 2222,
			'path' => '/path/to/dir',
		];
		$this->assertEquals( $expected, Utils\parse_ssh_url( $testcase ) );
		$this->assertEquals( null, Utils\parse_ssh_url( $testcase, PHP_URL_SCHEME ) );
		$this->assertEquals( null, Utils\parse_ssh_url( $testcase, PHP_URL_USER ) );
		$this->assertEquals( 'foo.com', Utils\parse_ssh_url( $testcase, PHP_URL_HOST ) );
		$this->assertEquals( 2222, Utils\parse_ssh_url( $testcase, PHP_URL_PORT ) );
		$this->assertEquals( '/path/to/dir', Utils\parse_ssh_url( $testcase, PHP_URL_PATH ) );

		$testcase = 'foo.com~/path/to/dir';
		$expected = [
			'host' => 'foo.com',
			'path' => '~/path/to/dir',
		];
		$this->assertEquals( $expected, Utils\parse_ssh_url( $testcase ) );
		$this->assertEquals( null, Utils\parse_ssh_url( $testcase, PHP_URL_SCHEME ) );
		$this->assertEquals( null, Utils\parse_ssh_url( $testcase, PHP_URL_USER ) );
		$this->assertEquals( 'foo.com', Utils\parse_ssh_url( $testcase, PHP_URL_HOST ) );
		$this->assertEquals( null, Utils\parse_ssh_url( $testcase, PHP_URL_PORT ) );
		$this->assertEquals( '~/path/to/dir', Utils\parse_ssh_url( $testcase, PHP_URL_PATH ) );

		// No host
		$testcase = '~/path/to/dir';
		$this->assertEquals( [], Utils\parse_ssh_url( $testcase ) );
		$this->assertEquals( null, Utils\parse_ssh_url( $testcase, PHP_URL_SCHEME ) );
		$this->assertEquals( null, Utils\parse_ssh_url( $testcase, PHP_URL_USER ) );
		$this->assertEquals( null, Utils\parse_ssh_url( $testcase, PHP_URL_HOST ) );
		$this->assertEquals( null, Utils\parse_ssh_url( $testcase, PHP_URL_PORT ) );
		$this->assertEquals( null, Utils\parse_ssh_url( $testcase, PHP_URL_PATH ) );

		// host and path, no port, with scp notation
		$testcase = 'foo.com:~/path/to/dir';
		$expected = [
			'host' => 'foo.com',
			'path' => '~/path/to/dir',
		];
		$this->assertEquals( $expected, Utils\parse_ssh_url( $testcase ) );
		$this->assertEquals( null, Utils\parse_ssh_url( $testcase, PHP_URL_SCHEME ) );
		$this->assertEquals( null, Utils\parse_ssh_url( $testcase, PHP_URL_USER ) );
		$this->assertEquals( 'foo.com', Utils\parse_ssh_url( $testcase, PHP_URL_HOST ) );
		$this->assertEquals( null, Utils\parse_ssh_url( $testcase, PHP_URL_PORT ) );
		$this->assertEquals( '~/path/to/dir', Utils\parse_ssh_url( $testcase, PHP_URL_PATH ) );

		$testcase = 'foo.com:2222~/path/to/dir';
		$expected = [
			'host' => 'foo.com',
			'path' => '~/path/to/dir',
			'port' => '2222',
		];
		$this->assertEquals( $expected, Utils\parse_ssh_url( $testcase ) );
		$this->assertEquals( null, Utils\parse_ssh_url( $testcase, PHP_URL_SCHEME ) );
		$this->assertEquals( null, Utils\parse_ssh_url( $testcase, PHP_URL_USER ) );
		$this->assertEquals( 'foo.com', Utils\parse_ssh_url( $testcase, PHP_URL_HOST ) );
		$this->assertEquals( '2222', Utils\parse_ssh_url( $testcase, PHP_URL_PORT ) );
		$this->assertEquals( '~/path/to/dir', Utils\parse_ssh_url( $testcase, PHP_URL_PATH ) );

		// explicit scheme, user, host, path, no port
		$testcase = 'ssh:bar@foo.com:~/path/to/dir';
		$expected = [
			'scheme' => 'ssh',
			'user'   => 'bar',
			'host'   => 'foo.com',
			'path'   => '~/path/to/dir',
		];
		$this->assertEquals( $expected, Utils\parse_ssh_url( $testcase ) );
		$this->assertEquals( 'ssh', Utils\parse_ssh_url( $testcase, PHP_URL_SCHEME ) );
		$this->assertEquals( 'bar', Utils\parse_ssh_url( $testcase, PHP_URL_USER ) );
		$this->assertEquals( 'foo.com', Utils\parse_ssh_url( $testcase, PHP_URL_HOST ) );
		$this->assertEquals( null, Utils\parse_ssh_url( $testcase, PHP_URL_PORT ) );
		$this->assertEquals( '~/path/to/dir', Utils\parse_ssh_url( $testcase, PHP_URL_PATH ) );

		// container scheme
		$testcase = 'docker:wordpress';
		$expected = [
			'scheme' => 'docker',
			'host'   => 'wordpress',
		];
		$this->assertEquals( $expected, Utils\parse_ssh_url( $testcase ) );
		$this->assertEquals( 'docker', Utils\parse_ssh_url( $testcase, PHP_URL_SCHEME ) );
		$this->assertEquals( null, Utils\parse_ssh_url( $testcase, PHP_URL_USER ) );
		$this->assertEquals( 'wordpress', Utils\parse_ssh_url( $testcase, PHP_URL_HOST ) );
		$this->assertEquals( null, Utils\parse_ssh_url( $testcase, PHP_URL_PORT ) );
		$this->assertEquals( null, Utils\parse_ssh_url( $testcase, PHP_URL_PATH ) );

		// container scheme with user, and host
		$testcase = 'docker:bar@wordpress';
		$expected = [
			'scheme' => 'docker',
			'user'   => 'bar',
			'host'   => 'wordpress',
		];
		$this->assertEquals( $expected, Utils\parse_ssh_url( $testcase ) );
		$this->assertEquals( 'docker', Utils\parse_ssh_url( $testcase, PHP_URL_SCHEME ) );
		$this->assertEquals( 'bar', Utils\parse_ssh_url( $testcase, PHP_URL_USER ) );
		$this->assertEquals( 'wordpress', Utils\parse_ssh_url( $testcase, PHP_URL_HOST ) );
		$this->assertEquals( null, Utils\parse_ssh_url( $testcase, PHP_URL_PORT ) );
		$this->assertEquals( null, Utils\parse_ssh_url( $testcase, PHP_URL_PATH ) );

		// container scheme with user, host, and path
		$testcase = 'docker-compose:bar@wordpress:~/path/to/dir';
		$expected = [
			'scheme' => 'docker-compose',
			'user'   => 'bar',
			'host'   => 'wordpress',
			'path'   => '~/path/to/dir',
		];
		$this->assertEquals( $expected, Utils\parse_ssh_url( $testcase ) );
		$this->assertEquals( 'docker-compose', Utils\parse_ssh_url( $testcase, PHP_URL_SCHEME ) );
		$this->assertEquals( 'bar', Utils\parse_ssh_url( $testcase, PHP_URL_USER ) );
		$this->assertEquals( 'wordpress', Utils\parse_ssh_url( $testcase, PHP_URL_HOST ) );
		$this->assertEquals( null, Utils\parse_ssh_url( $testcase, PHP_URL_PORT ) );
		$this->assertEquals( '~/path/to/dir', Utils\parse_ssh_url( $testcase, PHP_URL_PATH ) );

		// container scheme with user, host, and path
		$testcase = 'docker-compose-run:bar@wordpress:~/path/to/dir';
		$expected = [
			'scheme' => 'docker-compose-run',
			'user'   => 'bar',
			'host'   => 'wordpress',
			'path'   => '~/path/to/dir',
		];
		$this->assertEquals( $expected, Utils\parse_ssh_url( $testcase ) );
		$this->assertEquals( 'docker-compose-run', Utils\parse_ssh_url( $testcase, PHP_URL_SCHEME ) );
		$this->assertEquals( 'bar', Utils\parse_ssh_url( $testcase, PHP_URL_USER ) );
		$this->assertEquals( 'wordpress', Utils\parse_ssh_url( $testcase, PHP_URL_HOST ) );
		$this->assertEquals( null, Utils\parse_ssh_url( $testcase, PHP_URL_PORT ) );
		$this->assertEquals( '~/path/to/dir', Utils\parse_ssh_url( $testcase, PHP_URL_PATH ) );

		// vagrant scheme
		$testcase = 'vagrant:default';
		$expected = [
			'scheme' => 'vagrant',
			'host'   => 'default',
		];
		$this->assertEquals( $expected, Utils\parse_ssh_url( $testcase ) );
		$this->assertEquals( 'vagrant', Utils\parse_ssh_url( $testcase, PHP_URL_SCHEME ) );
		$this->assertEquals( null, Utils\parse_ssh_url( $testcase, PHP_URL_USER ) );
		$this->assertEquals( 'default', Utils\parse_ssh_url( $testcase, PHP_URL_HOST ) );
		$this->assertEquals( null, Utils\parse_ssh_url( $testcase, PHP_URL_PORT ) );
		$this->assertEquals( null, Utils\parse_ssh_url( $testcase, PHP_URL_PATH ) );

		// vagrant scheme
		$testcase = 'vagrant:/var/www/html';
		$expected = [
			'scheme' => 'vagrant',
			'host'   => '',
			'path'   => '/var/www/html',
		];
		$this->assertEquals( $expected, Utils\parse_ssh_url( $testcase ) );
		$this->assertEquals( 'vagrant', Utils\parse_ssh_url( $testcase, PHP_URL_SCHEME ) );
		$this->assertEquals( null, Utils\parse_ssh_url( $testcase, PHP_URL_USER ) );
		$this->assertEquals( '', Utils\parse_ssh_url( $testcase, PHP_URL_HOST ) );
		$this->assertEquals( null, Utils\parse_ssh_url( $testcase, PHP_URL_PORT ) );
		$this->assertEquals( '/var/www/html', Utils\parse_ssh_url( $testcase, PHP_URL_PATH ) );

		// unsupported scheme, should not match
		$testcase = 'foo:bar';
		$this->assertEquals( [], Utils\parse_ssh_url( $testcase ) );
		$this->assertEquals( null, Utils\parse_ssh_url( $testcase, PHP_URL_SCHEME ) );
		$this->assertEquals( null, Utils\parse_ssh_url( $testcase, PHP_URL_USER ) );
		$this->assertEquals( null, Utils\parse_ssh_url( $testcase, PHP_URL_HOST ) );
		$this->assertEquals( null, Utils\parse_ssh_url( $testcase, PHP_URL_PORT ) );
		$this->assertEquals( null, Utils\parse_ssh_url( $testcase, PHP_URL_PATH ) );
	}

	public function parseStrToArgvData() {
		return [
			[ [], '' ],
			[ [ 'option', 'get', 'home' ], 'option get home' ],
			[ [ 'core', 'download', '--path=/var/www/' ], 'core download --path=/var/www/' ],
			[ [ 'eval', 'echo wp_get_current_user()->user_login;' ], 'eval "echo wp_get_current_user()->user_login;"' ],
			[ [ 'post', 'create', '--post_title="Hello world!"' ], 'post create --post_title="Hello world!"' ],
			[ [ 'post', 'create', '--post_title=\'Mixed "quotes are working" hopefully\'' ], 'post create --post_title=\'Mixed "quotes are working" hopefully\'' ],
			[ [ 'post', 'create', '--post_title="Escaped \"double \"quotes!"' ], 'post create --post_title="Escaped \"double \"quotes!"' ],
			[ [ 'post', 'create', "--post_title='Escaped \'single \'quotes!'" ], "post create --post_title='Escaped \'single \'quotes!'" ],
			[ [ 'search-replace', '//old-domain.com', '//new-domain.com', 'specifictable', '--all-tables' ], 'search-replace "//old-domain.com" "//new-domain.com" "specifictable" --all-tables' ],
			[ [ 'i18n', 'make-pot', '/home/wporgdev/co/wordpress/trunk', '/home/wporgdev/co/wp-pot/trunk/wordpress-continents-cities.pot', '--include="wp-admin/includes/continents-cities.php"', "--package-name='WordPress'", '--headers=\'{"Report-Msgid-Bugs-To":"https://core.trac.wordpress.org/"}\'', "--file-comment='Copyright (C) 2019 by the contributors\nThis file is distributed under the same license as the WordPress package.'", '--skip-js', '--skip-audit', '--ignore-domain' ], "i18n make-pot '/home/wporgdev/co/wordpress/trunk' '/home/wporgdev/co/wp-pot/trunk/wordpress-continents-cities.pot' --include=\"wp-admin/includes/continents-cities.php\" --package-name='WordPress' --headers='{\"Report-Msgid-Bugs-To\":\"https://core.trac.wordpress.org/\"}' --file-comment='Copyright (C) 2019 by the contributors\nThis file is distributed under the same license as the WordPress package.' --skip-js --skip-audit --ignore-domain" ],
		];
	}

	/**
	 * @dataProvider parseStrToArgvData
	 */
	public function testParseStrToArgv( $expected, $parseable_string ) {
		$this->assertEquals( $expected, Utils\parse_str_to_argv( $parseable_string ) );
	}

	public function testAssocArgsToString() {
		// Strip quotes for Windows compat.
		$strip_quotes = function ( $str ) {
			return str_replace( [ '"', "'" ], '', $str );
		};

		$expected = " --url='foo.dev' --porcelain --apple='banana'";
		$input    = [
			'url'       => 'foo.dev',
			'porcelain' => true,
			'apple'     => 'banana',
		];
		$actual   = Utils\assoc_args_to_str( $input );
		$this->assertSame( $strip_quotes( $expected ), $strip_quotes( $actual ) );

		$expected = " --url='foo.dev' --require='file-a.php' --require='file-b.php' --porcelain --apple='banana'";
		$input    = [
			'url'       => 'foo.dev',
			'require'   => [
				'file-a.php',
				'file-b.php',
			],
			'porcelain' => true,
			'apple'     => 'banana',
		];
		$actual   = Utils\assoc_args_to_str( $input );
		$this->assertSame( $strip_quotes( $expected ), $strip_quotes( $actual ) );
	}

	public function testMysqlHostToCLIArgs() {
		// Test hostname only, with and without 'p:' modifier
		$expected = [
			'host' => 'hostname',
		];
		$testcase = 'hostname';
		$this->assertEquals( $expected, Utils\mysql_host_to_cli_args( $testcase ) );

		$testcase = 'p:hostname';
		$this->assertEquals( $expected, Utils\mysql_host_to_cli_args( $testcase ) );

		// Test hostname with port number, with and without 'p:' modifier
		$expected = [
			'host'     => 'hostname',
			'port'     => 3306,
			'protocol' => 'tcp',
		];
		$testcase = 'hostname:3306';
		$this->assertEquals( $expected, Utils\mysql_host_to_cli_args( $testcase ) );

		$testcase = 'p:hostname:3306';
		$this->assertEquals( $expected, Utils\mysql_host_to_cli_args( $testcase ) );

		// Test hostname with socket path, with and without 'p:' modifier
		$expected = [
			'host'   => 'hostname',
			'socket' => '/path/to/socket',
		];
		$testcase = 'hostname:/path/to/socket';
		$this->assertEquals( $expected, Utils\mysql_host_to_cli_args( $testcase ) );

		$testcase = 'p:hostname:/path/to/socket';
		$this->assertEquals( $expected, Utils\mysql_host_to_cli_args( $testcase ) );
	}

	public function testForceEnvOnNixSystems() {
		$env_is_windows = getenv( 'WP_CLI_TEST_IS_WINDOWS' );

		putenv( 'WP_CLI_TEST_IS_WINDOWS=0' );
		$this->assertSame( '/usr/bin/env cmd', Utils\force_env_on_nix_systems( 'cmd' ) );
		$this->assertSame( '/usr/bin/env cmd', Utils\force_env_on_nix_systems( '/usr/bin/env cmd' ) );

		putenv( 'WP_CLI_TEST_IS_WINDOWS=1' );
		$this->assertSame( 'cmd', Utils\force_env_on_nix_systems( 'cmd' ) );
		$this->assertSame( 'cmd', Utils\force_env_on_nix_systems( '/usr/bin/env cmd' ) );

		putenv( false === $env_is_windows ? 'WP_CLI_TEST_IS_WINDOWS' : "WP_CLI_TEST_IS_WINDOWS=$env_is_windows" );
	}

	public function testGetHomeDir() {

		// save environments
		$home      = getenv( 'HOME' );
		$homedrive = getenv( 'HOMEDRIVE' );
		$homepath  = getenv( 'HOMEPATH' );

		putenv( 'HOME=/home/user' );
		$this->assertSame( '/home/user', Utils\get_home_dir() );

		putenv( 'HOME' );

		putenv( 'HOMEDRIVE=D:' );
		putenv( 'HOMEPATH' );
		$this->assertSame( 'D:', Utils\get_home_dir() );

		putenv( 'HOMEPATH=\\Windows\\User\\' );
		$this->assertSame( 'D:\\Windows\\User', Utils\get_home_dir() );

		putenv( 'HOMEPATH=\\Windows\\User\\HOGE\\' );
		$this->assertSame( 'D:\\Windows\\User\\HOGE', Utils\get_home_dir() );

		// restore environments
		putenv( false === $home ? 'HOME' : "HOME=$home" );
		putenv( false === $homedrive ? 'HOMEDRIVE' : "HOME=$homedrive" );
		putenv( false === $homepath ? 'HOMEPATH' : "HOME=$homepath" );
	}

	public function testTrailingslashit() {
		$this->assertSame( 'a/', Utils\trailingslashit( 'a' ) );
		$this->assertSame( 'a/', Utils\trailingslashit( 'a/' ) );
		$this->assertSame( 'a/', Utils\trailingslashit( 'a\\' ) );
		$this->assertSame( 'a/', Utils\trailingslashit( 'a\\//\\' ) );
	}

	/**
	 * @dataProvider dataNormalizePath
	 */
	public function testNormalizePath( $path, $expected ) {
		$this->assertEquals( $expected, Utils\normalize_path( $path ) );
	}

	public function dataNormalizePath() {
		return [
			[ '', '' ],
			// Windows paths
			[ 'C:\\www\\path\\', 'C:/www/path/' ],
			[ 'C:\\www\\\\path\\', 'C:/www/path/' ],
			[ 'c:/www/path', 'C:/www/path' ],
			[ 'c:\\www\\path\\', 'C:/www/path/' ], // uppercase drive letter
			[ 'c:', 'C:' ],
			[ 'c:\\', 'C:/' ],
			[ 'c:\\\\www\\path\\', 'C:/www/path/' ],
			[ '\\\\Domain\\DFSRoots\\share\\path\\', '//Domain/DFSRoots/share/path/' ],
			[ '\\\\Server\\share\\path', '//Server/share/path' ],
			[ '\\\\Server\\share', '//Server/share' ],
			// Linux paths
			[ '/', '/' ],
			[ '/www/path/', '/www/path/' ],
			[ '/www/path/////', '/www/path/' ],
			[ '/www/path', '/www/path' ],
			[ '/www/path', '/www/path' ],
		];
	}

	public function testNormalizeEols() {
		$this->assertSame( "\na\ra\na\n", Utils\normalize_eols( "\r\na\ra\r\na\r\n" ) );
	}

	public function testGetTempDir() {
		$this->assertTrue( '/' === substr( Utils\get_temp_dir(), -1 ) );
	}

	public function testHttpRequestBadAddress() {
		// Save WP_CLI state.
		$class_wp_cli_capture_exit = new \ReflectionProperty( 'WP_CLI', 'capture_exit' );
		$class_wp_cli_capture_exit->setAccessible( true );
		$prev_capture_exit = $class_wp_cli_capture_exit->getValue();

		$prev_logger = WP_CLI::get_logger();

		// Enable exit exception.
		$class_wp_cli_capture_exit->setValue( true );

		$logger = new Loggers\Execution();
		WP_CLI::set_logger( $logger );

		$exception = null;
		try {
			Utils\http_request( 'GET', 'https://nosuchhost_asdf_asdf_asdf.com', null /*data*/, [] /*headers*/, [ 'timeout' => 0.01 ] );
		} catch ( ExitException $ex ) {
			$exception = $ex;
		}
		$this->assertTrue( null !== $exception );
		$this->assertTrue( 1 === $exception->getCode() );
		$this->assertTrue( empty( $logger->stdout ) );
		$this->assertTrue( false === strpos( $logger->stderr, 'Warning' ) );
		$this->assertTrue( 0 === strpos( $logger->stderr, 'Error: Failed to get url' ) );

		// Restore.
		$class_wp_cli_capture_exit->setValue( $prev_capture_exit );
		WP_CLI::set_logger( $prev_logger );
	}

	public function dataHttpRequestBadCAcert() {
		return [
			'default request'  => [
				[],
				RuntimeException::class,
				'Failed to get url \'https://example.com\': cURL error 77: error setting certificate verify locations:',
			],
			'secure request'   => [
				[ 'insecure' => false ],
				RuntimeException::class,
				'Failed to get url \'https://example.com\': cURL error 77: error setting certificate verify locations:',
			],
			'insecure request' => [
				[ 'insecure' => true ],
				false,
				'Warning: Re-trying without verify after failing to get verified url',
			],
		];
	}

	/**
	 * @dataProvider dataHttpRequestBadCAcert()
	 *
	 * @param array  $additional_options Associative array of additional options to pass to http_request().
	 * @param string $exception          Class of the exception to expect.
	 * @param string $exception_message  Message of the exception to expect.
	 */
	public function testHttpRequestBadCAcert( $additional_options, $exception, $exception_message ) {
		if ( ! extension_loaded( 'curl' ) ) {
			$this->markTestSkipped( 'curl not available' );
		}

		// Create temporary file to use as a bad certificate file.
		$bad_cacert_path = tempnam( sys_get_temp_dir(), 'wp-cli-badcacert-pem-' );
		file_put_contents( $bad_cacert_path, "-----BEGIN CERTIFICATE-----\nasdfasdf\n-----END CERTIFICATE-----\n" );

		$options = array_merge(
			[
				'halt_on_error' => false,
				'verify'        => $bad_cacert_path,
			],
			$additional_options
		);

		if ( false !== $exception ) {
			$this->expectException( $exception );
			$this->expectExceptionMessage( $exception_message );
		} else {
			// Save WP_CLI state.
			$prev_logger = WP_CLI::get_logger();
			$logger      = new Loggers\Execution();
			WP_CLI::set_logger( $logger );
		}

		Utils\http_request( 'GET', 'https://example.com', null, [], $options );

		// Restore.
		WP_CLI::set_logger( $prev_logger );

		$this->assertTrue( empty( $logger->stdout ) );
		$this->assertNotFalse( strpos( $logger->stderr, $exception_message ) );
	}

	/**
	 * @dataProvider dataHttpRequestVerify
	 */
	public function testHttpRequestVerify( $expected, $options ) {
		$transport_spy        = new Mock_Requests_Transport();
		$options['transport'] = $transport_spy;

		Utils\http_request( 'GET', 'https://wordpress.org', null /*data*/, [] /*headers*/, $options );

		$this->assertCount( 1, $transport_spy->requests );
		$this->assertEquals( $expected, $transport_spy->requests[0]['options']['verify'] );
	}

	public function dataHttpRequestVerify() {
		return [
			'not passed'    => [
				true,
				[],
			],
			'true'          => [
				true,
				[ 'verify' => true ],
			],
			'false'         => [
				false,
				[ 'verify' => false ],
			],
			'custom cacert' => [
				__FILE__,
				[ 'verify' => __FILE__ ],
			],
		];
	}

	public function testGetDefaultCaCert() {
		$default_cert = Utils\get_default_cacert();
		$this->assertStringEndsWith(
			'/rmccue/requests/certificates/cacert.pem',
			$default_cert
		);
		$this->assertFileExists( $default_cert );
	}

	/**
	 * @dataProvider dataPastTenseVerb
	 */
	public function testPastTenseVerb( $verb, $expected ) {
		$this->assertSame( $expected, Utils\past_tense_verb( $verb ) );
	}

	public function dataPastTenseVerb() {
		return [
			// Known to be used by commands.
			[ 'activate', 'activated' ],
			[ 'deactivate', 'deactivated' ],
			[ 'delete', 'deleted' ],
			[ 'import', 'imported' ],
			[ 'install', 'installed' ],
			[ 'network activate', 'network activated' ],
			[ 'network deactivate', 'network deactivated' ],
			[ 'regenerate', 'regenerated' ],
			[ 'reset', 'reset' ],
			[ 'spam', 'spammed' ],
			[ 'toggle', 'toggled' ],
			[ 'uninstall', 'uninstalled' ],
			[ 'update', 'updated' ],
			// Some others.
			[ 'call', 'called' ],
			[ 'check', 'checked' ],
			[ 'crop', 'cropped' ],
			[ 'fix', 'fixed' ], // One vowel + final "x" excluded.
			[ 'ah', 'ahed' ], // One vowel + final "h" excluded.
			[ 'show', 'showed' ], // One vowel + final "w" excluded.
			[ 'ski', 'skied' ],
			[ 'slay', 'slayed' ], // One vowel + final "y" excluded (nearly all irregular anyway).
			[ 'submit', 'submited' ], // BUG: multi-voweled verbs that double not catered for - should be "submitted".
			[ 'try', 'tried' ],
		];
	}

	/**
	 * @dataProvider dataExpandGlobs
	 */
	public function testExpandGlobs( $path, $expected ) {
		$expand_globs_no_glob_brace = getenv( 'WP_CLI_TEST_EXPAND_GLOBS_NO_GLOB_BRACE' );

		$dir      = __DIR__ . '/data/expand_globs/';
		$concat   = function ( $v ) use ( $dir ) {
			return $dir . $v;
		};
		$expected = array_map( $concat, $expected );
		sort( $expected );

		putenv( 'WP_CLI_TEST_EXPAND_GLOBS_NO_GLOB_BRACE=0' );
		$out = Utils\expand_globs( $dir . $path );
		sort( $out );
		$this->assertSame( $expected, $out );

		putenv( 'WP_CLI_TEST_EXPAND_GLOBS_NO_GLOB_BRACE=1' );
		$out = Utils\expand_globs( $dir . $path );
		sort( $out );
		$this->assertSame( $expected, $out );

		putenv( false === $expand_globs_no_glob_brace ? 'WP_CLI_TEST_EXPAND_GLOBS_NO_GLOB_BRACE' : "WP_CLI_TEST_EXPAND_GLOBS_NO_GLOB_BRACE=$expand_globs_no_glob_brace" );
	}

	public function dataExpandGlobs() {
		// Files in "data/expand_globs": foo.ab1, foo.ab2, foo.efg1, foo.efg2, bar.ab1, bar.ab2, baz.ab1, baz.ac1, baz.efg2.
		return [
			[ 'foo.ab1', [ 'foo.ab1' ] ],
			[ '{foo,bar}.ab1', [ 'foo.ab1', 'bar.ab1' ] ],
			[ '{foo,baz}.a{b,c}1', [ 'foo.ab1', 'baz.ab1', 'baz.ac1' ] ],
			[ '{foo,baz}.{ab,ac}1', [ 'foo.ab1', 'baz.ab1', 'baz.ac1' ] ],
			[ '{foo,bar}.{ab1,efg1}', [ 'foo.ab1', 'foo.efg1', 'bar.ab1' ] ],
			[ '{foo,bar,baz}.{ab,ac,efg}1', [ 'foo.ab1', 'foo.efg1', 'bar.ab1', 'baz.ab1', 'baz.ac1' ] ],
			[ '{foo,ba{r,z}}.ab1', [ 'foo.ab1', 'bar.ab1', 'baz.ab1' ] ],
			[ '{foo,ba{r,z}}.{ab1,efg1}', [ 'foo.ab1', 'foo.efg1', 'bar.ab1', 'baz.ab1' ] ],
			[ '{foo,bar}.{ab{1,2},efg1}', [ 'foo.ab1', 'foo.ab2', 'foo.efg1', 'bar.ab1', 'bar.ab2' ] ],
			[ '{foo,ba{r,z}}.{a{b,c}{1,2},efg{1,2}}', [ 'foo.ab1', 'foo.ab2', 'foo.efg1', 'foo.efg2', 'bar.ab1', 'bar.ab2', 'baz.ab1', 'baz.ac1', 'baz.efg2' ] ],

			[ 'no_such_file', [ 'no_such_file' ] ], // Documenting this behaviour here, which is odd (though advertized) - more natural to return an empty array.
		];
	}

	/**
	 * @dataProvider dataReportBatchOperationResults
	 */
	public function testReportBatchOperationResults( $stdout, $stderr, $noun, $verb, $total, $successes, $failures, $skips ) {
		// Save WP_CLI state.
		$class_wp_cli_capture_exit = new \ReflectionProperty( 'WP_CLI', 'capture_exit' );
		$class_wp_cli_capture_exit->setAccessible( true );
		$prev_capture_exit = $class_wp_cli_capture_exit->getValue();

		$prev_logger = WP_CLI::get_logger();

		// Enable exit exception.
		$class_wp_cli_capture_exit->setValue( true );

		$logger = new Loggers\Execution();
		WP_CLI::set_logger( $logger );

		$exception = null;

		try {
			Utils\report_batch_operation_results( $noun, $verb, $total, $successes, $failures, $skips );
		} catch ( ExitException $ex ) {
			$exception = $ex;
		}
		$this->assertSame( $stdout, $logger->stdout );
		$this->assertSame( $stderr, $logger->stderr );

		// Restore.
		$class_wp_cli_capture_exit->setValue( $prev_capture_exit );
		WP_CLI::set_logger( $prev_logger );
	}

	public function dataReportBatchOperationResults() {
		return [
			[ "Success: Noun already verbed.\n", '', 'noun', 'verb', 1, 0, 0, null ],
			[ "Success: Verbed 1 of 1 nouns.\n", '', 'noun', 'verb', 1, 1, 0, null ],
			[ "Success: Verbed 1 of 2 nouns.\n", '', 'noun', 'verb', 2, 1, 0, null ],
			[ "Success: Verbed 2 of 2 nouns.\n", '', 'noun', 'verb', 2, 2, 0, 0 ],
			[ "Success: Verbed 1 of 2 nouns (1 skipped).\n", '', 'noun', 'verb', 2, 1, 0, 1 ],
			[ "Success: Verbed 2 of 4 nouns (2 skipped).\n", '', 'noun', 'verb', 4, 2, 0, 2 ],
			[ '', "Error: No nouns verbed.\n", 'noun', 'verb', 1, 0, 1, null ],
			[ '', "Error: No nouns verbed.\n", 'noun', 'verb', 2, 0, 1, null ],
			[ '', "Error: No nouns verbed (2 failed).\n", 'noun', 'verb', 3, 0, 2, 0 ],
			[ '', "Error: No nouns verbed (2 failed, 1 skipped).\n", 'noun', 'verb', 3, 0, 2, 1 ],
			[ '', "Error: Only verbed 1 of 2 nouns.\n", 'noun', 'verb', 2, 1, 1, null ],
			[ '', "Error: Only verbed 1 of 3 nouns (2 failed).\n", 'noun', 'verb', 3, 1, 2, 0 ],
			[ '', "Error: Only verbed 1 of 6 nouns (3 failed, 2 skipped).\n", 'noun', 'verb', 6, 1, 3, 2 ],
		];
	}

	public function testGetPHPBinary() {
		$env_php_used = getenv( 'WP_CLI_PHP_USED' );
		$env_php      = getenv( 'WP_CLI_PHP' );

		putenv( 'WP_CLI_PHP_USED' );
		putenv( 'WP_CLI_PHP' );
		$get_php_binary = Utils\get_php_binary();
		$this->assertTrue( is_executable( $get_php_binary ) );

		putenv( 'WP_CLI_PHP_USED=/my-php-5.3' );
		putenv( 'WP_CLI_PHP' );
		$get_php_binary = Utils\get_php_binary();
		$this->assertSame( $get_php_binary, '/my-php-5.3' );

		putenv( 'WP_CLI_PHP=/my-php-7.3' );
		$get_php_binary = Utils\get_php_binary();
		$this->assertSame( $get_php_binary, '/my-php-5.3' ); // WP_CLI_PHP_USED wins.

		putenv( 'WP_CLI_PHP_USED' );
		$get_php_binary = Utils\get_php_binary();
		$this->assertSame( $get_php_binary, '/my-php-7.3' );

		putenv( false === $env_php_used ? 'WP_CLI_PHP_USED' : "WP_CLI_PHP_USED=$env_php_used" );
		putenv( false === $env_php ? 'WP_CLI_PHP' : "WP_CLI_PHP=$env_php" );
	}

	/**
	 * @dataProvider dataProcOpenCompatWinEnv
	 */
	public function testProcOpenCompatWinEnv( $cmd, $env, $expected_cmd, $expected_env ) {
		$env_is_windows = getenv( 'WP_CLI_TEST_IS_WINDOWS' );

		putenv( 'WP_CLI_TEST_IS_WINDOWS=1' );

		$cmd = Utils\_proc_open_compat_win_env( $cmd, $env );
		$this->assertSame( $expected_cmd, $cmd );
		$this->assertSame( $expected_env, $env );

		putenv( false === $env_is_windows ? 'WP_CLI_TEST_IS_WINDOWS' : "WP_CLI_TEST_IS_WINDOWS=$env_is_windows" );
	}

	public function dataProcOpenCompatWinEnv() {
		return [
			[ 'echo', [], 'echo', [] ],
			[ 'ENV=blah echo', [], 'echo', [ 'ENV' => 'blah' ] ],
			[ 'ENV="blah blah" echo', [], 'echo', [ 'ENV' => 'blah blah' ] ],
			[ 'ENV_1="blah1 blah1" ENV_2="blah2" ENV_3=blah3 echo', [], 'echo', [ 'ENV_1' => 'blah1 blah1', 'ENV_2' => 'blah2', 'ENV_3' => 'blah3' ] ],
			[ 'ENV= echo', [], 'echo', [ 'ENV' => '' ] ],
			[ 'ENV=0 echo', [], 'echo', [ 'ENV' => '0' ] ],

			// With `$env` set.
			[ 'echo', [ 'ENV' => 'in' ], 'echo', [ 'ENV' => 'in' ] ],
			[ 'ENV=blah echo', [ 'ENV_1' => 'in1', 'ENV_2' => 'in2' ], 'echo', [ 'ENV_1' => 'in1', 'ENV_2' => 'in2', 'ENV' => 'blah' ] ],
			[ 'ENV="blah blah" echo', [ 'ENV' => 'in' ], 'echo', [ 'ENV' => 'blah blah' ] ],

			// Special cases.
			[ '1=1 echo', [], '1=1 echo', [] ], // Must begin with alphabetic or underscore.
			[ '_eNv=1 echo', [], 'echo', [ '_eNv' => '1' ] ], // Mixed-case and beginning with underscore allowed.
			[ 'ENV=\'blah blah\' echo', [], 'blah\' echo', [ 'ENV' => '\'blah' ] ], // Unix escaping not supported, ie treated literally.
		];
	}

	/**
	 * Copied from core "tests/phpunit/tests/db.php" (adapted to not use `$wpdb`).
	 */
	public function test_esc_like() {
		$inputs   = [
			'howdy%', //Single Percent
			'howdy_', //Single Underscore
			'howdy\\', //Single slash
			'howdy\\howdy%howdy_', //The works
			'howdy\'"[[]*#[^howdy]!+)(*&$#@!~|}{=--`/.,<>?', //Plain text
		];
		$expected = [
			'howdy\\%',
			'howdy\\_',
			'howdy\\\\',
			'howdy\\\\howdy\\%howdy\\_',
			'howdy\'"[[]*#[^howdy]!+)(*&$#@!~|}{=--`/.,<>?',
		];

		foreach ( $inputs as $key => $input ) {
			$this->assertEquals( $expected[ $key ], Utils\esc_like( $input ) );
		}
	}

	/**
	 * @dataProvider dataIsJson
	 */
	public function testIsJson( $argument, $ignore_scalars, $expected ) {
		$this->assertEquals( $expected, Utils\is_json( $argument, $ignore_scalars ) );
	}

	public function dataIsJson() {
		return [
			[ '42', true, false ],
			[ '42', false, true ],
			[ '"test"', true, false ],
			[ '"test"', false, true ],
			[ '{"key1":"value1","key2":"value2"}', true, true ],
			[ '{"key1":"value1","key2":"value2"}', false, true ],
			[ '["value1","value2"]', true, true ],
			[ '["value1","value2"]', false, true ],
			[ '0', true, false ],
			[ '0', false, true ],
			[ '', true, false ],
			[ '', false, false ],
		];
	}

	/**
	 * @dataProvider dataParseShellArray
	 */
	public function testParseShellArray( $assoc_args, $array_arguments, $expected ) {
		$this->assertEquals( $expected, Utils\parse_shell_arrays( $assoc_args, $array_arguments ) );
	}

	public function dataParseShellArray() {
		return [
			[ [ 'alpha' => '{"key":"value"}' ], [], [ 'alpha' => '{"key":"value"}' ] ],
			[ [ 'alpha' => '{"key":"value"}' ], [ 'alpha' ], [ 'alpha' => [ 'key' => 'value' ] ] ],
			[ [ 'alpha' => '{"key":"value"}' ], [ 'beta' ], [ 'alpha' => '{"key":"value"}' ] ],
		];
	}

	/**
	 * @dataProvider dataPluralize
	 */
	public function testPluralize( $singular, $count, $expected ) {
		$this->assertEquals( $expected, Utils\pluralize( $singular, $count ) );
	}

	public function dataPluralize() {
		return [
			[ 'string', 1, 'string' ],
			[ 'string', 2, 'strings' ],
			[ 'string', null, 'strings' ],
		];
	}

	/**
	 * @dataProvider dataPickFields
	 */
	public function testPickFields( $data, $fields, $expected ) {
		$this->assertEquals( $expected, Utils\pick_fields( $data, $fields ) );
	}

	public function dataPickFields() {
		return [
			[ [ 'keyA' => 'valA', 'keyB' => 'valB', 'keyC' => 'valC' ], [ 'keyB' ], [ 'keyB' => 'valB' ] ],
			[ [ '1' => 'valA', '2' => 'valB', '3' => 'valC' ], [ '2' ], [ '2' => 'valB' ] ],
			[ [ 1 => 'valA', 2 => 'valB', 3 => 'valC' ], [ 2 ], [ 2 => 'valB' ] ],
			[ (object) [ 'keyA' => 'valA', 'keyB' => 'valB', 'keyC' => 'valC' ], [ 'keyB' ], [ 'keyB' => 'valB' ] ],
			[ [], [ 'keyB' ], [ 'keyB' => null ] ],
			[ [ 'keyA' => 'valA', 'keyB' => 'valB', 'keyC' => 'valC' ], [ 'keyD' ], [ 'keyD' => null ] ],
			[ [ 'keyA' => 'valA', 'keyB' => 'valB', 'keyC' => 'valC' ], [ 'keyA', 'keyB', 'keyC', 'keyD' ], [ 'keyA' => 'valA', 'keyB' => 'valB', 'keyC' => 'valC', 'keyD' => null ] ],
		];
	}

	/**
	 * @dataProvider dataParseUrl
	 */
	public function testParseUrl( $url, $component, $auto_add_scheme, $expected ) {
		$this->assertEquals( $expected, Utils\parse_url( $url, $component, $auto_add_scheme ) );
	}

	public function dataParseUrl() {
		return [
			[ 'http://user:pass@example.com:9090/path?arg=value#anchor', -1, true, [ 'scheme' => 'http', 'host' => 'example.com', 'port' => 9090, 'user' => 'user', 'pass' => 'pass', 'path' => '/path', 'query' => 'arg=value', 'fragment' => 'anchor' ] ],
			[ 'example.com:9090/path?arg=value#anchor', -1, true, [ 'scheme' => 'http', 'host' => 'example.com', 'port' => 9090, 'path' => '/path', 'query' => 'arg=value', 'fragment' => 'anchor' ] ],
			[ 'example.com:9090/path?arg=value#anchor', -1, false, [ 'host' => 'example.com', 'port' => 9090, 'path' => '/path', 'query' => 'arg=value', 'fragment' => 'anchor' ] ],
			[ 'https://example.com', PHP_URL_HOST, true, 'example.com' ],
		];
	}

	public function testReplacePathConstsAddSlashes() {
		$expected = "define( 'ABSPATH', dirname( 'C:\\\\Users\\\\test\'s\\\\site' ) . '/' );";
		$source   = "define( 'ABSPATH', dirname( __FILE__ ) . '/' );";
		$actual   = Utils\replace_path_consts( $source, "C:\Users\\test's\site" );
		$this->assertSame( $expected, $actual );
	}

	/**
	 * @dataProvider dataValidClassAndMethodPair
	 */
	public function testValidClassAndMethodPair( $pair, $is_valid ) {
		$this->assertEquals( $is_valid, Utils\is_valid_class_and_method_pair( $pair ) );
	}

	public function dataValidClassAndMethodPair() {
		return [
			[ 'string', false ],
			[ [], false ],
			[ [ 'WP_CLI' ], false ],
			[ [ true, false ], false ],
			[ [ 'WP_CLI', 'invalid_method' ], false ],
			[ [ 'Invalid_Class', 'invalid_method' ], false ],
			[ [ 'WP_CLI', 'add_command' ], true ],
			[ [ 'Exception', 'getMessage' ], true ],
		];
	}
}
