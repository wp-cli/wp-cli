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

define( 'BUILD', isset( $runtime_config['build'] ) ? $runtime_config['build'] : '' );

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

	$basename = basename( $path );
	if ( 0 === strpos( $basename, 'autoload_' ) && preg_match( '/(?:classmap|files|namespaces|psr4|static)\.php$/', $basename ) ) {
		// Strip autoload maps of unused stuff.
		static $strip_res = null;
		if ( null === $strip_res ) {
			if ( 'cli' === BUILD ) {
				$strips = array(
					'\/(?:behat|composer|gherkin)\/src',
					'\/phpunit\/',
					'\/nb\/oxymel',
					'-command\/src\/',
					'\/wp-cli\/[^\n]+-command\/',
					'\/symfony\/(?!finder|polyfill-mbstring)[^\/]+\/',
					'\/(?:dealerdirect|squizlabs|wimg)\/',
				);
			} else {
				$strips = array(
					'\/(?:behat|gherkin)\/src\/',
					'\/phpunit\/',
					'\/symfony\/(?!console|filesystem|finder|polyfill-mbstring|process)[^\/]+\/',
					'\/composer\/spdx-licenses\/',
					'\/Composer\/(?:Command\/|Compiler\.php|Console\/|Downloader\/Pear|Installer\/Pear|Question\/|Repository\/Pear|SelfUpdate\/)',
					'\/(?:dealerdirect|squizlabs|wimg)\/',
				);
			}
			$strip_res = array_map( function ( $v ) {
				return '/^[^,\n]+?' . $v . '[^,\n]+?, *\n/m';
			}, $strips );
		}
		$phar[ $key ] = preg_replace( $strip_res, '', file_get_contents( $path ) );
	} else {
		$phar[ $key ] = file_get_contents( $path );
	}
}

function set_file_contents( $phar, $path, $content ) {
	$key = str_replace( WP_CLI_BASE_PATH, '', $path );

	if ( !BE_QUIET )
		echo "$key - $path\n";

	$phar[ $key ] = $content;
}

if ( file_exists( DEST_PATH ) ) {
	unlink( DEST_PATH );
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
	->in(WP_CLI_VENDOR_DIR . '/mustache')
	->in(WP_CLI_VENDOR_DIR . '/rmccue/requests')
	->in(WP_CLI_VENDOR_DIR . '/composer')
	->in(WP_CLI_VENDOR_DIR . '/ramsey/array_column')
	->in(WP_CLI_VENDOR_DIR . '/symfony/finder')
	->in(WP_CLI_VENDOR_DIR . '/symfony/polyfill-mbstring')
	->notName('behat-tags.php')
	->notPath('#(?:[^/]+-command|php-cli-tools)/vendor/#') // For running locally, in case have composer installed or symlinked them.
	->exclude('examples')
	->exclude('features')
	->exclude('test')
	->exclude('tests')
	->exclude('Test')
	->exclude('Tests')
	;
if ( 'cli' === BUILD ) {
	$finder
		->in(WP_CLI_VENDOR_DIR . '/wp-cli/mustangostang-spyc')
		->in(WP_CLI_VENDOR_DIR . '/wp-cli/php-cli-tools')
		->in(WP_CLI_VENDOR_DIR . '/seld/cli-prompt')
		->exclude('composer/ca-bundle')
		->exclude('composer/semver')
		->exclude('composer/src')
		->exclude('composer/spdx-licenses')
		;
} else {
	$finder
		->in(WP_CLI_VENDOR_DIR . '/wp-cli')
		->in(WP_CLI_ROOT . '/features/bootstrap') // These are required for scaffold-package-command.
		->in(WP_CLI_ROOT . '/features/steps')
		->in(WP_CLI_ROOT . '/features/extra')
		->in(WP_CLI_VENDOR_DIR . '/nb/oxymel')
		->in(WP_CLI_VENDOR_DIR . '/psr')
		->in(WP_CLI_VENDOR_DIR . '/seld')
		->in(WP_CLI_VENDOR_DIR . '/symfony/console')
		->in(WP_CLI_VENDOR_DIR . '/symfony/filesystem')
		->in(WP_CLI_VENDOR_DIR . '/symfony/process')
		->in(WP_CLI_VENDOR_DIR . '/justinrainbow/json-schema')
		->exclude('nb/oxymel/OxymelTest.php')
		->exclude('composer/spdx-licenses')
		->exclude('composer/composer/src/Composer/Command')
		->exclude('composer/composer/src/Composer/Compiler.php')
		->exclude('composer/composer/src/Composer/Console')
		->exclude('composer/composer/src/Composer/Downloader/PearPackageExtractor.php') // Assuming Pear installation isn't supported by wp-cli.
		->exclude('composer/composer/src/Composer/Installer/PearBinaryInstaller.php')
		->exclude('composer/composer/src/Composer/Installer/PearInstaller.php')
		->exclude('composer/composer/src/Composer/Question')
		->exclude('composer/composer/src/Composer/Repository/Pear')
		->exclude('composer/composer/src/Composer/SelfUpdate')
		;
}

foreach ( $finder as $file ) {
	add_file( $phar, $file );
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

if ( 'cli' !== BUILD ) {
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
}

add_file( $phar, WP_CLI_VENDOR_DIR . '/autoload.php' );
add_file( $phar, WP_CLI_VENDOR_DIR . '/autoload_commands.php' );
add_file( $phar, WP_CLI_VENDOR_DIR . '/autoload_framework.php' );
if ( 'cli' !== BUILD ) {
	add_file( $phar, WP_CLI_ROOT . '/ci/behat-tags.php' );
	add_file( $phar, WP_CLI_VENDOR_DIR . '/composer/composer/LICENSE' );
	add_file( $phar, WP_CLI_VENDOR_DIR . '/composer/composer/res/composer-schema.json' );
}
add_file( $phar, WP_CLI_VENDOR_DIR . '/rmccue/requests/library/Requests/Transport/cacert.pem' );

set_file_contents( $phar, WP_CLI_ROOT . '/VERSION', $current_version );

$phar_boot = str_replace( WP_CLI_BASE_PATH, '', WP_CLI_ROOT . '/php/boot-phar.php' );
$phar->setStub( <<<EOB
#!/usr/bin/env php
<?php
Phar::mapPhar();
include 'phar://wp-cli.phar{$phar_boot}';
__HALT_COMPILER();
?>
EOB
);

$phar->stopBuffering();

chmod( DEST_PATH, 0755 ); // Make executable.

if ( ! BE_QUIET ) {
	echo "Generated " . DEST_PATH . "\n";
}
