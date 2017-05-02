<?php

define( 'WP_CLI_ROOT', dirname( dirname( __FILE__ ) ) );

if ( file_exists( WP_CLI_ROOT . '/vendor/autoload.php' ) ) {
	define( 'WP_CLI_BASE_PATH', WP_CLI_ROOT );
	define( 'WP_CLI_VENDOR_DIR' , WP_CLI_ROOT . '/vendor' );
} elseif ( file_exists( dirname( dirname( WP_CLI_ROOT ) ) . '/autoload.php' ) ) {
	define( 'WP_CLI_BASE_PATH', dirname( dirname( dirname( WP_CLI_ROOT ) ) ) );
	define( 'WP_CLI_VENDOR_DIR' , dirname( dirname( WP_CLI_ROOT ) ) );
} else {
	echo 'Missing vendor/autoload.php';
	exit(1);
}
require WP_CLI_VENDOR_DIR . '/autoload.php';
require WP_CLI_ROOT . '/php/utils.php';

use Symfony\Component\Finder\Finder;
use WP_CLI\Utils;
use WP_CLI\Configurator;

$configurator = new Configurator( WP_CLI_ROOT . '/utils/make-phar-spec.php' );

list( $args, $assoc_args, $runtime_config ) = $configurator->parse_args( array_slice( $GLOBALS['argv'], 1 ) );

if ( ! isset( $args[0] ) || empty( $args[0] ) ) {
	echo "usage: php -dphar.readonly=0 $argv[0] <path> [--quiet] [--version=same|patch|minor|major|x.y.z] [--store-version]\n";
	exit(1);
}

define( 'DEST_PATH', $args[0] );

define( 'BE_QUIET', isset( $runtime_config['quiet'] ) && $runtime_config['quiet'] );

$current_version = trim( file_get_contents( WP_CLI_ROOT . '/VERSION' ) );

if ( isset( $runtime_config['version'] ) ) {
	$new_version = $runtime_config['version'];
	$new_version = Utils\increment_version( $current_version, $new_version );

	if ( isset( $runtime_config['store-version'] ) && $runtime_config['store-version'] ) {
		file_put_contents( WP_CLI_ROOT . '/VERSION', $new_version );
	}

	$current_version = $new_version;
}

function add_file( $phar, $path ) {
	$key = str_replace( WP_CLI_BASE_PATH, '', $path );

	if ( !BE_QUIET )
		echo "$key - $path\n";

	$phar[ $key ] = file_get_contents( $path );
}

function set_file_contents( $phar, $path, $content ) {
	$key = str_replace( WP_CLI_BASE_PATH, '', $path );

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
	->in(WP_CLI_ROOT . '/php')
	->in(WP_CLI_ROOT . '/features')
	->in(WP_CLI_VENDOR_DIR . '/wp-cli')
	->in(WP_CLI_VENDOR_DIR . '/mustache')
	->in(WP_CLI_VENDOR_DIR . '/rmccue/requests')
	->in(WP_CLI_VENDOR_DIR . '/composer')
	->in(WP_CLI_VENDOR_DIR . '/psr')
	->in(WP_CLI_VENDOR_DIR . '/seld')
	->in(WP_CLI_VENDOR_DIR . '/symfony')
	->in(WP_CLI_VENDOR_DIR . '/nb/oxymel')
	->in(WP_CLI_VENDOR_DIR . '/ramsey/array_column')
	->in(WP_CLI_VENDOR_DIR . '/justinrainbow/json-schema')
	->exclude('test')
	->exclude('tests')
	->exclude('Test')
	->exclude('Tests')
	->exclude('php-cli-tools/examples')
	;

foreach ( $finder as $file ) {
	add_file( $phar, $file );
}

// Include base project files, because the autoloader will load them
if ( WP_CLI_BASE_PATH !== WP_CLI_ROOT ) {
	$finder = new Finder();
	$finder
		->files()
		->ignoreVCS(true)
		->name('*.php')
		->in(WP_CLI_BASE_PATH . '/src')
		->exclude('test')
		->exclude('tests')
		->exclude('Test')
		->exclude('Tests');
	foreach ( $finder as $file ) {
		add_file( $phar, $file );
	}
	// Any PHP files in the project root
	foreach ( glob( WP_CLI_BASE_PATH . '/*.php' ) as $file ) {
		add_file( $phar, $file );
	}
}

// other files
$finder = new Finder();
$finder
	->files()
	->ignoreVCS(true)
	->ignoreDotFiles(false)
	->in( WP_CLI_ROOT . '/templates')
	;

foreach ( $finder as $file ) {
	add_file( $phar, $file );
}

$finder = new Finder();
$finder
	->files()
	->ignoreVCS(true)
	->ignoreDotFiles(false)
	->in( WP_CLI_VENDOR_DIR . '/wp-cli/config-command/templates')
	;
foreach ( $finder as $file ) {
	add_file( $phar, $file );
}

$finder = new Finder();
$finder
	->files()
	->ignoreVCS(true)
	->ignoreDotFiles(false)
	->in( WP_CLI_VENDOR_DIR . '/wp-cli/scaffold-command/templates')
	;
foreach ( $finder as $file ) {
	add_file( $phar, $file );
}

add_file( $phar, WP_CLI_VENDOR_DIR . '/autoload.php' );
add_file( $phar, WP_CLI_VENDOR_DIR . '/autoload_commands.php' );
add_file( $phar, WP_CLI_VENDOR_DIR . '/autoload_framework.php' );
add_file( $phar, WP_CLI_ROOT . '/ci/behat-tags.php' );
add_file( $phar, WP_CLI_VENDOR_DIR . '/composer/ca-bundle/res/cacert.pem' );
add_file( $phar, WP_CLI_VENDOR_DIR . '/composer/composer/LICENSE' );
add_file( $phar, WP_CLI_VENDOR_DIR . '/composer/composer/res/composer-schema.json' );
add_file( $phar, WP_CLI_VENDOR_DIR . '/rmccue/requests/library/Requests/Transport/cacert.pem' );

set_file_contents( $phar, WP_CLI_ROOT . '/VERSION', $current_version );

$phar_boot = str_replace( WP_CLI_BASE_PATH, '', WP_CLI_ROOT . '/php/boot-phar.php' );
$phar->setStub( <<<EOB
#!/usr/bin/env php
<?php
Phar::mapPhar();
include 'phar://wp-cli.phar/{$phar_boot}';
__HALT_COMPILER();
?>
EOB
);

$phar->stopBuffering();

echo "Generated " . DEST_PATH . "\n";
