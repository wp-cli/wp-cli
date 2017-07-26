<?php

use WP_CLI\Utils;

class UtilsTest extends PHPUnit_Framework_TestCase {

	function testIncrementVersion() {
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
		$this->assertEquals( 'patch', Utils\get_named_sem_ver( '3.0.1', $original_version ) );
		$this->assertEquals( 'minor', Utils\get_named_sem_ver( '3.1', $original_version ) );
		$this->assertEquals( 'minor', Utils\get_named_sem_ver( '3.1.1', $original_version ) );
		$this->assertEquals( 'major', Utils\get_named_sem_ver( '4.0', $original_version ) );
		$this->assertEquals( 'major', Utils\get_named_sem_ver( '4.1.1', $original_version ) );
	}

	public function testParseSSHUrl() {
		$testcase = 'foo';
		$this->assertEquals( array(
			'host' => 'foo',
		), Utils\parse_ssh_url( $testcase ) );
		$this->assertEquals( null, Utils\parse_ssh_url( $testcase, PHP_URL_SCHEME ) );
		$this->assertEquals( null, Utils\parse_ssh_url( $testcase, PHP_URL_USER ) );
		$this->assertEquals( 'foo', Utils\parse_ssh_url( $testcase, PHP_URL_HOST ) );
		$this->assertEquals( null, Utils\parse_ssh_url( $testcase, PHP_URL_PORT ) );
		$this->assertEquals( null, Utils\parse_ssh_url( $testcase, PHP_URL_PATH ) );

		$testcase = 'foo.com';
		$this->assertEquals( array(
			'host' => 'foo.com',
		), Utils\parse_ssh_url( $testcase ) );
		$this->assertEquals( null, Utils\parse_ssh_url( $testcase, PHP_URL_SCHEME ) );
		$this->assertEquals( null, Utils\parse_ssh_url( $testcase, PHP_URL_USER ) );
		$this->assertEquals( 'foo.com', Utils\parse_ssh_url( $testcase, PHP_URL_HOST ) );
		$this->assertEquals( null, Utils\parse_ssh_url( $testcase, PHP_URL_PORT ) );
		$this->assertEquals( null, Utils\parse_ssh_url( $testcase, PHP_URL_PATH ) );

		$testcase = 'foo.com:2222';
		$this->assertEquals( array(
			'host' => 'foo.com',
			'port' => 2222,
		), Utils\parse_ssh_url( $testcase ) );
		$this->assertEquals( null, Utils\parse_ssh_url( $testcase, PHP_URL_SCHEME ) );
		$this->assertEquals( null, Utils\parse_ssh_url( $testcase, PHP_URL_USER ) );
		$this->assertEquals( 'foo.com', Utils\parse_ssh_url( $testcase, PHP_URL_HOST ) );
		$this->assertEquals( 2222, Utils\parse_ssh_url( $testcase, PHP_URL_PORT ) );
		$this->assertEquals( null, Utils\parse_ssh_url( $testcase, PHP_URL_PATH ) );

		$testcase = 'foo.com:2222/path/to/dir';
		$this->assertEquals( array(
			'host' => 'foo.com',
			'port' => 2222,
			'path' => '/path/to/dir',
		), Utils\parse_ssh_url( $testcase ) );
		$this->assertEquals( null, Utils\parse_ssh_url( $testcase, PHP_URL_SCHEME ) );
		$this->assertEquals( null, Utils\parse_ssh_url( $testcase, PHP_URL_USER ) );
		$this->assertEquals( 'foo.com', Utils\parse_ssh_url( $testcase, PHP_URL_HOST ) );
		$this->assertEquals( 2222, Utils\parse_ssh_url( $testcase, PHP_URL_PORT ) );
		$this->assertEquals( '/path/to/dir', Utils\parse_ssh_url( $testcase, PHP_URL_PATH ) );

		$testcase = 'foo.com~/path/to/dir';
		$this->assertEquals( array(
			'host' => 'foo.com',
			'path' => '~/path/to/dir',
		), Utils\parse_ssh_url( $testcase ) );
		$this->assertEquals( null, Utils\parse_ssh_url( $testcase, PHP_URL_SCHEME ) );
		$this->assertEquals( null, Utils\parse_ssh_url( $testcase, PHP_URL_USER ) );
		$this->assertEquals( 'foo.com', Utils\parse_ssh_url( $testcase, PHP_URL_HOST ) );
		$this->assertEquals( null, Utils\parse_ssh_url( $testcase, PHP_URL_PORT ) );
		$this->assertEquals( '~/path/to/dir', Utils\parse_ssh_url( $testcase, PHP_URL_PATH ) );

		// No host
		$testcase = '~/path/to/dir';
		$this->assertEquals( array(), Utils\parse_ssh_url( $testcase ) );
		$this->assertEquals( null, Utils\parse_ssh_url( $testcase, PHP_URL_SCHEME ) );
		$this->assertEquals( null, Utils\parse_ssh_url( $testcase, PHP_URL_USER ) );
		$this->assertEquals( null, Utils\parse_ssh_url( $testcase, PHP_URL_HOST ) );
		$this->assertEquals( null, Utils\parse_ssh_url( $testcase, PHP_URL_PORT ) );
		$this->assertEquals( null, Utils\parse_ssh_url( $testcase, PHP_URL_PATH ) );

		// host and path, no port, with scp notation
		$testcase = 'foo.com:~/path/to/dir';
		$this->assertEquals( array(
			'host' => 'foo.com',
			'path' => '~/path/to/dir',
		), Utils\parse_ssh_url( $testcase ) );
		$this->assertEquals( null, Utils\parse_ssh_url( $testcase, PHP_URL_SCHEME ) );
		$this->assertEquals( null, Utils\parse_ssh_url( $testcase, PHP_URL_USER ) );
		$this->assertEquals( 'foo.com', Utils\parse_ssh_url( $testcase, PHP_URL_HOST ) );
		$this->assertEquals( null, Utils\parse_ssh_url( $testcase, PHP_URL_PORT ) );
		$this->assertEquals( '~/path/to/dir', Utils\parse_ssh_url( $testcase, PHP_URL_PATH ) );

		$testcase = 'foo.com:2222~/path/to/dir';
		$this->assertEquals( array(
			'host' => 'foo.com',
			'path' => '~/path/to/dir',
			'port' => '2222'
		), Utils\parse_ssh_url( $testcase ) );
		$this->assertEquals( null, Utils\parse_ssh_url( $testcase, PHP_URL_SCHEME ) );
		$this->assertEquals( null, Utils\parse_ssh_url( $testcase, PHP_URL_USER ) );
		$this->assertEquals( 'foo.com', Utils\parse_ssh_url( $testcase, PHP_URL_HOST ) );
		$this->assertEquals( '2222', Utils\parse_ssh_url( $testcase, PHP_URL_PORT ) );
		$this->assertEquals( '~/path/to/dir', Utils\parse_ssh_url( $testcase, PHP_URL_PATH ) );

		// explicit scheme, user, host, path, no port
		$testcase = 'ssh:bar@foo.com:~/path/to/dir';
		$this->assertEquals( array(
			'scheme' => 'ssh',
			'user' => 'bar',
			'host' => 'foo.com',
			'path' => '~/path/to/dir',
		), Utils\parse_ssh_url( $testcase ) );
		$this->assertEquals( 'ssh', Utils\parse_ssh_url( $testcase, PHP_URL_SCHEME ) );
		$this->assertEquals( 'bar', Utils\parse_ssh_url( $testcase, PHP_URL_USER ) );
		$this->assertEquals( 'foo.com', Utils\parse_ssh_url( $testcase, PHP_URL_HOST ) );
		$this->assertEquals( null, Utils\parse_ssh_url( $testcase, PHP_URL_PORT ) );
		$this->assertEquals( '~/path/to/dir', Utils\parse_ssh_url( $testcase, PHP_URL_PATH ) );

		// container scheme
		$testcase = 'docker:wordpress';
		$this->assertEquals( array(
			'scheme' => 'docker',
			'host' => 'wordpress',
		), Utils\parse_ssh_url( $testcase ) );
		$this->assertEquals( 'docker', Utils\parse_ssh_url( $testcase, PHP_URL_SCHEME ) );
		$this->assertEquals( null, Utils\parse_ssh_url( $testcase, PHP_URL_USER ) );
		$this->assertEquals( 'wordpress', Utils\parse_ssh_url( $testcase, PHP_URL_HOST ) );
		$this->assertEquals( null, Utils\parse_ssh_url( $testcase, PHP_URL_PORT ) );
		$this->assertEquals( null, Utils\parse_ssh_url( $testcase, PHP_URL_PATH ) );

		// container scheme with user, and host
		$testcase = 'docker:bar@wordpress';
		$this->assertEquals( array(
			'scheme' => 'docker',
			'user' => 'bar',
			'host' => 'wordpress',
		), Utils\parse_ssh_url( $testcase ) );
		$this->assertEquals( 'docker', Utils\parse_ssh_url( $testcase, PHP_URL_SCHEME ) );
		$this->assertEquals( 'bar', Utils\parse_ssh_url( $testcase, PHP_URL_USER ) );
		$this->assertEquals( 'wordpress', Utils\parse_ssh_url( $testcase, PHP_URL_HOST ) );
		$this->assertEquals( null, Utils\parse_ssh_url( $testcase, PHP_URL_PORT ) );
		$this->assertEquals( null, Utils\parse_ssh_url( $testcase, PHP_URL_PATH ) );

		// container scheme with user, host, and path
		$testcase = 'docker-compose:bar@wordpress:~/path/to/dir';
		$this->assertEquals( array(
			'scheme' => 'docker-compose',
			'user' => 'bar',
			'host' => 'wordpress',
			'path' => '~/path/to/dir',
		), Utils\parse_ssh_url( $testcase ) );
		$this->assertEquals( 'docker-compose', Utils\parse_ssh_url( $testcase, PHP_URL_SCHEME ) );
		$this->assertEquals( 'bar', Utils\parse_ssh_url( $testcase, PHP_URL_USER ) );
		$this->assertEquals( 'wordpress', Utils\parse_ssh_url( $testcase, PHP_URL_HOST ) );
		$this->assertEquals( null, Utils\parse_ssh_url( $testcase, PHP_URL_PORT ) );
		$this->assertEquals( '~/path/to/dir', Utils\parse_ssh_url( $testcase, PHP_URL_PATH ) );

		// unsupported scheme, should not match
		$testcase = 'foo:bar';
		$this->assertEquals( array(), Utils\parse_ssh_url( $testcase ) );
		$this->assertEquals( null, Utils\parse_ssh_url( $testcase, PHP_URL_SCHEME ) );
		$this->assertEquals( null, Utils\parse_ssh_url( $testcase, PHP_URL_USER ) );
		$this->assertEquals( null, Utils\parse_ssh_url( $testcase, PHP_URL_HOST ) );
		$this->assertEquals( null, Utils\parse_ssh_url( $testcase, PHP_URL_PORT ) );
		$this->assertEquals( null, Utils\parse_ssh_url( $testcase, PHP_URL_PATH ) );
	}

	public function testParseStrToArgv() {
		$this->assertEquals( array(), Utils\parse_str_to_argv( '' ) );
		$this->assertEquals( array(
			'option',
			'get',
			'home',
		), Utils\parse_str_to_argv( 'option get home' ) );
		$this->assertEquals( array(
			'core',
			'download',
			'--path=/var/www/',
		), Utils\parse_str_to_argv( 'core download --path=/var/www/' ) );
		$this->assertEquals( array(
			'eval',
			'echo wp_get_current_user()->user_login;',
		), Utils\parse_str_to_argv( 'eval "echo wp_get_current_user()->user_login;"' ) );
	}

	public function testAssocArgsToString() {
		$this->assertEquals( " --url='foo.dev' --porcelain --apple='banana'" , Utils\assoc_args_to_str( array(
			'url'       => 'foo.dev',
			'porcelain' => true,
			'apple'     => 'banana'
		) ) );
		$this->assertEquals( " --url='foo.dev' --require='file-a.php' --require='file-b.php' --porcelain --apple='banana'" , Utils\assoc_args_to_str( array(
			'url'       => 'foo.dev',
			'require'   => array(
				'file-a.php',
				'file-b.php',
			),
			'porcelain' => true,
			'apple'     => 'banana'
		) ) );
	}

	public function testForceEnvOnNixSystems() {
		putenv( 'WP_CLI_TEST_IS_WINDOWS=0' );
		$this->assertSame( '/usr/bin/env cmd', Utils\force_env_on_nix_systems( 'cmd' ) );
		$this->assertSame( '/usr/bin/env cmd', Utils\force_env_on_nix_systems( '/usr/bin/env cmd' ) );

		putenv( 'WP_CLI_TEST_IS_WINDOWS=1' );
		$this->assertSame( 'cmd', Utils\force_env_on_nix_systems( 'cmd' ) );
		$this->assertSame( 'cmd', Utils\force_env_on_nix_systems( '/usr/bin/env cmd' ) );

		putenv( 'WP_CLI_TEST_IS_WINDOWS' );
	}

	public function testGetHomeDir() {

		// save environments
		$home = getenv( 'HOME' );
		$homedrive = getenv( 'HOMEDRIVE' );
		$homepath = getenv( 'HOMEPATH' );

		putenv( 'HOME=/home/user' );
		$this->assertSame('/home/user', Utils\get_home_dir() );
		putenv( 'HOME=' );
		putenv( 'HOMEDRIVE=C:/\\Windows/\\User/\\' );
		$this->assertSame( 'C:/\Windows/\User', Utils\get_home_dir() );
		putenv( 'HOMEPATH=HOGE/\\' );
		$this->assertSame( 'C:/\Windows/\User/\HOGE', Utils\get_home_dir() );

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

	public function testGetTempDir() {
		$this->assertTrue( '/' === substr( Utils\get_temp_dir(), -1 ) );

		// INI directive `sys_temp_dir` introduced PHP 5.5.0.
		if ( version_compare( PHP_VERSION, '5.5.0', '>=' ) ) {

			// `sys_temp_dir` set.

			$cmd = "WP_CLI_PHP_ARGS='-dsys_temp_dir=\\tmp\\' bin/wp eval 'echo WP_CLI\\Utils\\get_temp_dir();' --skip-wordpress 2>&1";
			$output = array();
			exec( $cmd, $output );
			$this->assertTrue( 2 === count( $output ) );
			$this->assertTrue( 2 === preg_match_all( '/warning|writable/i', $output[0] ) );
			$this->assertSame( '\\tmp/', $output[1] );

			// `sys_temp_dir` unset and `upload_tmp_dir' set.

			// `upload_tmp_dir` needs to be a legitimate writable directory.
			$temp_dir = sys_get_temp_dir() . '/' . uniqid( 'test-utils-get-temp-dir', true );
			mkdir( $temp_dir, 0777, true );
			$cmd = "WP_CLI_PHP_ARGS='-dsys_temp_dir=0 -dupload_tmp_dir=$temp_dir\\' bin/wp eval 'echo WP_CLI\\Utils\\get_temp_dir();' --skip-wordpress 2>&1";
			$output = array();
			exec( $cmd, $output );

			rmdir( $temp_dir );

			$this->assertTrue( 1 === count( $output ) );
			$this->assertSame( $temp_dir . '/', trim( $output[0] ) );

			// Both `sys_temp_dir` and `upload_tmp_dir' unset.

			$cmd = "WP_CLI_PHP_ARGS='-dsys_temp_dir=0 -dupload_tmp_dir=0' bin/wp eval 'echo WP_CLI\\Utils\\get_temp_dir();' --skip-wordpress --quiet 2>&1";
			$output = array();
			exec( $cmd, $output );
			$this->assertTrue( 1 === count( $output ) );
			$this->assertSame( '/tmp/', trim( $output[0] ) );
		}
	}

	public function testRunMysqlCommandProcDisabled() {
		$err_msg = 'Error: Cannot do \'run_mysql_command\': The PHP functions `proc_open()` and/or `proc_close()` are disabled';

		$cmd = "WP_CLI_PHP_ARGS='-ddisable_functions=proc_open' bin/wp eval 'WP_CLI\\Utils\\run_mysql_command( null, array() );' --skip-wordpress 2>&1";
		$output = array();
		exec( $cmd, $output );
		$this->assertTrue( 1 === count( $output ) );
		$this->assertTrue( false !== strpos( trim( $output[0] ), $err_msg ) );

		$cmd = "WP_CLI_PHP_ARGS='-ddisable_functions=proc_close' bin/wp eval 'WP_CLI\\Utils\\run_mysql_command( null, array() );' --skip-wordpress 2>&1";
		$output = array();
		exec( $cmd, $output );
		$this->assertTrue( 1 === count( $output ) );
		$this->assertTrue( false !== strpos( trim( $output[0] ), $err_msg ) );
	}

	public function testLaunchEditorForInputProcDisabled() {
		$err_msg = 'Error: Cannot do \'launch_editor_for_input\': The PHP functions `proc_open()` and/or `proc_close()` are disabled';

		$cmd = "WP_CLI_PHP_ARGS='-ddisable_functions=proc_open' bin/wp eval 'WP_CLI\\Utils\\launch_editor_for_input( null, null );' --skip-wordpress 2>&1";
		$output = array();
		exec( $cmd, $output );
		$this->assertTrue( 1 === count( $output ) );
		$this->assertTrue( false !== strpos( trim( $output[0] ), $err_msg ) );

		$cmd = "WP_CLI_PHP_ARGS='-ddisable_functions=proc_close' bin/wp eval 'WP_CLI\\Utils\\launch_editor_for_input( null, null );' --skip-wordpress 2>&1";
		$output = array();
		exec( $cmd, $output );
		$this->assertTrue( 1 === count( $output ) );
		$this->assertTrue( false !== strpos( trim( $output[0] ), $err_msg ) );
	}

}
