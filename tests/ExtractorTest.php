<?php

use PHPUnit\Framework\Attributes\DataProvider;
use WP_CLI\Extractor;
use WP_CLI\Loggers;
use WP_CLI\Tests\TestCase;
use WP_CLI\Utils;

class ExtractorTest extends TestCase {

	/**
	 * @var string
	 */
	public static $copy_overwrite_files_prefix = 'wp-cli-test-utils-copy-overwrite-files-';

	/**
	 * @var array<string>
	 */
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

	/**
	 * @var Loggers\Execution
	 */
	public static $logger;

	/**
	 * @var Loggers\Base
	 */
	public static $prev_logger;

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
		$caught = null;
		try {
			Extractor::rmdir( 'no-such-dir' );
		} catch ( \Exception $e ) {
			$caught = $e;
		}
		// Assert the type, not the message: PHP 8.6 dropped the path argument
		// from the RecursiveDirectoryIterator::__construct() exception message.
		$this->assertInstanceOf( \UnexpectedValueException::class, $caught );
		$this->assertEmpty( self::$logger->stderr );
	}

	public function test_copy_overwrite_files(): void {
		list( $temp_dir, $src_dir, $wp_dir ) = self::create_test_directory_structure();

		$dest_dir = $temp_dir . '/dest';

		Extractor::copy_overwrite_files( $wp_dir, $dest_dir );

		$files = self::recursive_scandir( $dest_dir );

		$this->assertSame( self::$expected_wp, $files );
		$this->assertEmpty( self::$logger->stderr );

		// Clean up.
		Extractor::rmdir( $temp_dir );
	}

	public function test_err_copy_overwrite_files(): void {
		$caught = null;
		try {
			Extractor::copy_overwrite_files( 'no-such-dir', 'dest-dir' );
		} catch ( \Exception $e ) {
			$caught = $e;
		}
		// Assert the type, not the message: PHP 8.6 dropped the path argument
		// from the RecursiveDirectoryIterator::__construct() exception message.
		$this->assertInstanceOf( \UnexpectedValueException::class, $caught );
		$this->assertEmpty( self::$logger->stderr );
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
		$this->assertEmpty( self::$logger->stderr );

		// Clean up.
		Extractor::rmdir( $temp_dir );
	}

	public function test_extract_tarball_fallback_to_phardata(): void {
		if ( Utils\is_windows() ) {
			$this->markTestSkipped( 'Hiding system tar via PATH is not supported on Windows.' );
		}

		if ( ! class_exists( 'PharData' ) ) {
			$this->markTestSkipped( 'PharData not available.' );
		}

		$msg = '';

		list( $temp_dir, $src_dir, $wp_dir ) = self::create_test_directory_structure();

		$tarball  = $temp_dir . '/test.tar.gz';
		$dest_dir = $temp_dir . '/dest';

		// Create test tarball using PharData.
		$phar = new \PharData( $tarball );
		$phar->buildFromDirectory( $src_dir );

		// Temporarily hide system 'tar' by overriding PATH.
		$prev_path     = getenv( 'PATH' );
		$prev_env_path = isset( $_ENV['PATH'] ) ? $_ENV['PATH'] : null;

		putenv( 'PATH=/does/not/exist' );
		$_ENV['PATH'] = '/does/not/exist';

		try {
			// Test.
			Extractor::extract( $tarball, $dest_dir );
		} catch ( \Throwable $e ) {
			$msg = $e->getMessage();
		} finally {
			// Restore environment.
			putenv( false === $prev_path ? 'PATH' : "PATH=$prev_path" );
			if ( null === $prev_env_path ) {
				unset( $_ENV['PATH'] );
			} else {
				$_ENV['PATH'] = $prev_env_path;
			}
		}

		$files = self::recursive_scandir( $dest_dir );
		// Clean up.
		Extractor::rmdir( $temp_dir );
		$this->assertSame( self::$expected_wp, $files );
		$this->assertStringStartsWith( 'Warning: tar xz failed, falling back to PharData', self::$logger->stderr );
		$this->assertEmpty( $msg );
	}

	public function test_err_extract_tarball(): void {
		// Non-existent.
		$msg = '';
		try {
			Extractor::extract( 'no-such-tar.tar.gz', 'dest-dir' );
		} catch ( \Exception $e ) {
			$msg = $e->getMessage();
		}

		$this->assertStringContainsString( 'no-such-tar', $msg );
		$this->assertEmpty( self::$logger->stderr );

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

		$this->assertStringContainsString( 'zero-tar', $msg );
		$this->assertEmpty( self::$logger->stderr );
	}

	public function test_extract_tarball_both_failed(): void {
		$invalid_tar = Utils\get_temp_dir() . 'invalid-tar.tar.gz';
		file_put_contents( $invalid_tar, 'invalid tar content' );

		$msg = '';
		try {
			Extractor::extract( $invalid_tar, 'dest-dir' );
		} catch ( \Exception $e ) {
			$msg = $e->getMessage();
		}
		unlink( $invalid_tar );

		$this->assertStringContainsString( 'Failed to extract the tarball.', $msg );
		$this->assertStringContainsString( 'tar xz failed:', $msg );
		if ( class_exists( 'PharData' ) ) {
			$this->assertStringContainsString( 'PharData failed:', $msg );
		} else {
			$this->assertStringNotContainsString( 'PharData failed:', $msg );
		}
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
		$this->assertEmpty( self::$logger->stderr );

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
		$this->assertStringContainsString( 'no-such-zip', $msg );
		$this->assertEmpty( self::$logger->stderr );

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
		$this->assertStringContainsString( 'zero-zip', $msg );
		$this->assertEmpty( self::$logger->stderr );
	}

	public function test_err_extract(): void {
		$msg = '';
		try {
			Extractor::extract( 'not-supported.tar.xz', 'dest-dir' );
		} catch ( \Exception $e ) {
			$msg = $e->getMessage();
		}
		$this->assertSame( "Extraction only supported for '.zip' and '.tar.gz' file types.", $msg );
		$this->assertEmpty( self::$logger->stderr );
	}

	public function test_ensure_dir_exists(): void {
		$dir        = Utils\get_temp_dir() . uniqid( 'wp-cli-test-extractor-', true );
		$test_class = new ReflectionClass( Extractor::class );
		$method     = $test_class->getMethod( 'ensure_dir_exists' );
		if ( PHP_VERSION_ID < 80100 ) {
			// @phpstan-ignore method.deprecated
			$method->setAccessible( true );
		}

		$result = $method->invoke( null, $dir );
		$this->assertTrue( $result );
		$this->assertTrue( is_dir( $dir ) );
		if ( ! Utils\is_windows() ) {
			// Assert the write bits rather than a literal mode: the exact result depends on the
			// umask, and what matters is that no other local user can write into the directory.
			$perms = fileperms( $dir ) & 0777;
			$this->assertSame(
				0,
				$perms & 0022,
				sprintf( 'Extraction directory must not be group- or world-writable, got %o.', $perms )
			);
		}

		rmdir( $dir );
	}

	public function test_get_first_subfolder(): void {
		list( $temp_dir, $src_dir, $wp_dir ) = self::create_test_directory_structure();

		$test_class = new ReflectionClass( Extractor::class );
		$method     = $test_class->getMethod( 'get_first_subfolder' );
		if ( PHP_VERSION_ID < 80100 ) {
			// @phpstan-ignore method.deprecated
			$method->setAccessible( true );
		}

		$result_with_slash    = $method->invoke( null, rtrim( $src_dir, '/\\' ) . '/' );
		$result_without_slash = $method->invoke( null, rtrim( $src_dir, '/\\' ) );

		$this->assertSame( $wp_dir, $result_with_slash );
		$this->assertSame( $wp_dir, $result_without_slash );
		$this->assertIsString( $result_with_slash );
		$this->assertStringNotContainsString( '//', $result_with_slash );

		Extractor::rmdir( $temp_dir );
	}

	/**
	 * @return array{0: string, 1: string, 2: string}
	 */
	/**
	 * @return array<string, array{bool}>
	 */
	public static function data_symlink_targets(): array {
		return [
			'relative target' => [ false ],
			'absolute target' => [ true ],
		];
	}

	/**
	 * @dataProvider data_symlink_targets
	 */
	#[DataProvider( 'data_symlink_targets' )] // phpcs:ignore PHPCompatibility.Attributes.NewAttributes.PHPUnitAttributeFound
	public function test_extract_tarball_rejects_symlinks( bool $absolute ): void {
		if ( Utils\is_windows() ) {
			$this->markTestSkipped( 'Creating symbolic links is not reliably supported on Windows.' );
		}
		if ( ! exec( 'tar --version' ) ) {
			$this->markTestSkipped( 'tar not installed.' );
		}

		list( $temp_dir, $src_dir, $wp_dir ) = self::create_test_directory_structure();

		$outside = $temp_dir . '/outside.txt';
		file_put_contents( $outside, 'outside' );

		$target = $absolute ? $outside : '../outside.txt';
		$this->assertTrue( symlink( $target, $wp_dir . '/wp-settings.php' ) );

		$tarball  = $temp_dir . '/test.tar.gz';
		$dest_dir = $temp_dir . '/dest';

		exec( Utils\esc_cmd( 'tar czf %s --directory=%s wordpress 2>&1', $tarball, $src_dir ), $output, $return_var );
		$this->assertSame( 0, $return_var );

		$msg = '';
		try {
			Extractor::extract( $tarball, $dest_dir );
		} catch ( \Exception $e ) {
			$msg = $e->getMessage();
		}

		$this->assertSame( "Refusing to extract symbolic link 'wp-settings.php'.", $msg );
		$this->assertFalse( is_link( $dest_dir . '/wp-settings.php' ) );
		$this->assertFalse( file_exists( $dest_dir . '/index1.php' ), 'Nothing should be written when the archive is rejected.' );
		$this->assertSame( 'outside', file_get_contents( $outside ) );

		Extractor::rmdir( $temp_dir );
	}

	/**
	 * @return array<string, array{bool, string}>
	 */
	public static function data_existing_symlinks(): array {
		return [
			'tar.gz, relative target' => [ false, 'tar.gz' ],
			'tar.gz, absolute target' => [ true, 'tar.gz' ],
			'zip, relative target'    => [ false, 'zip' ],
			'zip, absolute target'    => [ true, 'zip' ],
		];
	}

	/**
	 * @dataProvider data_existing_symlinks
	 */
	#[DataProvider( 'data_existing_symlinks' )] // phpcs:ignore PHPCompatibility.Attributes.NewAttributes.PHPUnitAttributeFound
	public function test_extract_does_not_write_through_existing_symlink( bool $absolute, string $format ): void {
		if ( Utils\is_windows() ) {
			$this->markTestSkipped( 'Creating symbolic links is not reliably supported on Windows.' );
		}
		if ( 'tar.gz' === $format && ! exec( 'tar --version' ) ) {
			$this->markTestSkipped( 'tar not installed.' );
		}
		if ( 'zip' === $format && ! class_exists( 'ZipArchive' ) ) {
			$this->markTestSkipped( 'ZipArchive not installed.' );
		}

		list( $temp_dir, $src_dir, $wp_dir ) = self::create_test_directory_structure();

		file_put_contents( $wp_dir . '/wp-config6.php', 'legit' );

		$archive  = $temp_dir . '/test.' . $format;
		$dest_dir = $temp_dir . '/dest';

		if ( 'zip' === $format ) {
			$zip = new ZipArchive();
			$this->assertTrue( $zip->open( $archive, ZipArchive::CREATE ) );
			foreach ( self::recursive_scandir( $src_dir ) as $file ) {
				if ( 0 === substr_compare( $file, '/', -1 ) ) {
					$this->assertTrue( $zip->addEmptyDir( $file ) );
				} else {
					$this->assertTrue( $zip->addFile( $src_dir . '/' . $file, $file ) );
				}
			}
			$this->assertTrue( $zip->close() );
		} else {
			exec( Utils\esc_cmd( 'tar czf %s --directory=%s wordpress 2>&1', $archive, $src_dir ), $output, $return_var );
			$this->assertSame( 0, $return_var );
		}

		// A symbolic link planted in the destination by an earlier extraction.
		$outside = $temp_dir . '/outside.txt';
		file_put_contents( $outside, 'outside' );
		mkdir( $dest_dir );
		$this->assertTrue( symlink( $absolute ? $outside : '../outside.txt', $dest_dir . '/wp-config6.php' ) );

		// A dangling symbolic link must not create its target either.
		$dangling = $temp_dir . '/dangling.txt';
		$this->assertTrue( symlink( $absolute ? $dangling : '../dangling.txt', $dest_dir . '/xmlrpc8.php' ) );

		Extractor::extract( $archive, $dest_dir );

		$this->assertSame( 'outside', file_get_contents( $outside ) );
		$this->assertFalse( file_exists( $dangling ) );
		$this->assertFalse( is_link( $dest_dir . '/wp-config6.php' ) );
		$this->assertFalse( is_link( $dest_dir . '/xmlrpc8.php' ) );
		$this->assertSame( 'legit', file_get_contents( $dest_dir . '/wp-config6.php' ) );
		$this->assertSame( self::$expected_wp, self::recursive_scandir( $dest_dir ) );

		Extractor::rmdir( $temp_dir );
	}

	public function test_copy_overwrite_files_rejects_symlinked_dir_outside_dest(): void {
		if ( Utils\is_windows() ) {
			$this->markTestSkipped( 'Creating symbolic links is not reliably supported on Windows.' );
		}

		list( $temp_dir, $src_dir, $wp_dir ) = self::create_test_directory_structure();

		$outside_dir = $temp_dir . '/outside';
		mkdir( $outside_dir );

		$dest_dir = $temp_dir . '/dest';
		mkdir( $dest_dir );
		$this->assertTrue( symlink( '../outside', $dest_dir . '/wp-includes' ) );

		$msg = '';
		try {
			Extractor::copy_overwrite_files( $wp_dir, $dest_dir );
		} catch ( \Exception $e ) {
			$msg = $e->getMessage();
		}

		$this->assertStringContainsString( "Refusing to write through symbolic link 'wp-includes'", $msg );
		$this->assertSame( [], self::recursive_scandir( $outside_dir ) );

		Extractor::rmdir( $temp_dir );
	}

	public function test_copy_overwrite_files_allows_symlinked_dir_inside_dest(): void {
		if ( Utils\is_windows() ) {
			$this->markTestSkipped( 'Creating symbolic links is not reliably supported on Windows.' );
		}

		list( $temp_dir, $src_dir, $wp_dir ) = self::create_test_directory_structure();

		$dest_dir = $temp_dir . '/dest';
		mkdir( $dest_dir . '/shared', 0755, true );
		$this->assertTrue( symlink( 'shared', $dest_dir . '/wp-includes' ) );

		Extractor::copy_overwrite_files( $wp_dir, $dest_dir );

		$this->assertTrue( is_link( $dest_dir . '/wp-includes' ) );
		$this->assertFileExists( $dest_dir . '/shared/file7.php' );
		$this->assertEmpty( self::$logger->stderr );

		Extractor::rmdir( $temp_dir );
	}

	/**
	 * @return array{string, string, string}
	 */
	private static function create_test_directory_structure(): array {
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

	/**
	 * @param string $dir
	 * @param string $prefix_dir
	 * @return array<int, string>
	 */
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
