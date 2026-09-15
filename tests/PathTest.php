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
	public function testIsAbsolute( string $path, bool $expected ): void {
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
	public function testPathIsRecognizedAsAbsolute( string $path, bool $expected ): void {
		$this->assertSame(
			$expected,
			// @phpstan-ignore function.deprecated
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
	public function testNormalize( string $path, string $expected ): void {
		$this->assertSame( $expected, Path::normalize( $path ) );
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

		$this->assertSame( $home, Path::expand_tilde( '~' ) );
		$this->assertSame( $home . '/sites/wordpress', Path::expand_tilde( '~/sites/wordpress' ) );
		$this->assertSame( '/absolute/path', Path::expand_tilde( '/absolute/path' ) );
		$this->assertSame( 'relative/path', Path::expand_tilde( 'relative/path' ) );
		$this->assertSame( '/path/to/~something', Path::expand_tilde( '/path/to/~something' ) );
	}

	public function testReplacePathConsts(): void {
		$expected = "define( 'ABSPATH', dirname( 'C:\\\\Users\\\\test\'s\\\\site' ) . '/' );";
		$source   = "define( 'ABSPATH', dirname( __FILE__ ) . '/' );";
		$actual   = Path::replace_path_consts( $source, "C:\Users\\test's\site" );
		$this->assertSame( $expected, $actual );
	}

	/**
	 * @dataProvider dataReplacePathConstsSources
	 * @param string $source
	 * @param string $expected
	 */
	#[DataProvider( 'dataReplacePathConstsSources' )] // phpcs:ignore PHPCompatibility.Attributes.NewAttributes.PHPUnitAttributeFound
	public function testReplacePathConstsSources( $source, $expected ): void {
		$this->assertSame( $expected, Path::replace_path_consts( $source, '/path/to/file.php' ) );
	}

	/**
	 * Sources the tokenizer and the regular expression fallback must agree on.
	 */
	public static function dataReplacePathConstsSources(): array {
		return [
			'bare snippet'             => [
				"dirname( __FILE__ ) . '",
				"dirname( '/path/to/file.php' ) . '",
			],
			'file and dir'             => [
				"<?php\necho __FILE__ . __DIR__;",
				"<?php\necho '/path/to/file.php' . '/path/to';",
			],
			'without constants'        => [
				"<?php\necho 'unchanged';",
				"<?php\necho 'unchanged';",
			],
			'single-quoted string'     => [
				"<?php\necho '__FILE__' . __FILE__;",
				"<?php\necho '__FILE__' . '/path/to/file.php';",
			],
			'double-quoted string'     => [
				"<?php\necho \"__DIR__\" . __DIR__;",
				"<?php\necho \"__DIR__\" . '/path/to';",
			],
			'escaped quotes in string' => [
				"<?php\necho 'it\\'s __FILE__' . \"say \\\"__FILE__\\\"\" . __FILE__;",
				"<?php\necho 'it\\'s __FILE__' . \"say \\\"__FILE__\\\"\" . '/path/to/file.php';",
			],
			'line comments'            => [
				"<?php\n// __FILE__\n# __DIR__\necho __FILE__;",
				"<?php\n// __FILE__\n# __DIR__\necho '/path/to/file.php';",
			],
			'block comment'            => [
				"<?php\n/* __FILE__\n * __DIR__ */\necho __DIR__;",
				"<?php\n/* __FILE__\n * __DIR__ */\necho '/path/to';",
			],
			'shebang before open tag'  => [
				"#!/usr/bin/env wp\n<?php\necho __FILE__;",
				"#!/usr/bin/env wp\n<?php\necho '/path/to/file.php';",
			],
			'windows line endings'     => [
				"<?php\r\n// __FILE__\r\necho __FILE__;\r\n",
				"<?php\r\n// __FILE__\r\necho '/path/to/file.php';\r\n",
			],
			'case-insensitive'         => [
				"<?php\necho __file__ . __Dir__;",
				"<?php\necho '/path/to/file.php' . '/path/to';",
			],
		];
	}

	/**
	 * @dataProvider dataReplacePathConstsTokenizerSources
	 * @param string $source
	 * @param string $expected
	 */
	#[DataProvider( 'dataReplacePathConstsTokenizerSources' )] // phpcs:ignore PHPCompatibility.Attributes.NewAttributes.PHPUnitAttributeFound
	public function testReplacePathConstsWithTokenizer( $source, $expected ): void {
		if ( ! function_exists( 'token_get_all' ) ) {
			$this->markTestSkipped( 'The tokenizer extension is not available.' );
		}

		$this->assertSame( $expected, Path::replace_path_consts( $source, '/path/to/file.php' ) );
	}

	/**
	 * Sources only the tokenizer handles correctly, or that are too large for the
	 * regular expression fallback.
	 */
	public static function dataReplacePathConstsTokenizerSources(): array {
		return [
			'heredoc'                    => [
				"<?php\necho <<<HTML\n<a href=\"__FILE__\">it's __DIR__</a>\nHTML;\necho __FILE__;",
				"<?php\necho <<<HTML\n<a href=\"__FILE__\">it's __DIR__</a>\nHTML;\necho '/path/to/file.php';",
			],
			'nowdoc'                     => [
				"<?php\necho <<<'TXT'\n__FILE__\nTXT;\necho __DIR__;",
				"<?php\necho <<<'TXT'\n__FILE__\nTXT;\necho '/path/to';",
			],
			'inline html'                => [
				"<p class=\"__FILE__\">it's</p>\n<?php echo __FILE__; ?>\n<p>__DIR__</p>",
				"<p class=\"__FILE__\">it's</p>\n<?php echo '/path/to/file.php'; ?>\n<p>__DIR__</p>",
			],
			'unterminated string'        => [
				"<?php\necho __FILE__; echo 'unterminated __FILE__",
				"<?php\necho '/path/to/file.php'; echo 'unterminated __FILE__",
			],
			'unterminated comment'       => [
				"<?php\necho __DIR__; /* __FILE__",
				"<?php\necho '/path/to'; /* __FILE__",
			],
			'short echo tag'             => [
				'<?= __FILE__ ?>',
				"<?= '/path/to/file.php' ?>",
			],
			'not a magic constant'       => [
				"<?php\necho \$obj->__FILE__, \$__FILE__, __FILE;",
				"<?php\necho \$obj->__FILE__, \$__FILE__, __FILE;",
			],
			'binary content'             => [
				"<?php\necho __FILE__; ?>\x00\xff\xfe",
				"<?php\necho '/path/to/file.php'; ?>\x00\xff\xfe",
			],
			'empty'                      => [ '', '' ],
			'large single-quoted string' => [
				"<?php\n\$html = '" . str_repeat( 'a', 1024 * 1024 ) . "';\necho __FILE__;",
				"<?php\n\$html = '" . str_repeat( 'a', 1024 * 1024 ) . "';\necho '/path/to/file.php';",
			],
			'large double-quoted string' => [
				"<?php\n\$html = \"" . str_repeat( 'a', 1024 * 1024 ) . "\";\necho __FILE__;",
				"<?php\n\$html = \"" . str_repeat( 'a', 1024 * 1024 ) . "\";\necho '/path/to/file.php';",
			],
			'many quoted strings'        => [
				"<?php\n" . str_repeat( "\$html .= '<a href=\"https://example.com/\">it\\'s \"quoted\"</a>';\n", 5000 ) . 'echo __FILE__;',
				"<?php\n" . str_repeat( "\$html .= '<a href=\"https://example.com/\">it\\'s \"quoted\"</a>';\n", 5000 ) . "echo '/path/to/file.php';",
			],
			'large block comment'        => [
				"<?php\n/* " . str_repeat( 'a', 1024 * 1024 ) . " */\necho __FILE__;",
				"<?php\n/* " . str_repeat( 'a', 1024 * 1024 ) . " */\necho '/path/to/file.php';",
			],
		];
	}

	/**
	 * @dataProvider dataReplacePathConstsSources
	 * @param string $source
	 * @param string $expected
	 */
	#[DataProvider( 'dataReplacePathConstsSources' )] // phpcs:ignore PHPCompatibility.Attributes.NewAttributes.PHPUnitAttributeFound
	public function testReplacePathConstsWithRegex( $source, $expected ): void {
		$this->assertSame( $expected, $this->replace_path_consts_with_regex( $source ) );
	}

	/**
	 * The regular expression fallback needs to backtrack for every character of a
	 * string, which exhausts the PCRE JIT stack or backtrack limit on large
	 * strings. It must fail loudly instead of silently returning nothing.
	 */
	public function testReplacePathConstsWithRegexThrowsOnPcreFailure(): void {
		$source = "<?php\n\$html = \"" . str_repeat( 'a', 1024 * 1024 ) . "\";\necho __FILE__;";

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'Failed to replace the __FILE__ and __DIR__ magic constants' );

		// The JIT ignores the backtrack limit and runs out of stack on its own,
		// but lower the limit so the non-JIT engine fails deterministically too.
		$backtrack_limit = ini_set( 'pcre.backtrack_limit', '1000' ); // phpcs:ignore WordPress.PHP.IniSet.Risky

		try {
			$this->replace_path_consts_with_regex( $source );
		} finally {
			if ( false !== $backtrack_limit ) {
				ini_set( 'pcre.backtrack_limit', $backtrack_limit ); // phpcs:ignore WordPress.PHP.IniSet.Risky
			}
		}
	}

	/**
	 * @return mixed
	 */
	private function replace_path_consts_with_regex( string $source ) {
		$method = new \ReflectionMethod( Path::class, 'replace_path_consts_with_regex' );
		if ( PHP_VERSION_ID < 80100 ) {
			// @phpstan-ignore method.deprecated
			$method->setAccessible( true );
		}

		return $method->invoke( null, $source, '/path/to/file.php', '/path/to' );
	}

	public function testInsidePhar(): void {
		$this->assertFalse( Path::inside_phar( '/regular/path/to/file.php' ) );
		$this->assertTrue( Path::inside_phar( 'phar:///path/to/archive.phar/file.php' ) );
	}

	/**
	 * @dataProvider dataPharSafe
	 * @param string $path
	 * @param string|null $phar_path
	 * @param string|null $phar_root
	 * @param string $expected
	 */
	#[DataProvider( 'dataPharSafe' )] // phpcs:ignore PHPCompatibility.Attributes.NewAttributes.PHPUnitAttributeFound
	public function testPharSafe( $path, $phar_path, $phar_root, $expected ): void {
		$this->assertSame(
			$expected,
			Path::phar_safe( $path, $phar_path, $phar_root )
		);
	}

	public static function dataPharSafe(): array {
		$bundled    = 'phar://wp-cli.phar/vendor/wp-cli/wp-cli';
		$standalone = 'phar://wp-cli.phar';

		return [
			// Not running inside a Phar: the path is returned unchanged.
			'outside phar'                   => [
				'/home/user/site/wp-config.php',
				'/home/user/.local/bin/wp',
				'/home/user/site',
				'/home/user/site/wp-config.php',
			],

			// Canonical filename, WP_CLI_PHAR_PATH as a phar:// URL ( Phar::running( true ) ).
			'canonical name, url phar path'  => [
				'phar:///home/user/.local/bin/wp-cli.phar/vendor/wp-cli/config-command/templates/wp-config.mustache',
				'phar:///home/user/.local/bin/wp-cli.phar',
				$bundled,
				'phar://wp-cli.phar/vendor/wp-cli/config-command/templates/wp-config.mustache',
			],

			// Renamed binary, WP_CLI_PHAR_PATH as a phar:// URL ( Phar::running( true ) ).
			'renamed binary, url phar path'  => [
				'phar:///home/user/.local/bin/wp/vendor/wp-cli/config-command/templates/wp-config.mustache',
				'phar:///home/user/.local/bin/wp',
				$bundled,
				'phar://wp-cli.phar/vendor/wp-cli/config-command/templates/wp-config.mustache',
			],

			// Renamed binary, WP_CLI_PHAR_PATH as a bare path ( Phar::running( false ) ).
			'renamed binary, bare phar path' => [
				'phar:///home/user/.local/bin/wp/vendor/wp-cli/config-command/templates/wp-config.mustache',
				'/home/user/.local/bin/wp',
				$bundled,
				'phar://wp-cli.phar/vendor/wp-cli/config-command/templates/wp-config.mustache',
			],

			// Windows bare path ( Phar::running( false ) ) with backslashes: the
			// separators are normalized so the prefix still matches the stream URL.
			'windows backslash phar path'    => [
				'phar://C:/Users/bob/wp/vendor/wp-cli/config-command/templates/wp-config.mustache',
				'C:\\Users\\bob\\wp',
				$bundled,
				'phar://wp-cli.phar/vendor/wp-cli/config-command/templates/wp-config.mustache',
			],

			// Standalone Phar layout (WP_CLI_ROOT without an internal path).
			'standalone root'                => [
				'phar:///home/user/.local/bin/wp/php/wp-cli.php',
				'/home/user/.local/bin/wp',
				$standalone,
				'phar://wp-cli.phar/php/wp-cli.php',
			],

			// Already in alias form: no double rewrite.
			'already aliased'                => [
				'phar://wp-cli.phar/vendor/wp-cli/wp-cli/templates/wp-config.mustache',
				'/home/user/.local/bin/wp',
				$bundled,
				'phar://wp-cli.phar/vendor/wp-cli/wp-cli/templates/wp-config.mustache',
			],

			// Root loaded via its physical path (no alias host): path left untouched.
			'physical root, no alias'        => [
				'phar:///home/user/.local/bin/wp/vendor/wp-cli/wp-cli/templates/wp-config.mustache',
				'/home/user/.local/bin/wp',
				'phar:///home/user/.local/bin/wp/vendor/wp-cli/wp-cli',
				'phar:///home/user/.local/bin/wp/vendor/wp-cli/wp-cli/templates/wp-config.mustache',
			],
		];
	}
}
