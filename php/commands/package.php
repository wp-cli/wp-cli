<?php

use WP_CLI\Utils;
use WP_CLI\Process;

/**
 * Manage community commands
 *
 * @package wp-cli
 */
class Package_Command extends WP_CLI_Command {
	private $home;
	private $current_patj;

	public function __construct() {
		$this->home    = getenv( 'HOME' ) . '/.wp-cli';
		$this->current = getcwd();
	}

	/**
	 * Install a community package
	 *
	 * ## OPTIONS
	 *
	 * <package>
	 * : the slug of the package
	 *
	 * [--repo=<url>]
	 * : The package repository url
	 *
	 * [--version=<version>]
	 * : The package version/tag
	 *
	 * @subcommand install
	 *
	 * @when before_wp_load
	 */
	public function install( $args, $assoc_args ) {
		extract( $assoc_args );
		$type = 'packagist';

		if ( ! isset( $version ) ) {
			$version = 'dev-master';
		}

		// when a repo has been provided set the type
		if ( isset( $repo ) ) {
			$type = 'vcs';
		}

		if ( ! file_exists( $this->home ) ) {
			mkdir( $this->home );
		}

		if ( ! file_exists( $this->home . '/composer.json' ) ) {
			$this->create_composer_file();
		}

		$package = $args[0];

		switch ( $type ) {
			case 'vcs':
				$this->install_vcs_package( $package, $version, $repo );
				break;

			default:
				$this->install_packagist_package( $package, $version );
				break;
		}

	}

	/**
	 * Remove a community package
	 *
	 * ## OPTIONS
	 *
	 * <package>
	 * : the slug of the package
	 *
	 * @subcommand remove
	 *
	 * @when before_wp_load
	 */
	public function remove( $args, $assoc_args ) {
		$package = $args[0];

		$this->remove_package( $package );
	}

	private function create_composer_file() {
		chdir( $this->home );

		exec( 'composer init --no-interaction --name="wp-cli/packages" --description="WP-CLI community packages"' );

		chdir( $this->current );
	}

	private function install_packagist_package( $package, $version ) {
		chdir( $this->home );

		// [TODO] implement packagist installs
		exec( "composer require {$package}:{$version}" );

		exec( 'composer update' );

		chdir( $this->current );
	}

	private function install_satis_package( $package, $type, $url ) {
		chdir( $this->home );

		// [TODO] implement Satis installs

		chdir( $this->current );
	}

	private function install_vcs_package( $package, $version, $repo_url ) {
		chdir( $this->home );

		// let composer know where to find this package
		exec( "composer config repositories.{$package} vcs {$repo_url}" );

		// add the package to the require dependencies
		exec( "composer require {$package}:{$version}" );

		// create/update the lock file
		exec( 'composer update' );

		chdir( $this->current );
	}

	private function remove_package( $package ) {
		chdir( $this->home );

		// let composer know where to find this package
		exec( "composer config --unset repositories.{$package} vcs {$repo_url}" );

		exec( "composer remove {$package}" );

		exec( 'composer update' );

		chdir( $this->current );
	}

}

WP_CLI::add_command( 'package', 'Package_Command' );
