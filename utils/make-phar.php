<?php

if ( !isset( $argv[1] ) ) {
	echo "usage: php -dphar.readonly=0 $argv[0] <path> [--quiet]\n";
	exit(1);
}

define( 'DEST_PATH', $argv[1] );

define( 'BE_QUIET', in_array( '--quiet', $argv ) );

function get_iterator( $dir ) {
	return new \RecursiveIteratorIterator(
		new \RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS )
	);
}

function add_file( $phar, $path ) {
	$key = str_replace( './', '', $path );

	if ( !BE_QUIET )
		echo "$key - $path\n";

	$phar[ $key ] = file_get_contents( $path );
}

$phar = new Phar( DEST_PATH, 0, 'wp-cli.phar' );

$phar->startBuffering();

$ignored_paths = array(
	'/mustache/bin/',
	'/mustache/test/',
	'/mustache/vendor/',
	'/php-cli-tools/examples/'
);

foreach ( get_iterator( './php' ) as $path ) {
	foreach ( $ignored_paths as $ignore ) {
		if ( strpos( $path, $ignore ) )
			continue 2;
	}

	if ( !preg_match( '/\.php$/', $path ) )
		continue;

	add_file( $phar, $path );
}

foreach ( get_iterator( './templates' ) as $path ) {
	add_file( $phar, $path );
}

foreach ( get_iterator( './man' ) as $path ) {
	add_file( $phar, $path );
}

$phar->setStub( <<<EOB
#!/usr/bin/env php
<?php
Phar::mapPhar();
include 'phar://wp-cli.phar/php/boot-phar.php';
__HALT_COMPILER();
?>
EOB
);

$phar->stopBuffering();

echo "\nGenerated " . DEST_PATH . "\n";

