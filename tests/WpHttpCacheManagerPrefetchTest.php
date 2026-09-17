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

	/** @var array<int, string> Prefetch temp files that existed before the test ran. */
	private $stale_temp_files = [];

	public function set_up(): void {
		parent::set_up();

		$this->prev_logger = WP_CLI::get_logger();
		$this->logger      = new Loggers\Execution();
		WP_CLI::set_logger( $this->logger );

		// The logger consults the runner's debug setting, which no test has configured.
		$runner        = WP_CLI::get_runner();
		$runner_config = new ReflectionProperty( $runner, 'config' );
		if ( PHP_VERSION_ID < 80100 ) {
			// @phpstan-ignore method.deprecated
			$runner_config->setAccessible( true );
		}
		$runner_config->setValue( $runner, [ 'debug' => false ] );

		$this->cache_dir = Utils\get_temp_dir() . uniqid( 'wp-cli-test-prefetch', true );
		$this->cache     = new FileCache( $this->cache_dir, 600, 1024 * 1024 );
		$this->manager   = new WpHttpCacheManager( $this->cache );

		Prefetch_Requests_Transport::$body    = null;
		Prefetch_Requests_Transport::$batches = [];
		$this->stale_temp_files               = $this->temp_files();

		if ( ! self::$hooked ) {
			WP_CLI::add_hook(
				'http_request_options',
				static function ( $options ) {
					$options['transport'] = new Prefetch_Requests_Transport();
					return $options;
				}
			);
			self::$hooked = true;
		}
	}

	public function tear_down(): void {
		$this->remove_dir( $this->cache_dir );
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
