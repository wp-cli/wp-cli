<?php

use WP_CLI\FileCache;
use WP_CLI\Loggers;
use WP_CLI\Tests\TestCase;
use WP_CLI\Utils;
use WP_CLI\WpHttpCacheManager;

require_once __DIR__ . '/includes/wp-filter-stub.php';
require_once __DIR__ . '/includes/prefetch-requests-transport.php';

class WpHttpCacheManagerPrefetchTest extends TestCase {

	/** @var string */
	private $cache_dir;

	/** @var FileCache */
	private $cache;

	/** @var WpHttpCacheManager */
	private $manager;

	/** @var Loggers\Execution */
	private $logger;

	/** @var \WP_CLI\Loggers\Base|null */
	private $prev_logger;

	/** @var bool */
	private static $hooked = false;

	/** @var bool Whether the http_request_options hook should hand out the canned transport. */
	private static $intercept = false;

	/** @var array<int, string> URLs the hook hands a different transport than the canned one. */
	private static $odd_transport_urls = [];

	/** @var array<int, string> URLs the hook throws for. */
	private static $throwing_urls = [];

	/** @var ReflectionProperty */
	private $runner_config;

	/** @var mixed Runner config before the test replaced it. */
	private $prev_config;

	/** @var array<int, string> Prefetch temp files that existed before the test ran. */
	private $stale_temp_files = [];

	public function set_up(): void {
		parent::set_up();

		$this->prev_logger = WP_CLI::get_logger();
		$this->logger      = new Loggers\Execution();
		WP_CLI::set_logger( $this->logger );

		// The logger consults the runner's debug setting, which no test has configured.
		$runner              = WP_CLI::get_runner();
		$this->runner_config = new ReflectionProperty( $runner, 'config' );
		if ( PHP_VERSION_ID < 80100 ) {
			// @phpstan-ignore method.deprecated
			$this->runner_config->setAccessible( true );
		}
		$this->prev_config = $this->runner_config->getValue( $runner );
		$this->runner_config->setValue( $runner, [ 'debug' => false ] );

		$this->cache_dir = Utils\get_temp_dir() . uniqid( 'wp-cli-test-prefetch', true );
		$this->cache     = new FileCache( $this->cache_dir, 600, 1024 * 1024 );
		$this->manager   = new WpHttpCacheManager( $this->cache );

		Prefetch_Requests_Transport::$body    = null;
		Prefetch_Requests_Transport::$batches = [];
		$this->stale_temp_files               = $this->temp_files();
		self::$intercept                      = true;
		self::$odd_transport_urls             = [];
		self::$throwing_urls                  = [];

		// Hooks cannot be removed, so the one registered here stays out of the way outside these tests.
		if ( ! self::$hooked ) {
			WP_CLI::add_hook(
				'http_request_options',
				static function ( $options, $method, $url ) {
					if ( ! self::$intercept ) {
						return $options;
					}
					if ( in_array( $url, self::$throwing_urls, true ) ) {
						throw new RuntimeException( "Hook failure for {$url}." );
					}
					$options['transport'] = in_array( $url, self::$odd_transport_urls, true )
						? 'Some_Other_Transport'
						: new Prefetch_Requests_Transport();
					return $options;
				}
			);
			self::$hooked = true;
		}
	}

	public function tear_down(): void {
		self::$intercept = false;
		$this->remove_dir( $this->cache_dir );
		$this->runner_config->setValue( WP_CLI::get_runner(), $this->prev_config );
		if ( $this->prev_logger ) {
			WP_CLI::set_logger( $this->prev_logger );
		}
		parent::tear_down();
	}

	private function remove_dir( string $dir ): void {
		if ( ! is_dir( $dir ) ) {
			return;
		}
		foreach ( (array) scandir( $dir ) as $entry ) {
			if ( '.' === $entry || '..' === $entry ) {
				continue;
			}
			$path = $dir . '/' . $entry;
			if ( is_dir( $path ) ) {
				$this->remove_dir( $path );
			} else {
				unlink( $path );
			}
		}
		rmdir( $dir );
	}

	/**
	 * Temp files a prefetch would leave behind if it did not clean up after itself.
	 *
	 * @return array<int, string>
	 */
	private function temp_files(): array {
		return array_values( array_filter( (array) glob( Utils\get_temp_dir() . 'wp-cli-prefetch-*' ), 'is_string' ) );
	}

	private function assert_no_temp_files_left(): void {
		$this->assertSame( $this->stale_temp_files, $this->temp_files() );
	}

	/**
	 * A small but well-formed zip archive (one empty file), so the download validator accepts it.
	 */
	private function zip_bytes(): string {
		$file = Utils\get_temp_dir() . uniqid( 'wp-cli-test-prefetch-zip', true );
		$zip  = new ZipArchive();
		$zip->open( $file, ZipArchive::CREATE );
		$zip->addFromString( 'plugin/plugin.php', '<?php // Plugin' );
		$zip->close();
		$bytes = (string) file_get_contents( $file );
		unlink( $file );
		return $bytes;
	}

	public function test_prefetch_ignores_urls_that_are_not_whitelisted(): void {
		$this->assertSame( 0, $this->manager->prefetch( [ 'https://example.com/a.zip', 'https://example.com/b.zip' ] ) );
		$this->assertSame( [], Prefetch_Requests_Transport::$batches );
	}

	public function test_prefetch_skips_a_single_pending_url(): void {
		$this->manager->whitelist_url( 'https://example.com/a.zip', 'plugin/a-1.0.zip' );

		$this->assertSame( 0, $this->manager->prefetch( [ 'https://example.com/a.zip' ] ) );
		$this->assertSame( [], Prefetch_Requests_Transport::$batches );
		$this->assertSame( '', $this->logger->stdout );
	}

	public function test_prefetch_skips_urls_that_are_already_cached(): void {
		$this->manager->whitelist_url( 'https://example.com/a.zip', 'plugin/a-1.0.zip' );
		$this->manager->whitelist_url( 'https://example.com/b.zip', 'plugin/b-1.0.zip' );
		$this->cache->write( 'plugin/a-1.0.zip', $this->zip_bytes() );

		// Only b.zip is pending, and one download is never prefetched.
		$this->assertSame( 0, $this->manager->prefetch( [ 'https://example.com/a.zip', 'https://example.com/b.zip' ] ) );
		$this->assertSame( [], Prefetch_Requests_Transport::$batches );
	}

	public function test_prefetch_caches_valid_downloads(): void {
		Prefetch_Requests_Transport::$body = $this->zip_bytes();
		$this->manager->whitelist_url( 'https://example.com/a.zip', 'plugin/a-1.0.zip' );
		$this->manager->whitelist_url( 'https://example.com/b.zip', 'plugin/b-1.0.zip' );

		$this->assertSame( 2, $this->manager->prefetch( [ 'https://example.com/a.zip', 'https://example.com/b.zip' ] ) );
		$this->assertSame( [ [ 'https://example.com/a.zip', 'https://example.com/b.zip' ] ], Prefetch_Requests_Transport::$batches );
		$this->assertNotFalse( $this->cache->has( 'plugin/a-1.0.zip' ) );
		$this->assertNotFalse( $this->cache->has( 'plugin/b-1.0.zip' ) );
		$this->assertStringContainsString( 'Downloading 2 packages...', $this->logger->stdout );
		$this->assert_no_temp_files_left();
	}

	public function test_prefetch_respects_the_concurrency_limit(): void {
		Prefetch_Requests_Transport::$body = $this->zip_bytes();
		foreach ( [ 'a', 'b', 'c' ] as $name ) {
			$this->manager->whitelist_url( "https://example.com/{$name}.zip", "plugin/{$name}-1.0.zip" );
		}

		$this->assertSame( 3, $this->manager->prefetch( [ 'https://example.com/a.zip', 'https://example.com/b.zip', 'https://example.com/c.zip' ], 2 ) );
		$this->assertCount( 2, Prefetch_Requests_Transport::$batches );
		$this->assertCount( 2, Prefetch_Requests_Transport::$batches[0] );
		$this->assertCount( 1, Prefetch_Requests_Transport::$batches[1] );
	}

	public function test_prefetch_leaves_invalid_downloads_out_of_the_cache(): void {
		Prefetch_Requests_Transport::$body = '<html>Not a zip archive</html>';
		$this->manager->whitelist_url( 'https://example.com/a.zip', 'plugin/a-1.0.zip' );
		$this->manager->whitelist_url( 'https://example.com/b.zip', 'plugin/b-1.0.zip' );

		$this->assertSame( 0, $this->manager->prefetch( [ 'https://example.com/a.zip', 'https://example.com/b.zip' ] ) );
		$this->assertFalse( $this->cache->has( 'plugin/a-1.0.zip' ) );
		$this->assertFalse( $this->cache->has( 'plugin/b-1.0.zip' ) );
		$this->assert_no_temp_files_left();
	}

	public function test_prefetch_survives_a_transport_failure(): void {
		$this->manager->whitelist_url( 'https://example.com/a.zip', 'plugin/a-1.0.zip' );
		$this->manager->whitelist_url( 'https://example.com/b.zip', 'plugin/b-1.0.zip' );

		$this->assertSame( 0, $this->manager->prefetch( [ 'https://example.com/a.zip', 'https://example.com/b.zip' ] ) );
		$this->assertCount( 1, Prefetch_Requests_Transport::$batches );
		$this->assertFalse( $this->cache->has( 'plugin/a-1.0.zip' ) );
		$this->assert_no_temp_files_left();
	}

	public function test_prefetch_leaves_a_url_with_a_different_transport_to_the_upgrader(): void {
		Prefetch_Requests_Transport::$body = $this->zip_bytes();
		self::$odd_transport_urls          = [ 'https://example.com/b.zip' ];
		$this->manager->whitelist_url( 'https://example.com/a.zip', 'plugin/a-1.0.zip' );
		$this->manager->whitelist_url( 'https://example.com/b.zip', 'plugin/b-1.0.zip' );

		// Requests takes one transport per batch, so b.zip is skipped rather than sent through a.zip's.
		$this->assertSame( 1, $this->manager->prefetch( [ 'https://example.com/a.zip', 'https://example.com/b.zip' ] ) );
		$this->assertSame( [ [ 'https://example.com/a.zip' ] ], Prefetch_Requests_Transport::$batches );
		$this->assertNotFalse( $this->cache->has( 'plugin/a-1.0.zip' ) );
		$this->assertFalse( $this->cache->has( 'plugin/b-1.0.zip' ) );
		$this->assert_no_temp_files_left();
	}

	public function test_prefetch_removes_its_temp_files_when_the_options_hook_throws(): void {
		Prefetch_Requests_Transport::$body = $this->zip_bytes();
		self::$throwing_urls               = [ 'https://example.com/b.zip' ];
		$this->manager->whitelist_url( 'https://example.com/a.zip', 'plugin/a-1.0.zip' );
		$this->manager->whitelist_url( 'https://example.com/b.zip', 'plugin/b-1.0.zip' );

		try {
			$this->manager->prefetch( [ 'https://example.com/a.zip', 'https://example.com/b.zip' ] );
			$this->fail( 'The hook exception should propagate.' );
		} catch ( RuntimeException $exception ) {
			$this->assertSame( 'Hook failure for https://example.com/b.zip.', $exception->getMessage() );
		}

		// a.zip's temp file was created before the hook threw for b.zip.
		$this->assertSame( [], Prefetch_Requests_Transport::$batches );
		$this->assertFalse( $this->cache->has( 'plugin/a-1.0.zip' ) );
		$this->assert_no_temp_files_left();
	}

	public function test_prefetch_does_nothing_when_the_cache_is_disabled(): void {
		$unusable = Utils\get_temp_dir() . uniqid( 'wp-cli-test-prefetch-file', true );
		touch( $unusable );
		$cache   = new FileCache( $unusable . '/cache', 600, 1024 * 1024 );
		$manager = new WpHttpCacheManager( $cache );
		$manager->whitelist_url( 'https://example.com/a.zip', 'plugin/a-1.0.zip' );
		$manager->whitelist_url( 'https://example.com/b.zip', 'plugin/b-1.0.zip' );

		$this->assertFalse( $cache->is_enabled() );
		$this->assertSame( 0, $manager->prefetch( [ 'https://example.com/a.zip', 'https://example.com/b.zip' ] ) );
		$this->assertSame( [], Prefetch_Requests_Transport::$batches );
		unlink( $unusable );
	}
}
