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

// php files
foreach ( get_iterator( './php' ) as $path ) {
	if ( !preg_match( '/\.php$/', $path ) )
		continue;

	add_file( $phar, $path );
}

// non-php files
$additional_dirs = array(
	'./templates',
	'./man'
);

foreach ( $additional_dirs as $dir ) {
	foreach ( get_iterator( $dir ) as $path ) {
		add_file( $phar, $path );
	}
}

// dependencies
$ignored_paths = array(
	'/.git',
);

$vendor_dirs = array(
	'./vendor/mustache',
	'./vendor/wp-cli',
	'./vendor/composer',
);

foreach ( $vendor_dirs as $vendor_dir ) {
	foreach ( get_iterator( $vendor_dir ) as $path ) {
		foreach ( $ignored_paths as $ignore ) {
			if ( strpos( $path, $ignore ) )
				continue 2;
		}

		add_file( $phar, $path );
	}
}

add_file( $phar, './vendor/autoload.php' );

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

echo "Generated " . DEST_PATH . "\n";

