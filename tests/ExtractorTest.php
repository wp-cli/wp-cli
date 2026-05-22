<?php

use WP_CLI\Extractor;
use WP_CLI\Loggers;
use WP_CLI\Tests\TestCase;
use WP_CLI\Utils;

class ExtractorTest extends TestCase {

	public static $copy_overwrite_files_prefix = 'wp-cli-test-utils-copy-overwrite-files-';

	public static $expected_wp = [
		'index1.php',
		'license2.php',
		'wp-admin/',
		'wp-admin/about3.php',
		'wp-admin/includes/',
		'wp-admin/includes/file4.php',
		'wp-admin/widgets5.php',
		'wp-config6.php',
		'wp-includes/',
		'wp-includes/file7.php',
		'xmlrpc8.php',
	];

	public static $logger      = null;
	public static $prev_logger = null;

	public function set_up(): void {
		parent::set_up();

		self::$prev_logger = WP_CLI::get_logger();

		self::$logger = new Loggers\Execution();
		WP_CLI::set_logger( self::$logger );

		// Remove any failed tests detritus.
		$temp_dirs = glob( Utils\get_temp_dir() . self::$copy_overwrite_files_prefix . '*' );

		$this->assertNotFalse( $temp_dirs );

		foreach ( $temp_dirs as $temp_dir ) {
			Extractor::rmdir( $temp_dir );
		}
	}

	public function tear_down(): void {
		// Restore logger.
		WP_CLI::set_logger( self::$prev_logger );

		parent::tear_down();
	}

	public function test_rmdir(): void {
		list( $temp_dir, $src_dir, $wp_dir ) = self::create_test_directory_structure();

		$this->assertTrue( is_dir( $wp_dir ) );
		Extractor::rmdir( $wp_dir );
		$this->assertFalse( file_exists( $wp_dir ) );

		$this->assertTrue( is_dir( $temp_dir ) );
		Extractor::rmdir( $temp_dir );
		$this->assertFalse( file_exists( $temp_dir ) );
	}

	public function test_err_rmdir(): void {
		$msg = '';
		try {
			Extractor::rmdir( 'no-such-dir' );
		} catch ( \Exception $e ) {
			$msg = $e->getMessage();
		}
		$this->assertTrue( false !== strpos( $msg, 'no-such-dir' ) );
		$this->assertTrue( empty( self::$logger->stderr ) );
	}

	public function test_copy_overwrite_files(): void {
		list( $temp_dir, $src_dir, $wp_dir ) = self::create_test_directory_structure();

		$dest_dir = $temp_dir . '/dest';

		Extractor::copy_overwrite_files( $wp_dir, $dest_dir );

		$files = self::recursive_scandir( $dest_dir );

		$this->assertSame( self::$expected_wp, $files );
		$this->assertTrue( empty( self::$logger->stderr ) );

		// Clean up.
		Extractor::rmdir( $temp_dir );
	}

	public function test_err_copy_overwrite_files(): void {
		$msg = '';
		try {
			Extractor::copy_overwrite_files( 'no-such-dir', 'dest-dir' );
		} catch ( \Exception $e ) {
			$msg = $e->getMessage();
		}
		$this->assertTrue( false !== strpos( $msg, 'no-such-dir' ) );
		$this->assertTrue( empty( self::$logger->stderr ) );
	}

	public function test_extract_tarball(): void {
		if ( ! exec( 'tar --version' ) ) {
			$this->markTestSkipped( 'tar not installed.' );
		}

		list( $temp_dir, $src_dir, $wp_dir ) = self::create_test_directory_structure();

		$tarball  = $temp_dir . '/test.tar.gz';
		$dest_dir = $temp_dir . '/dest';

		// Create test tarball.
		$output     = [];
		$return_var = -1;
		// Need --force-local for Windows to avoid "C:" being interpreted as being on remote machine, and redirect for Mac as outputs verbosely on STDERR.
		$cmd = 'tar czvf %1$s' . ( Utils\is_windows() ? ' --force-local' : '' ) . ' --directory=%2$s/src wordpress 2>&1';
		exec( Utils\esc_cmd( $cmd, $tarball, $temp_dir ), $output, $return_var );
		$this->assertSame( 0, $return_var );
		$this->assertFalse( empty( $output ) );

		// Normalize (Mac) output.
		$normalize = function ( $v ) {
			if ( 'a ' === substr( $v, 0, 2 ) ) {
				$v = substr( $v, 2 );
			}
			if ( '/' !== substr( $v, -1 ) && false === strpos( $v, '.' ) ) {
				$v .= '/';
			}
			return $v;
		};
		$output    = array_filter(
			$output,
			function ( $v ) {
				return 0 !== strpos( basename( $v ), '._' );
			}
		);
		$output    = array_map( $normalize, $output );
		sort( $output );

		$this->assertSame( self::recursive_scandir( $src_dir ), $output );

		// Test.
		Extractor::extract( $tarball, $dest_dir );

		$files = self::recursive_scandir( $dest_dir );
		$this->assertSame( self::$expected_wp, $files );
		$this->assertTrue( empty( self::$logger->stderr ) );

		// Clean up.
		Extractor::rmdir( $temp_dir );
	}

	public function test_err_extract_tarball(): void {
		// Non-existent.
		$msg = '';
		try {
			Extractor::extract( 'no-such-tar.tar.gz', 'dest-dir' );
		} catch ( \Exception $e ) {
			$msg = $e->getMessage();
		}

		$this->assertTrue( false !== strpos( $msg, 'no-such-tar' ) );
		$this->assertTrue( empty( self::$logger->stderr ) );

		// Reset logger.
		self::$logger->stderr = '';
		self::$logger->stdout = '';

		// Zero-length.
		$zero_tar = Utils\get_temp_dir() . 'zero-tar.tar.gz';
		touch( $zero_tar );
		$msg = '';
		try {
			Extractor::extract( $zero_tar, 'dest-dir' );
		} catch ( \Exception $e ) {
			$msg = $e->getMessage();
		}
		unlink( $zero_tar );

		$this->assertTrue( false !== strpos( $msg, 'zero-tar' ) );
		$this->assertTrue( empty( self::$logger->stderr ) );
	}

	public function test_extract_tarball_long_path(): void {
		if ( ! exec( 'tar --version' ) ) {
			$this->markTestSkipped( 'tar not installed.' );
		}

		$long_path                             = 'wp-includes/php-ai-client/third-party/Http/Discovery/Exception/PuliUnavailableException.php';
		list( $temp_dir, $tarball, $dest_dir ) = self::create_test_tarball( [ $long_path ] );

		Extractor::extract( $tarball, $dest_dir );

		$this->assertFileExists( $dest_dir . '/' . $long_path );
		$this->assertTrue( empty( self::$logger->stderr ) );

		Extractor::rmdir( $temp_dir );
	}

	public function test_extract_tarball_with_phar_data(): void {
		if ( ! class_exists( 'PharData' ) ) {
			$this->markTestSkipped( 'PharData not installed.' );
		}

		list( $temp_dir, $tarball, $dest_dir ) = self::create_test_tarball();

		$reflection = new ReflectionClass( Extractor::class );
		$method     = $reflection->getMethod( 'extract_tarball_with_phar_data' );
		$method->setAccessible( true );
		$method->invoke( null, $tarball, $dest_dir );

		$files = self::recursive_scandir( $dest_dir );
		$this->assertSame( self::$expected_wp, $files );
		$this->assertTrue( empty( self::$logger->stderr ) );

		Extractor::rmdir( $temp_dir );
	}

	public function test_extract_tarball_with_phar_data_when_tar_unavailable(): void {
		if ( ! class_exists( 'PharData' ) ) {
			$this->markTestSkipped( 'PharData not installed.' );
		}

		if ( Utils\is_windows() ) {
			$this->markTestSkipped( 'PATH manipulation is not reliable on Windows.' );
		}

		list( $temp_dir, $tarball, $dest_dir ) = self::create_test_tarball();

		$old_path = getenv( 'PATH' );
		putenv( 'PATH=' );

		try {
			Extractor::extract( $tarball, $dest_dir );

			$files = self::recursive_scandir( $dest_dir );
			$this->assertSame( self::$expected_wp, $files );
			$this->assertTrue( empty( self::$logger->stderr ) );
		} finally {
			putenv( 'PATH=' . $old_path );
		}

		Extractor::rmdir( $temp_dir );
	}

	public function test_extract_tarball_falls_back_to_phar_data_when_tar_fails(): void {
		if ( ! class_exists( 'PharData' ) ) {
			$this->markTestSkipped( 'PharData not installed.' );
		}

		if ( Utils\is_windows() ) {
			$this->markTestSkipped( 'Fake tar binary test is not supported on Windows.' );
		}

		if ( ! exec( 'tar --version' ) ) {
			$this->markTestSkipped( 'tar not installed.' );
		}

		list( $temp_dir, $tarball, $dest_dir ) = self::create_test_tarball();

		$fake_bin = $temp_dir . '/fakebin';
		mkdir( $fake_bin );
		file_put_contents(
			"{$fake_bin}/tar",
			"#!/bin/sh\nif [ \"\$1\" = \"--version\" ]; then exit 0; fi\nexit 1\n"
		);
		chmod( "{$fake_bin}/tar", 0755 );

		$old_path = getenv( 'PATH' );
		putenv( 'PATH=' . $fake_bin . PATH_SEPARATOR . $old_path );

		try {
			Extractor::extract( $tarball, $dest_dir );

			$files = self::recursive_scandir( $dest_dir );
			$this->assertSame( self::$expected_wp, $files );
			$this->assertTrue( 0 === strpos( self::$logger->stderr, 'Warning: Failed to extract with \'tar xz\'' ) );
		} finally {
			putenv( 'PATH=' . $old_path );
		}

		Extractor::rmdir( $temp_dir );
	}

	public function test_err_extract_tarball_with_system_tar(): void {
		if ( ! exec( 'tar --version' ) ) {
			$this->markTestSkipped( 'tar not installed.' );
		}

		$temp_dir = Utils\get_temp_dir() . uniqid( self::$copy_overwrite_files_prefix, true );
		mkdir( $temp_dir );

		$dest_dir = $temp_dir . '/dest';
		mkdir( $dest_dir );

		$corrupt_tarball = $temp_dir . '/corrupt.tar.gz';
		file_put_contents( $corrupt_tarball, 'invalid gzip archive' );

		$reflection = new ReflectionClass( Extractor::class );
		$method     = $reflection->getMethod( 'extract_tarball_with_system_tar' );
		$method->setAccessible( true );

		$msg = '';
		try {
			$method->invoke( null, realpath( $corrupt_tarball ), $dest_dir );
		} catch ( \Exception $e ) {
			$msg = $e->getMessage();
		}

		$this->assertTrue( false !== strpos( $msg, 'Failed to execute' ) );
		$this->assertTrue( empty( self::$logger->stderr ) );

		Extractor::rmdir( $temp_dir );
	}

	public function test_extract_zip(): void {
		if ( ! class_exists( 'ZipArchive' ) ) {
			$this->markTestSkipped( 'ZipArchive not installed.' );
		}

		list( $temp_dir, $src_dir, $wp_dir ) = self::create_test_directory_structure();

		$zipfile  = $temp_dir . '/test.zip';
		$dest_dir = $temp_dir . '/dest';

		// Create test zip.
		$zip    = new ZipArchive();
		$result = $zip->open( $zipfile, ZipArchive::CREATE );
		$this->assertTrue( $result );
		$files = self::recursive_scandir( $src_dir );
		foreach ( $files as $file ) {
			if ( 0 === substr_compare( $file, '/', -1 ) ) {
				$result = $zip->addEmptyDir( $file );
			} else {
				$result = $zip->addFile( $src_dir . '/' . $file, $file );
			}
			$this->assertTrue( $result );
		}
		$result = $zip->close();
		$this->assertTrue( $result );

		// Test.
		Extractor::extract( $zipfile, $dest_dir );

		$files = self::recursive_scandir( $dest_dir );
		$this->assertSame( self::$expected_wp, $files );
		$this->assertTrue( empty( self::$logger->stderr ) );

		// Clean up.
		Extractor::rmdir( $temp_dir );
	}

	public function test_err_extract_zip(): void {
		if ( ! class_exists( 'ZipArchive' ) ) {
			$this->markTestSkipped( 'ZipArchive not installed.' );
		}

		// Non-existent.
		$msg = '';
		try {
			Extractor::extract( 'no-such-zip.zip', 'dest-dir' );
		} catch ( \Exception $e ) {
			$msg = $e->getMessage();
		}
		$this->assertTrue( false !== strpos( $msg, 'no-such-zip' ) );
		$this->assertTrue( empty( self::$logger->stderr ) );

		// Reset logger.
		self::$logger->stderr = '';
		self::$logger->stdout = '';

		// Zero-length.
		$zero_zip = Utils\get_temp_dir() . 'zero-zip.zip';
		touch( $zero_zip );
		$msg = '';
		try {
			Extractor::extract( $zero_zip, 'dest-dir' );
		} catch ( \Exception $e ) {
			$msg = $e->getMessage();
		}
		unlink( $zero_zip );
		$this->assertTrue( false !== strpos( $msg, 'zero-zip' ) );
		$this->assertTrue( empty( self::$logger->stderr ) );
	}

	public function test_err_extract(): void {
		$msg = '';
		try {
			Extractor::extract( 'not-supported.tar.xz', 'dest-dir' );
		} catch ( \Exception $e ) {
			$msg = $e->getMessage();
		}
		$this->assertSame( "Extraction only supported for '.zip' and '.tar.gz' file types.", $msg );
		$this->assertTrue( empty( self::$logger->stderr ) );
	}

	private static function create_test_tarball( $extra_files = [] ) {
		$temp_dir = Utils\get_temp_dir() . uniqid( self::$copy_overwrite_files_prefix, true );
		mkdir( $temp_dir );

		$src_dir = $temp_dir . '/src';
		mkdir( $src_dir );

		$wp_dir = $src_dir . '/wordpress';
		mkdir( $wp_dir );

		foreach ( self::$expected_wp as $file ) {
			if ( 0 === substr_compare( $file, '/', -1 ) ) {
				mkdir( $wp_dir . '/' . $file );
			} else {
				touch( $wp_dir . '/' . $file );
			}
		}

		foreach ( $extra_files as $relative_path ) {
			$full_path = $wp_dir . '/' . $relative_path;
			$dir       = dirname( $full_path );
			if ( ! is_dir( $dir ) ) {
				mkdir( $dir, 0777, true );
			}
			touch( $full_path );
		}

		$tarball  = $temp_dir . '/test.tar.gz';
		$dest_dir = $temp_dir . '/dest';

		$output     = [];
		$return_var = -1;
		$cmd        = 'tar czvf %1$s' . ( Utils\is_windows() ? ' --force-local' : '' ) . ' --directory=%2$s/src wordpress 2>&1';
		exec( Utils\esc_cmd( $cmd, $tarball, $temp_dir ), $output, $return_var );

		if ( 0 !== $return_var ) {
			throw new \RuntimeException( 'Failed to create test tarball.' );
		}

		return [ $temp_dir, $tarball, $dest_dir ];
	}

	private static function create_test_directory_structure() {
		$temp_dir = Utils\get_temp_dir() . uniqid( self::$copy_overwrite_files_prefix, true );
		mkdir( $temp_dir );

		$src_dir = $temp_dir . '/src';
		mkdir( $src_dir );

		$wp_dir = $src_dir . '/wordpress';
		mkdir( $wp_dir );

		foreach ( self::$expected_wp as $file ) {
			if ( 0 === substr_compare( $file, '/', -1 ) ) {
				mkdir( $wp_dir . '/' . $file );
			} else {
				touch( $wp_dir . '/' . $file );
			}
		}

		return [ $temp_dir, $src_dir, $wp_dir ];
	}

	private static function recursive_scandir( $dir, $prefix_dir = '' ) {
		$dirs = scandir( $dir );
		if ( ! $dirs ) {
			return [];
		}

		$ret = [];

		foreach ( array_diff( $dirs, [ '.', '..' ] ) as $file ) {
			if ( 0 === strpos( $file, '._' ) ) {
				continue;
			}

			if ( is_dir( $dir . '/' . $file ) ) {
				$ret[] = ( $prefix_dir ? ( $prefix_dir . '/' . $file ) : $file ) . '/';
				$ret   = array_merge( $ret, self::recursive_scandir( $dir . '/' . $file, $prefix_dir ? ( $prefix_dir . '/' . $file ) : $file ) );
			} else {
				$ret[] = $prefix_dir ? ( $prefix_dir . '/' . $file ) : $file;
			}
		}
		return $ret;
	}
}
