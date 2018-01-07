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
	 * @dataProvider data_behat_tags_wp_version
	 */
	function test_behat_tags_wp_version( $env, $expected ) {
		$behat_tags = dirname( __DIR__ ) . '/ci/behat-tags.php';

		$contents = '@require-wp-4.6 @require-wp-4.8 @require-wp-4.9 @less-than-wp-4.6 @less-than-wp-4.8 @less-than-wp-4.9';
		file_put_contents( $this->temp_dir . '/features/wp_version.feature', $contents );

		$output = exec( "cd {$this->temp_dir}; $env php $behat_tags" );
		$this->assertSame( $expected, $output );
	}

	function data_behat_tags_wp_version() {
		return array(
			array( 'WP_VERSION=4.5', '--tags=~@require-wp-4.6&&~@require-wp-4.8&&~@require-wp-4.9&&~@github-api&&~@broken' ),
			array( 'WP_VERSION=4.6', '--tags=~@require-wp-4.8&&~@require-wp-4.9&&~@github-api&&~@broken' ),
			array( 'WP_VERSION=4.7', '--tags=~@require-wp-4.8&&~@require-wp-4.9&&~@less-than-wp-4.6&&~@github-api&&~@broken' ),
			array( 'WP_VERSION=4.8', '--tags=~@require-wp-4.9&&~@less-than-wp-4.6&&~@github-api&&~@broken' ),
			array( 'WP_VERSION=4.9', '--tags=~@less-than-wp-4.6&&~@less-than-wp-4.8&&~@github-api&&~@broken' ),
			array( 'WP_VERSION=5.0', '--tags=~@less-than-wp-4.6&&~@less-than-wp-4.8&&~@less-than-wp-4.9&&~@github-api&&~@broken' ),
			array( 'WP_VERSION=latest', '--tags=~@less-than-wp-4.6&&~@less-than-wp-4.8&&~@less-than-wp-4.9&&~@github-api&&~@broken' ),
			array( 'WP_VERSION=trunk', '--tags=~@less-than-wp-4.6&&~@less-than-wp-4.8&&~@less-than-wp-4.9&&~@github-api&&~@broken' ),
			array( 'WP_VERSION=nightly', '--tags=~@less-than-wp-4.6&&~@less-than-wp-4.8&&~@less-than-wp-4.9&&~@github-api&&~@broken' ),
			array( '', '--tags=~@less-than-wp-4.6&&~@less-than-wp-4.8&&~@less-than-wp-4.9&&~@github-api&&~@broken' ),
			array( 'GITHUB_TOKEN=blah', '--tags=~@less-than-wp-4.6&&~@less-than-wp-4.8&&~@less-than-wp-4.9&&~@broken' ),
		);
	}
}
