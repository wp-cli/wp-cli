<?php

use WP_CLI\FileCache;
use WP_CLI\Tests\TestCase;
use WP_CLI\Utils;
use WP_CLI\WpOrgApi;
use WpOrg\Requests\Transport;
use PHPUnit\Framework\Attributes\DataProvider;

class WpOrgApiTest extends TestCase {
	public static function data_http_request_verify(): array {
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
	 * @dataProvider data_http_request_verify
	 * @param string $method
	 * @param array<int|string, mixed> $arguments
	 * @param array<string, mixed> $options
	 * @param string $expected_url
	 * @param array<string, mixed> $expected_options
	 */
	#[DataProvider( 'data_http_request_verify' )] // phpcs:ignore PHPCompatibility.Attributes.NewAttributes.PHPUnitAttributeFound
	public function test_http_request_verify( $method, $arguments, $options, $expected_url, $expected_options ): void {
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

		$wp_org_api = new WpOrgApi( $options, false );
		try {
			$wp_org_api->$method( ...array_values( $arguments ) );
		} catch ( RuntimeException $exception ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
		}

		// Undo bad CAcert hack before asserting.
		if ( isset( $bad_cacert_path ) ) {
			unlink( $bad_cacert_path );
		}

		$this->assertCount( 1, $transport_spy->requests );
		$this->assertSame( $expected_url, $transport_spy->requests[0]['url'] );
		foreach ( $expected_options as $key => $value ) {
			$this->assertEquals( $value, $transport_spy->requests[0]['options'][ $key ] );
		}
	}

	/**
	 * Creates a transport that responds to every request with the given JSON body.
	 *
	 * @param string $body Response body.
	 * @return Transport&object{requests: int}
	 */
	private function get_json_transport( $body ) {
		return new class( $body ) implements Transport {
			/** @var int */
			public $requests = 0;

			/** @var string */
			private $body;

			/**
			 * @param string $body
			 */
			public function __construct( $body ) {
				$this->body = $body;
			}

			/**
			 * @param string              $url
			 * @param array<mixed>        $headers
			 * @param array<mixed>|string $data
			 * @param array<mixed>        $options
			 * @return string
			 */
			public function request( $url, $headers = [], $data = [], $options = [] ) {
				++$this->requests;
				return "HTTP/1.1 200 OK\r\nContent-Type: application/json\r\n\r\n" . $this->body;
			}

			/**
			 * @param array<mixed> $requests
			 * @param array<mixed> $options
			 * @return array<mixed>
			 */
			public function request_multiple( $requests, $options ) {
				throw new Exception( 'Method not implemented: ' . __METHOD__ );
			}

			public static function test( $capabilities = [] ) {
				return true;
			}
		};
	}

	/**
	 * @return FileCache
	 */
	private function get_temp_cache() {
		return new FileCache( Utils\get_temp_dir() . uniqid( 'wp-cli-test-wp-org-api', true ), 3600, 1024 * 1024 );
	}

	public function test_caches_core_checksums_of_a_release(): void {
		$transport = $this->get_json_transport( '{"checksums":{"wp-load.php":"abc"}}' );
		$cache     = $this->get_temp_cache();

		$this->assertSame( [ 'wp-load.php' => 'abc' ], ( new WpOrgApi( [ 'transport' => $transport ], $cache ) )->get_core_checksums( '7.1.2', 'de_DE' ) );
		$this->assertSame( [ 'wp-load.php' => 'abc' ], ( new WpOrgApi( [ 'transport' => $transport ], $cache ) )->get_core_checksums( '7.1.2', 'de_DE' ) );
		$this->assertSame( 1, $transport->requests );

		// Other versions and locales are requested separately.
		( new WpOrgApi( [ 'transport' => $transport ], $cache ) )->get_core_checksums( '7.1.2', 'en_US' );
		( new WpOrgApi( [ 'transport' => $transport ], $cache ) )->get_core_checksums( '7.1-RC1', 'de_DE' );
		$this->assertSame( 3, $transport->requests );
	}

	public function test_does_not_cache_core_checksums_of_a_nightly_build(): void {
		$transport = $this->get_json_transport( '{"checksums":{"wp-load.php":"abc"}}' );
		$cache     = $this->get_temp_cache();

		( new WpOrgApi( [ 'transport' => $transport ], $cache ) )->get_core_checksums( '7.2-alpha-61000' );
		( new WpOrgApi( [ 'transport' => $transport ], $cache ) )->get_core_checksums( '7.2-alpha-61000' );
		$this->assertSame( 2, $transport->requests );
	}

	public function test_does_not_cache_missing_core_checksums(): void {
		$transport = $this->get_json_transport( '{"checksums":false}' );
		$cache     = $this->get_temp_cache();

		$this->assertFalse( ( new WpOrgApi( [ 'transport' => $transport ], $cache ) )->get_core_checksums( '99.0' ) );
		$this->assertFalse( ( new WpOrgApi( [ 'transport' => $transport ], $cache ) )->get_core_checksums( '99.0' ) );
		$this->assertSame( 2, $transport->requests );
	}

	public function test_does_not_cache_core_checksums_if_caching_is_disabled(): void {
		$transport = $this->get_json_transport( '{"checksums":{"wp-load.php":"abc"}}' );

		( new WpOrgApi( [ 'transport' => $transport ], false ) )->get_core_checksums( '7.1.2' );
		( new WpOrgApi( [ 'transport' => $transport ], false ) )->get_core_checksums( '7.1.2' );
		$this->assertSame( 2, $transport->requests );
	}

	public function test_does_not_cache_core_checksums_fetched_with_insecure(): void {
		$transport = $this->get_json_transport( '{"checksums":{"wp-load.php":"abc"}}' );
		$cache     = $this->get_temp_cache();

		$insecure_options = [
			'transport' => $transport,
			'insecure'  => true,
		];

		( new WpOrgApi( $insecure_options, $cache ) )->get_core_checksums( '7.1.2' );
		( new WpOrgApi( [ 'transport' => $transport ], $cache ) )->get_core_checksums( '7.1.2' );
		$this->assertSame( 2, $transport->requests );
	}

	public function test_ignores_cached_core_checksums_with_an_unexpected_structure(): void {
		$transport = $this->get_json_transport( '{"checksums":{"wp-load.php":"abc"}}' );
		$cache     = $this->get_temp_cache();
		$cache->write( 'core/checksums-7.1.2-en_US.json', '{"checksums":{"wp-load.php":"abc"}}' );

		$this->assertSame( [ 'wp-load.php' => 'abc' ], ( new WpOrgApi( [ 'transport' => $transport ], $cache ) )->get_core_checksums( '7.1.2' ) );
		$this->assertSame( 1, $transport->requests );
	}
}
