<?php

use WP_CLI\Tests\TestCase;
use WP_CLI\WpOrgApi;

require_once dirname( __DIR__ ) . '/mock-requests-transport.php';

class WpOrgApiTest extends TestCase {

	public static function data_http_request_verify() {
		return [
			'can retrieve core checksums'              => [
				'get_core_checksums',
				[ 'version' => 'trunk' ],
				[],
				'https://api.wordpress.org/core/checksums/1.0/?version=trunk&locale=en_US',
				[],
			],
			'can retrieve core checksums for a specific locale' => [
				'get_core_checksums',
				[
					'version' => '4.5',
					'locale'  => 'de_DE',
				],
				[],
				'https://api.wordpress.org/core/checksums/1.0/?version=4.5&locale=de_DE',
				[],
			],
			'can retrieve plugin checksums'            => [
				'get_plugin_checksums',
				[
					'plugin'  => 'hello-dolly',
					'version' => '1.0',
				],
				[],
				'https://downloads.wordpress.org/plugin-checksums/hello-dolly/1.0.json',
				[],
			],
			'can retrieve a core version check'        => [
				'get_core_version_check',
				[],
				[],
				'https://api.wordpress.org/core/version-check/1.7/?locale=en_US',
				[],
			],
			'can retrieve a core version check for a specific locale' => [
				'get_core_version_check',
				[ 'locale' => 'de_DE' ],
				[],
				'https://api.wordpress.org/core/version-check/1.7/?locale=de_DE',
				[],
			],
			'can retrieve a download offer for core'   => [
				'get_core_download_offer',
				[],
				[],
				'https://api.wordpress.org/core/version-check/1.7/?locale=en_US',
				[],
			],
			'can retrieve a download offer for core for a specific locale' => [
				'get_core_download_offer',
				[ 'locale' => 'de_DE' ],
				[],
				'https://api.wordpress.org/core/version-check/1.7/?locale=de_DE',
				[],
			],
			'can retrieve info for a plugin'           => [
				'get_plugin_info',
				[ 'plugin' => 'hello-dolly' ],
				[],
				'https://api.wordpress.org/plugins/info/1.2/?action=plugin_information&request%5Blocale%5D=en_US&request%5Bslug%5D=hello-dolly',
				[],
			],
			'can retrieve info for a plugin for a specific locale' => [
				'get_plugin_info',
				[
					'plugin' => 'hello-dolly',
					'locale' => 'de_DE',
				],
				[],
				'https://api.wordpress.org/plugins/info/1.2/?action=plugin_information&request%5Blocale%5D=de_DE&request%5Bslug%5D=hello-dolly',
				[],
			],
			'can retrieve info for a theme'            => [
				'get_theme_info',
				[ 'theme' => 'twentytwenty' ],
				[],
				'https://api.wordpress.org/themes/info/1.2/?action=theme_information&request%5Blocale%5D=en_US&request%5Bslug%5D=twentytwenty',
				[],
			],
			'can retrieve salts'                       => [
				'get_salts',
				[],
				[],
				'https://api.wordpress.org/secret-key/1.1/salt/',
				[],
			],
			'defaults to secure requests'              => [
				'get_salts',
				[],
				[],
				'https://api.wordpress.org/secret-key/1.1/salt/',
				[ 'verify' => true ],
			],
			'can explicitly request secure requests'   => [
				'get_salts',
				[],
				[ 'insecure' => false ],
				'https://api.wordpress.org/secret-key/1.1/salt/',
				[
					'insecure' => false,
					'verify'   => true,
				],
			],
			'can explicitly request insecure requests' => [
				'get_salts',
				[],
				[ 'insecure' => true ],
				'https://api.wordpress.org/secret-key/1.1/salt/',
				[
					'insecure' => true,
					'verify'   => false,
				],
			],
		];
	}

	/**
	 * @dataProvider data_http_request_verify()
	 */
	public function test_http_request_verify( $method, $arguments, $options, $expected_url, $expected_options ) {
		if ( isset( $options['insecure'] ) && true === $options['insecure'] ) {
			// Create temporary file to use as a bad certificate file.
			$bad_cacert_path = tempnam( sys_get_temp_dir(), 'wp-cli-badcacert-pem-' );
			file_put_contents(
				$bad_cacert_path,
				"-----BEGIN CERTIFICATE-----\nasdfasdf\n-----END CERTIFICATE-----\n"
			);

			$options = array_merge( [ 'verify' => $bad_cacert_path ], $options );
		}

		$transport_spy                 = new Mock_Requests_Transport();
		$options['transport']          = $transport_spy;
		$expected_options['transport'] = $transport_spy;

		$wp_org_api = new WpOrgApi( $options );
		try {
			$wp_org_api->$method( ...array_values( $arguments ) );
		} catch ( RuntimeException $exception ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
		}

		// Undo bad CAcert hack before asserting.
		if ( isset( $bad_cacert_path ) ) {
			unlink( $bad_cacert_path );
		}

		$this->assertCount( 1, $transport_spy->requests );
		$this->assertEquals( $expected_url, $transport_spy->requests[0]['url'] );
		foreach ( $expected_options as $key => $value ) {
			$this->assertEquals( $value, $transport_spy->requests[0]['options'][ $key ] );
		}
	}
}
