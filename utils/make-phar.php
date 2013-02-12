<?php

// php -dphar.readonly=0 utils/make-phar.php [--quiet]

define( 'BE_QUIET', isset( $argv[1] ) && '--quiet' == $argv[1] );

$iterator = new \RecursiveIteratorIterator(
	new \RecursiveDirectoryIterator( './php', FilesystemIterator::SKIP_DOTS )
);

$phar = new Phar( 'wp-cli.phar', 0, 'wp-cli.phar' );

$phar->startBuffering();

$ignored_paths = array(
	'/mustache/bin/',
	'/mustache/test/',
	'/mustache/vendor/',
	'/php-cli-tools/examples/'
);

foreach ( $iterator as $path ) {
	foreach ( $ignored_paths as $ignore ) {
		if ( strpos( $path, $ignore ) )
			continue 2;
	}

	if ( !preg_match( '/\.php$/', $path ) )
		continue;

	$key = str_replace( './', '', $path );

	if ( !BE_QUIET )
		echo "$key - $path\n";

	$phar[ $key ] = file_get_contents( $path );
}

$phar->setStub( <<<EOB
<?php
Phar::mapPhar();
include 'phar://wp-cli.phar/php/boot-phar.php';
__HALT_COMPILER();
?>
EOB
);

$phar->stopBuffering();

echo "Generated wp-cli.phar.\n";

