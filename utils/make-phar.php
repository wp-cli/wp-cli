<?php

require './vendor/autoload.php';
require './php/utils.php';

use Symfony\Component\Finder\Finder;
use WP_CLI\Utils;
use WP_CLI\Configurator;

$configurator = new Configurator( './utils/make-phar-spec.php' );

list( $args, $assoc_args, $runtime_config ) = $configurator->parse_args( array_slice( $GLOBALS['argv'], 1 ) );

if ( ! isset( $args[0] ) || empty( $args[0] ) ) {
	echo "usage: php -dphar.readonly=0 $argv[0] <path> [--quiet] [--version=same|patch|minor|major|x.y.z] [--store-version]\n";
	exit(1);
}

define( 'DEST_PATH', $args[0] );

define( 'BE_QUIET', isset( $runtime_config['quiet'] ) && $runtime_config['quiet'] );

$current_version = trim( file_get_contents( './VERSION' ) );

if ( isset( $runtime_config['version'] ) ) {
	$new_version = $runtime_config['version'];
	$new_version = Utils\increment_version( $current_version, $new_version );

	if ( isset( $runtime_config['store-version'] ) && $runtime_config['store-version'] ) {
		file_put_contents( './VERSION', $new_version );
	}

	$current_version = $new_version;
}

function add_file( $phar, $path ) {
	$key = str_replace( './', '', $path );

	if ( !BE_QUIET )
		echo "$key - $path\n";

	$phar[ $key ] = file_get_contents( $path );
}

function set_file_contents( $phar, $path, $content ) {
	$key = str_replace( './', '', $path );

	if ( !BE_QUIET )
		echo "$key - $path\n";

	$phar[ $key ] = $content;
}

$phar = new Phar( DEST_PATH, 0, 'wp-cli.phar' );

$phar->startBuffering();

// PHP files
$finder = new Finder();
$finder
	->files()
	->ignoreVCS(true)
	->name('*.php')
	->in('./php')
	->in('./features')
	->in('./vendor/wp-cli')
	->in('./vendor/mustache')
	->in('./vendor/rmccue/requests')
	->in('./vendor/composer')
	->in('./vendor/symfony/finder')
	->in('./vendor/nb/oxymel')
	->in('./vendor/ramsey/array_column')
	->exclude('test')
	->exclude('tests')
	->exclude('Tests')
	->exclude('php-cli-tools/examples')
	;

foreach ( $finder as $file ) {
	add_file( $phar, $file );
}

// other files
$finder = new Finder();
$finder
	->files()
	->ignoreVCS(true)
	->ignoreDotFiles(false)
	->in('./templates')
	;

foreach ( $finder as $file ) {
	add_file( $phar, $file );
}

add_file( $phar, './vendor/autoload.php' );
add_file( $phar, './utils/get-package-require-from-composer.php' );
add_file( $phar, './vendor/rmccue/requests/library/Requests/Transport/cacert.pem' );

set_file_contents( $phar, './VERSION', $current_version );

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
