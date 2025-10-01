<?php

namespace WP_CLI\Tests;

use WP_CLI\Utils;

/**
 * Test is_path_absolute() on Windows and Unix-like systems.
 */
final class PathTest extends TestCase {

    /**
     * @dataProvider providePathCases
     */
    public function testPathIsRecognizedAsAbsolute( $path, $expected ) {
        $this->assertSame(
            $expected,
            Utils\is_path_absolute( $path ),
            "Failed asserting that path '{$path}' is recognized correctly."
        );
    }

        public function providePathCases(): array {
        return [
            // Windows-style absolute paths.
            [ 'C:\\wp\\public/', true ],
            [ 'C:/wp/public/', true ],
            [ 'C:\\wp\\public', true ],
            [ '\\\\Server\\Share', true ], // UNC path.

            // Unix-style absolute paths.
            [ '/var/www/html/', true ],
            [ '/', true ], // Root.

            // Relative paths (not absolute).
            [ './relative/path', false ],
            [ '', false ],
        ];
    }

}
