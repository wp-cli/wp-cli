<?php

use WP_CLI\Utils;

class BehatTagsTest extends PHPUnit_Framework_TestCase {

	var $temp_dir;

	function setUp() {
		parent::setUp();

		$this->temp_dir = Utils\get_temp_dir() . uniqid( 'wp-cli-test-behat-tags-', true );
		mkdir( $this->temp_dir );
		mkdir( $this->temp_dir . '/features' );
	}

	function tearDown() {

		if ( $this->temp_dir && file_exists( $this->temp_dir ) ) {
			foreach ( glob( $this->temp_dir . '/features/*' ) as $feature_file ) {
				unlink( $feature_file );
			}
			rmdir( $this->temp_dir . '/features' );
			rmdir( $this->temp_dir );
		}

		parent::tearDown();
	}

	/**
	 * @dataProvider data_behat_tags_wp_version_github_token
	 */
	function test_behat_tags_wp_version_github_token( $env, $expected ) {
		$env_wp_version = getenv( 'WP_VERSION' );
		$env_github_token = getenv( 'GITHUB_TOKEN' );

		putenv( 'WP_VERSION' );
		putenv( 'GITHUB_TOKEN' );

		$behat_tags = dirname( __DIR__ ) . '/ci/behat-tags.php';

		$contents = '@require-wp-4.6 @require-wp-4.8 @require-wp-4.9 @less-than-wp-4.6 @less-than-wp-4.8 @less-than-wp-4.9';
		file_put_contents( $this->temp_dir . '/features/wp_version.feature', $contents );

		$output = exec( "cd {$this->temp_dir}; $env php $behat_tags" );
		$this->assertSame( '--tags=' . $expected . '&&~@broken', $output );

		putenv( false === $env_wp_version ? 'WP_VERSION' : "WP_VERSION=$env_wp_version" );
		putenv( false === $env_github_token ? 'GITHUB_TOKEN' : "GITHUB_TOKEN=$env_github_token" );
	}

	function data_behat_tags_wp_version_github_token() {
		return array(
			array( 'WP_VERSION=4.5', '~@require-wp-4.6&&~@require-wp-4.8&&~@require-wp-4.9&&~@github-api' ),
			array( 'WP_VERSION=4.6', '~@require-wp-4.8&&~@require-wp-4.9&&~@less-than-wp-4.6&&~@github-api' ),
			array( 'WP_VERSION=4.7', '~@require-wp-4.8&&~@require-wp-4.9&&~@less-than-wp-4.6&&~@github-api' ),
			array( 'WP_VERSION=4.8', '~@require-wp-4.9&&~@less-than-wp-4.6&&~@less-than-wp-4.8&&~@github-api' ),
			array( 'WP_VERSION=4.9', '~@less-than-wp-4.6&&~@less-than-wp-4.8&&~@less-than-wp-4.9&&~@github-api' ),
			array( 'WP_VERSION=5.0', '~@less-than-wp-4.6&&~@less-than-wp-4.8&&~@less-than-wp-4.9&&~@github-api' ),
			array( 'WP_VERSION=latest', '~@less-than-wp-4.6&&~@less-than-wp-4.8&&~@less-than-wp-4.9&&~@github-api' ),
			array( 'WP_VERSION=trunk', '~@less-than-wp-4.6&&~@less-than-wp-4.8&&~@less-than-wp-4.9&&~@github-api' ),
			array( 'WP_VERSION=nightly', '~@less-than-wp-4.6&&~@less-than-wp-4.8&&~@less-than-wp-4.9&&~@github-api' ),
			array( '', '~@less-than-wp-4.6&&~@less-than-wp-4.8&&~@less-than-wp-4.9&&~@github-api' ),
			array( 'GITHUB_TOKEN=blah', '~@less-than-wp-4.6&&~@less-than-wp-4.8&&~@less-than-wp-4.9' ),
		);
	}

	function test_behat_tags_php_version() {
		$env_github_token = getenv( 'GITHUB_TOKEN' );

		putenv( 'GITHUB_TOKEN' );

		$behat_tags = dirname( __DIR__ ) . '/ci/behat-tags.php';

		$php_version = substr( PHP_VERSION, 0, 3 );
		$contents = $expected = '';

		if ( '5.3' === $php_version ) {
			$contents = '@require-php-5.2 @require-php-5.3 @require-php-5.4 @less-than-php-5.2 @less-than-php-5.3 @less-than-php-5.4';
			$expected = '~@require-php-5.4&&~@less-than-php-5.2&&~@less-than-php-5.3';
		} elseif ( '5.4' === $php_version ) {
			$contents = '@require-php-5.3 @require-php-5.4 @require-php-5.5 @less-than-php-5.3 @less-than-php-5.4 @less-than-php-5.5';
			$expected = '~@require-php-5.5&&~@less-than-php-5.3&&~@less-than-php-5.4';
		} elseif ( '5.5' === $php_version ) {
			$contents = '@require-php-5.4 @require-php-5.5 @require-php-5.6 @less-than-php-5.4 @less-than-php-5.5 @less-than-php-5.6';
			$expected = '~@require-php-5.6&&~@less-than-php-5.4&&~@less-than-php-5.5';
		} elseif ( '5.6' === $php_version ) {
			$contents = '@require-php-5.5 @require-php-5.6 @require-php-7.0 @less-than-php-5.5 @less-than-php-5.6 @less-than-php-7.0';
			$expected = '~@require-php-7.0&&~@less-than-php-5.5&&~@less-than-php-5.6';
		} elseif ( '7.0' === $php_version ) {
			$contents = '@require-php-5.6 @require-php-7.0 @require-php-7.1 @less-than-php-5.6 @less-than-php-7.0 @less-than-php-7.1';
			$expected = '~@require-php-7.1&&~@less-than-php-5.6&&~@less-than-php-7.0';
		} elseif ( '7.1' === $php_version ) {
			$contents = '@require-php-7.0 @require-php-7.1 @require-php-7.2 @less-than-php-7.0 @less-than-php-7.1 @less-than-php-7.2';
			$expected = '~@require-php-7.2&&~@less-than-php-7.0&&~@less-than-php-7.1';
		} elseif ( '7.2' === $php_version ) {
			$contents = '@require-php-7.1 @require-php-7.2 @require-php-7.3 @less-than-php-7.1 @less-than-php-7.2 @less-than-php-7.3';
			$expected = '~@require-php-7.3&&~@less-than-php-7.1&&~@less-than-php-7.2';
		} else {
			$this->markTestSkipped( "No test for PHP_VERSION $php_version." );
		}

		file_put_contents( $this->temp_dir . '/features/php_version.feature', $contents );

		$output = exec( "cd {$this->temp_dir}; php $behat_tags" );
		$this->assertSame( '--tags=' . $expected . '&&~@github-api&&~@broken', $output );

		putenv( false === $env_github_token ? 'GITHUB_TOKEN' : "GITHUB_TOKEN=$env_github_token" );
	}

	function test_behat_tags_extension() {
		$env_github_token = getenv( 'GITHUB_TOKEN' );

		putenv( 'GITHUB_TOKEN' );

		$behat_tags = dirname( __DIR__ ) . '/ci/behat-tags.php';

		file_put_contents( $this->temp_dir . '/features/extension.feature', '@require-extension-imagick @require-extension-curl' );

		$expecteds = array();
		if ( ! extension_loaded( 'imagick' ) ) {
			$expecteds[] = '~@require-extension-imagick';
		}
		if ( ! extension_loaded( 'curl' ) ) {
			$expecteds[] = '~@require-extension-curl';
		}
		$expected = '--tags=' . implode( '&&', array_merge( array( '~@github-api', '~@broken' ), $expecteds ) );
		$output = exec( "cd {$this->temp_dir}; php $behat_tags" );
		$this->assertSame( $expected, $output );

		putenv( false === $env_github_token ? 'GITHUB_TOKEN' : "GITHUB_TOKEN=$env_github_token" );
	}
}
