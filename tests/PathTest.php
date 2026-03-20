<?php

namespace WP_CLI\Tests;

use PHPUnit\Framework\Attributes\DataProvider;

use WP_CLI\Path;
use WP_CLI\Utils;

/**
 * Tests for the WP_CLI\Path class and the deprecated Utils path helper functions.
 */
final class PathTest extends TestCase {

	/**
	 * @dataProvider dataProviderPathCases
	 */
	#[DataProvider( 'dataProviderPathCases' )] // phpcs:ignore PHPCompatibility.Attributes.NewAttributes.PHPUnitAttributeFound
	public function testIsAbsolute( $path, $expected ) {
		$this->assertSame(
			$expected,
			Path::is_absolute( $path ),
			"Failed asserting that path '{$path}' is recognized correctly."
		);
	}

	/**
	 * @dataProvider dataProviderPathCases
	 */
	#[DataProvider( 'dataProviderPathCases' )] // phpcs:ignore PHPCompatibility.Attributes.NewAttributes.PHPUnitAttributeFound
	public function testPathIsRecognizedAsAbsolute( $path, $expected ) {
		$this->assertSame(
			$expected,
			Utils\is_path_absolute( $path ),
			"Failed asserting that path '{$path}' is recognized correctly."
		);
	}

	public static function dataProviderPathCases(): array {
		return [
			// Windows-style absolute paths.
			[ 'C:\\wp\\public/', true ],
			[ 'C:/wp/public/', true ],
			[ 'C:\\wp\\public', true ],
			[ 'C:\\', true ],
			[ 'c:\\', true ],
			[ 'c:/path', true ],
			[ 'C:\\wp/public', true ],
			[ 'C:', false ],
			[ '\\\\Server\\Share', true ], // UNC path.

			// Unix-style absolute paths.
			[ '/var/www/html/', true ],
			[ '/', true ], // Root.

			// Relative paths (not absolute).
			[ './relative/path', false ],
			[ '', false ],
		];
	}

	public function testGetHomeDir(): void {
		$home      = getenv( 'HOME' );
		$homedrive = getenv( 'HOMEDRIVE' );
		$homepath  = getenv( 'HOMEPATH' );

		putenv( 'HOME=/home/user' );
		$this->assertSame( '/home/user', Path::get_home_dir() );

		putenv( 'HOME' );

		putenv( 'HOMEDRIVE=D:' );
		putenv( 'HOMEPATH' );
		$this->assertSame( 'D:', Path::get_home_dir() );

		putenv( 'HOMEPATH=\\Windows\\User\\' );
		$this->assertSame( 'D:\\Windows\\User', Path::get_home_dir() );

		// Restore environments.
		putenv( false === $home ? 'HOME' : "HOME=$home" );
		putenv( false === $homedrive ? 'HOMEDRIVE' : "HOMEDRIVE=$homedrive" );
		putenv( false === $homepath ? 'HOMEPATH' : "HOMEPATH=$homepath" );
	}

	public function testTrailingslashit(): void {
		$this->assertSame( 'a/', Path::trailingslashit( 'a' ) );
		$this->assertSame( 'a/', Path::trailingslashit( 'a/' ) );
		$this->assertSame( 'a/', Path::trailingslashit( 'a\\' ) );
		$this->assertSame( 'a/', Path::trailingslashit( 'a\\//\\' ) );
	}

	public function testIsStream(): void {
		$this->assertTrue( Path::is_stream( 'phar:///path/to/file.phar' ) );
		$this->assertTrue( Path::is_stream( 'php://stdin' ) );
		$this->assertTrue( Path::is_stream( 'PHAR:///path/to/file.phar' ) );
		$this->assertTrue( Path::is_stream( 'PhAr:///path/to/file.phar' ) );
		$this->assertFalse( Path::is_stream( '/www/path' ) );
		$this->assertFalse( Path::is_stream( 'C:/www/path' ) );
		$this->assertFalse( Path::is_stream( '' ) );
		$this->assertFalse( Path::is_stream( 'nonexistent_wrapper://path' ) );
	}

	/**
	 * @dataProvider dataNormalize
	 */
	#[DataProvider( 'dataNormalize' )] // phpcs:ignore PHPCompatibility.Attributes.NewAttributes.PHPUnitAttributeFound
	public function testNormalize( $path, $expected ): void {
		$this->assertEquals( $expected, Path::normalize( $path ) );
	}

	public static function dataNormalize(): array {
		return [
			[ '', '' ],
			// Windows paths.
			[ 'C:\\www\\path\\', 'C:/www/path/' ],
			[ 'C:\\www\\\\path\\', 'C:/www/path/' ],
			[ 'c:/www/path', 'C:/www/path' ],
			[ 'c:\\www\\path\\', 'C:/www/path/' ],
			[ 'c:', 'C:' ],
			[ 'c:\\', 'C:/' ],
			[ 'c:\\\\www\\path\\', 'C:/www/path/' ],
			[ '\\\\Domain\\DFSRoots\\share\\path\\', '//Domain/DFSRoots/share/path/' ],
			[ '\\\\Server\\share\\path', '//Server/share/path' ],
			[ '\\\\Server\\share', '//Server/share' ],
			// Linux paths.
			[ '/', '/' ],
			[ '/www/path/', '/www/path/' ],
			[ '/www/path/////', '/www/path/' ],
			[ '/www/path', '/www/path' ],
			// PHP stream wrapper paths.
			[ 'phar:///path/to/file.phar/www/path', 'phar:///path/to/file.phar/www/path' ],
			[ 'php://stdin', 'php://stdin' ],
			[ 'phar:///path/to/file.phar/some//dir', 'phar:///path/to/file.phar/some/dir' ],
			[ 'phar:///path/to/file.phar/some\\dir/file', 'phar:///path/to/file.phar/some/dir/file' ],
			[ 'PHAR:///path/to/file.phar/some//dir', 'PHAR:///path/to/file.phar/some/dir' ],
			[ 'PhAr:///path/to/file.phar/some\\dir/file', 'PhAr:///path/to/file.phar/some/dir/file' ],
			// Paths with single-dot segments.
			[ '/www/./path/', '/www/path/' ],
			[ '/www/html/./public/wp/', '/www/html/public/wp/' ],
			[ '/www/./path', '/www/path' ],
			[ '/www/path/.', '/www/path/' ],
			[ '/www/path/./', '/www/path/' ],
			[ '/www/././path/', '/www/path/' ],
			[ './public/wp', 'public/wp' ],
		];
	}

	public function testBasename(): void {
		$this->assertSame( 'file.txt', Path::basename( '/path/to/file.txt' ) );
		$this->assertSame( 'file', Path::basename( '/path/to/file.txt', '.txt' ) );
		$this->assertSame( 'file.txt', Path::basename( 'C:\\path\\to\\file.txt' ) );
	}

	public function testExpandTilde(): void {
		$home = Path::get_home_dir();

		$this->assertEquals( $home, Path::expand_tilde( '~' ) );
		$this->assertEquals( $home . '/sites/wordpress', Path::expand_tilde( '~/sites/wordpress' ) );
		$this->assertEquals( '/absolute/path', Path::expand_tilde( '/absolute/path' ) );
		$this->assertEquals( 'relative/path', Path::expand_tilde( 'relative/path' ) );
		$this->assertEquals( '/path/to/~something', Path::expand_tilde( '/path/to/~something' ) );
	}

	public function testReplacePathConsts(): void {
		$expected = "define( 'ABSPATH', dirname( 'C:\\\\Users\\\\test\\'s\\\\site' ) . '/' );";
		$source   = "define( 'ABSPATH', dirname( __FILE__ ) . '/' );";
		$actual   = Path::replace_path_consts( $source, 'C:\\Users\\\\test\'s\\site' );
		$this->assertSame( $expected, $actual );
	}

	public function testInsidePhar(): void {
		$this->assertFalse( Path::inside_phar( '/regular/path/to/file.php' ) );
		$this->assertTrue( Path::inside_phar( 'phar:///path/to/archive.phar/file.php' ) );
	}
}
