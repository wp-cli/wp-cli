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
		$this->assertEquals( 'foo', Utils\parse_ssh_url( $testcase, PHP_URL_HOST ) );
		$this->assertEquals( null, Utils\parse_ssh_url( $testcase, PHP_URL_PORT ) );
		$this->assertEquals( null, Utils\parse_ssh_url( $testcase, PHP_URL_PATH ) );

		$testcase = 'foo.com';
		$this->assertEquals( array(
			'host' => 'foo.com',
		), Utils\parse_ssh_url( $testcase ) );
		$this->assertEquals( 'foo.com', Utils\parse_ssh_url( $testcase, PHP_URL_HOST ) );
		$this->assertEquals( null, Utils\parse_ssh_url( $testcase, PHP_URL_PORT ) );
		$this->assertEquals( null, Utils\parse_ssh_url( $testcase, PHP_URL_PATH ) );

		$testcase = 'foo.com:2222';
		$this->assertEquals( array(
			'host' => 'foo.com',
			'port' => 2222,
		), Utils\parse_ssh_url( $testcase ) );
		$this->assertEquals( 'foo.com', Utils\parse_ssh_url( $testcase, PHP_URL_HOST ) );
		$this->assertEquals( 2222, Utils\parse_ssh_url( $testcase, PHP_URL_PORT ) );
		$this->assertEquals( null, Utils\parse_ssh_url( $testcase, PHP_URL_PATH ) );

		$testcase = 'foo.com:2222/path/to/dir';
		$this->assertEquals( array(
			'host' => 'foo.com',
			'port' => 2222,
			'path' => '/path/to/dir',
		), Utils\parse_ssh_url( $testcase ) );
		$this->assertEquals( 'foo.com', Utils\parse_ssh_url( $testcase, PHP_URL_HOST ) );
		$this->assertEquals( 2222, Utils\parse_ssh_url( $testcase, PHP_URL_PORT ) );
		$this->assertEquals( '/path/to/dir', Utils\parse_ssh_url( $testcase, PHP_URL_PATH ) );

		$testcase = 'foo.com~/path/to/dir';
		$this->assertEquals( array(
			'host' => 'foo.com',
			'path' => '~/path/to/dir',
		), Utils\parse_ssh_url( $testcase ) );
		$this->assertEquals( 'foo.com', Utils\parse_ssh_url( $testcase, PHP_URL_HOST ) );
		$this->assertEquals( null, Utils\parse_ssh_url( $testcase, PHP_URL_PORT ) );
		$this->assertEquals( '~/path/to/dir', Utils\parse_ssh_url( $testcase, PHP_URL_PATH ) );

		// No host
		$testcase = '~/path/to/dir';
		$this->assertEquals( array(), Utils\parse_ssh_url( $testcase ) );
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

}

