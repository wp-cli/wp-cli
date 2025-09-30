<?php

namespace WP_CLI\Tests;

use PHPUnit\Framework\TestCase;
use WP_CLI\Utils;

final class PathTest extends TestCase {

	/**
	 * @dataProvider providePathCases
	 */
	public function testPathIsRecognizedAsAbsolute( string $path, bool $expected ): void {
		$this->assertSame(
			$expected,
			Utils\is_path_absolute( $path ),
			"Failed asserting that path '$path' is recognized correctly."
		);
	}

	public static function providePathCases(): array {
		return [
			[ 'C:\\wp\\public/', true ],
			[ 'C:/wp/public/', true ],
			[ 'C:\\wp\\public', true ],
			[ '/var/www/html/', true ],
			[ './relative/path', false ],
		];
	}
}
