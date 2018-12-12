<?php

$file = $argv[1];
if ( ! file_exists( $file ) ) {
	echo 'File does not exist.';
	exit( 1 );
}

$contents = file_get_contents( $file );
$composer = json_decode( $contents );

if ( empty( $composer ) || ! is_object( $composer ) ) {
	echo 'Invalid composer.json for package.';
	exit( 1 );
}

if ( empty( $composer->autoload->files ) ) {
	echo 'composer.json must specify valid "autoload" => "files"';
	exit( 1 );
}

echo implode( PHP_EOL, $composer->autoload->files );
exit( 0 );
