<?php
use \Composer\Composer;
use \Composer\Config;
use \Composer\Config\JsonConfigSource;
use \Composer\DependencyResolver\Pool;
use \Composer\EventDispatcher\Event;
use \Composer\Factory;
use \Composer\IO\NullIO;
use \Composer\Installer;
use \Composer\Json\JsonFile;
use \Composer\Json\JsonManipulator;
use \Composer\Package;
use \Composer\Package\BasePackage;
use \Composer\Package\PackageInterface;
use \Composer\Package\Version\VersionParser;
use \Composer\Package\Version\VersionSelector;
use \Composer\Repository;
use \Composer\Repository\CompositeRepository;
use \Composer\Repository\ComposerRepository;
use \Composer\Repository\RepositoryManager;
use \Composer\Util\Filesystem;
use \WP_CLI\ComposerIO;
use \WP_CLI\Extractor;
use \WP_CLI\Utils;

/**
 * Manage WP-CLI packages.
 *
 * WP-CLI packages are community-maintained projects built on WP-CLI. They can
 * contain WP-CLI commands, but they can also just extend WP-CLI in some way.
 *
 * Installable packages are listed in the
 * [Package Index](http://wp-cli.org/package-index/).
 *
 * Learn how to create your own command from the
 * [Commands Cookbook](http://wp-cli.org/docs/commands-cookbook/)
 *
 * ## EXAMPLES
 *
 *     # List installed packages
 *     $ wp package list
 *     +-----------------------+------------------------------------------+---------+------------+
 *     | name                  | description                              | authors | version    |
 *     +-----------------------+------------------------------------------+---------+------------+
 *     | wp-cli/server-command | Start a development server for WordPress |         | dev-master |
 *     +-----------------------+------------------------------------------+---------+------------+
 *
 *     # Install the latest development version of the package
 *     $ wp package install wp-cli/server-command
 *     Installing package wp-cli/server-command (dev-master)
 *     Updating /home/person/.wp-cli/packages/composer.json to require the package...
 *     Using Composer to install the package...
 *     ---
 *     Loading composer repositories with package information
 *     Updating dependencies
 *     Resolving dependencies through SAT
 *     Dependency resolution completed in 0.005 seconds
 *     Analyzed 732 packages to resolve dependencies
 *     Analyzed 1034 rules to resolve dependencies
 *      - Installing package
 *     Writing lock file
 *     Generating autoload files
 *     ---
 *     Success: Package installed.
 *
 *     # Uninstall package
 *     $ wp package uninstall wp-cli/server-command
 *     Removing require statement from /home/person/.wp-cli/packages/composer.json
 *     Deleting package directory /home/person/.wp-cli/packages/vendor/wp-cli/server-command
 *     Regenerating Composer autoload.
 *     Success: Uninstalled package.
 *
 * @package WP-CLI
 *
 * @when before_wp_load
 */
class Package_Command extends WP_CLI_Command {

	const PACKAGE_INDEX_URL = 'https://wp-cli.org/package-index/';

	private $pool = false;

	/**
	 * Browse WP-CLI packages available for installation.
	 *
	 * Lists packages available for installation from the [Package Index](http://wp-cli.org/package-index/).
	 *
	 * ## OPTIONS
	 *
	 * [--fields=<fields>]
	 * : Limit the output to specific fields. Defaults to all fields.
	 *
	 * [--format=<format>]
	 * : Render output in a particular format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - ids
	 *   - json
	 *   - yaml
	 * ---
	 *
	 * ## AVAILABLE FIELDS
	 *
	 * These fields will be displayed by default for each package:
	 *
	 * * name
	 * * description
	 * * authors
	 * * version
	 *
	 * There are no optionally available fields.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp package browse --format=yaml
	 *     ---
	 *     10up/mu-migration:
	 *       name: 10up/mu-migration
	 *       description: A set of WP-CLI commands to support the migration of single WordPress instances to multisite
	 *       authors: Nícholas André
	 *       version: dev-master, dev-develop
	 *     aaemnnosttv/wp-cli-dotenv-command:
	 *       name: aaemnnosttv/wp-cli-dotenv-command
	 *       description: Dotenv commands for WP-CLI
	 *       authors: Evan Mattson
	 *       version: v0.1, v0.1-beta.1, v0.2, dev-master, dev-dev, dev-develop, dev-tests/behat
	 *     aaemnnosttv/wp-cli-http-command:
	 *       name: aaemnnosttv/wp-cli-http-command
	 *       description: WP-CLI command for using the WordPress HTTP API
	 *       authors: Evan Mattson
	 *       version: dev-master
	 *
	 */
	public function browse( $_, $assoc_args ) {
		$this->show_packages( 'browse', $this->get_community_packages(), $assoc_args );
	}

	/**
	 * Install a WP-CLI package.
	 *
	 * Packages are required to be a valid Composer package, and can be
	 * specified as:
	 *
	 * * Package name from WP-CLI's package index.
	 * * Git URL accessible by the current shell user.
	 * * Path to a directory on the local machine.
	 * * Local or remote .zip file.
	 *
	 * When installing a local directory, WP-CLI simply registers a
	 * reference to the directory. If you move or delete the directory, WP-CLI's
	 * reference breaks.
	 *
	 * When installing a .zip file, WP-CLI extracts the package to
	 * `~/.wp-cli/packages/local/<package-name>`.
	 *
	 * ## OPTIONS
	 *
	 * <name|git|path|zip>
	 * : Name, git URL, directory path, or .zip file for the package to install.
	 * Names can optionally include a version constraint
	 * (e.g. wp-cli/server-command:@stable).
	 *
	 * ## EXAMPLES
	 *
	 *     # Install the latest development version from the package index.
	 *     $ wp package install wp-cli/server-command
	 *     Installing package wp-cli/server-command (dev-master)
	 *     Updating /home/person/.wp-cli/packages/composer.json to require the package...
	 *     Using Composer to install the package...
	 *     ---
	 *     Loading composer repositories with package information
	 *     Updating dependencies
	 *     Resolving dependencies through SAT
	 *     Dependency resolution completed in 0.005 seconds
	 *     Analyzed 732 packages to resolve dependencies
	 *     Analyzed 1034 rules to resolve dependencies
	 *      - Installing package
	 *     Writing lock file
	 *     Generating autoload files
	 *     ---
	 *     Success: Package installed.
	 *
	 *     # Install the latest stable version.
	 *     $ wp package install wp-cli/server-command:@stable
	 *
	 *     # Install a package hosted at a git URL.
	 *     $ wp package install git@github.com:runcommand/hook.git
	 *
	 *     # Install a package in a .zip file.
	 *     $ wp package install google-sitemap-generator-cli.zip
	 */
	public function install( $args, $assoc_args ) {
		list( $package_name ) = $args;

		$git_package = $dir_package = false;
		$version = 'dev-master';
		if ( '.git' === strtolower( substr( $package_name, -4, 4 ) ) ) {
			$git_package = $package_name;
			preg_match( '#([^:\/]+\/[^\/]+)\.git#', $package_name, $matches );
			if ( ! empty( $matches[1] ) ) {
				$package_name = $matches[1];
			} else {
				WP_CLI::error( "Couldn't parse package name from expected path '<name>/<package>'." );
			}
		} else if ( ( false !== strpos( $package_name, '://' ) && false !== stripos( $package_name, '.zip' ) )
			|| ( pathinfo( $package_name, PATHINFO_EXTENSION ) === 'zip' && is_file( $package_name ) ) ) {
			// Download the remote ZIP file to a temp directory
			if ( false !== strpos( $package_name, '://' ) ) {
				$temp = Utils\get_temp_dir() . uniqid('package_') . ".zip";
				$options = array(
					'timeout' => 600,
					'filename' => $temp
				);
				$response = Utils\http_request( 'GET', $package_name, null, array(), $options );
				if ( 20 != substr( $response->status_code, 0, 2 ) ) {
					WP_CLI::error( "Couldn't download package." );
				}
				$package_name = $temp;
			}
			$dir_package = Utils\get_temp_dir() . uniqid( 'package_' );
			try {
				// Extract the package to get the package name
				Extractor::extract( $package_name, $dir_package );
				list( $package_name, $version ) = self::get_package_name_and_version_from_dir_package( $dir_package );
				// Move to a location based on the package name
				$local_dir = rtrim( WP_CLI::get_runner()->get_packages_dir_path(), '/' ) . '/local/';
				$actual_dir_package = $local_dir . str_replace( '/', '-', $package_name );
				Extractor::copy_overwrite_files( $dir_package, $actual_dir_package );
				Extractor::rmdir( $dir_package );
				// Behold, the extracted package
				$dir_package = $actual_dir_package;
			} catch ( Exception $e ) {
				WP_CLI::error( $e->getMessage() );
			}
		} else if ( is_dir( $package_name ) && file_exists( $package_name . '/composer.json' ) ) {
			$dir_package = $package_name;
			if ( ! Utils\is_path_absolute( $dir_package ) ) {
				$dir_package = getcwd() . DIRECTORY_SEPARATOR . $dir_package;
			}
			list( $package_name, $version ) = self::get_package_name_and_version_from_dir_package( $dir_package );
		} else {
			if ( false !== strpos( $package_name, ':' ) ) {
				list( $package_name, $version ) = explode( ':', $package_name );
			}
			$package = $this->get_community_package_by_name( $package_name );
			if ( ! $package ) {
				WP_CLI::error( "Invalid package." );
			}
		}

		WP_CLI::log( sprintf( "Installing package %s (%s)", $package_name, $version ) );

		$composer_json_obj = $this->get_composer_json();

		// Add the 'require' to composer.json
		WP_CLI::log( sprintf( "Updating %s to require the package...", $composer_json_obj->getPath() ) );
		$composer_backup = file_get_contents( $composer_json_obj->getPath() );
		$json_manipulator = new JsonManipulator( $composer_backup );
		$json_manipulator->addMainKey( 'name', 'wp-cli/wp-cli' );
		$json_manipulator->addMainKey( 'version', self::get_wp_cli_version_composer() );
		$json_manipulator->addLink( 'require', $package_name, $version );
		$json_manipulator->addConfigSetting( 'secure-http', true );

		if ( $git_package ) {
			WP_CLI::log( sprintf( 'Registering %s as a VCS repository...', $git_package ) );
			$json_manipulator->addRepository( $package_name, array( 'type' => 'vcs', 'url' => $git_package ) );
		} else if ( $dir_package ) {
			WP_CLI::log( sprintf( 'Registering %s as a path repository...', $dir_package ) );
			$json_manipulator->addRepository( $package_name, array( 'type' => 'path', 'url' => $dir_package ) );
		}

		$composer_backup_decoded = json_decode( $composer_backup, true );
		// If the composer file does not contain the current package index repository, refresh the repository definition.
		if ( empty( $composer_backup_decoded['repositories']['wp-cli']['url'] ) || self::PACKAGE_INDEX_URL != $composer_backup_decoded['repositories']['wp-cli']['url'] ) {
			WP_CLI::log( 'Updating package index repository url...' );
			$json_manipulator->addRepository( 'wp-cli', array( 'type' => 'composer', 'url' => self::PACKAGE_INDEX_URL ) );
		}

		file_put_contents( $composer_json_obj->getPath(), $json_manipulator->getContents() );
		try {
			$composer = $this->get_composer();
		} catch( Exception $e ) {
			WP_CLI::error( $e->getMessage() );
		}

		// Set up the EventSubscriber
		$event_subscriber = new \WP_CLI\PackageManagerEventSubscriber;
		$composer->getEventDispatcher()->addSubscriber( $event_subscriber );

		// Set up the installer
		$install = Installer::create( new ComposerIO, $composer );
		$install->setUpdate( true ); // Installer class will only override composer.lock with this flag
		$install->setPreferSource( true ); // Use VCS when VCS for easier contributions.

		// Try running the installer, but revert composer.json if failed
		WP_CLI::log( 'Using Composer to install the package...' );
		WP_CLI::log( '---' );
		$res = false;
		try {
			$res = $install->run();
		} catch ( Exception $e ) {
			WP_CLI::warning( $e->getMessage() );
		}
		WP_CLI::log( '---' );

		if ( 0 === $res ) {
			WP_CLI::success( "Package installed." );
		} else {
			file_put_contents( $composer_json_obj->getPath(), $composer_backup );
			WP_CLI::error( "Package installation failed (Composer return code {$res}). Reverted composer.json" );
		}
	}

	/**
	 * List installed WP-CLI packages.
	 *
	 * ## OPTIONS
	 *
	 * [--fields=<fields>]
	 * : Limit the output to specific fields. Defaults to all fields.
	 *
	 * [--format=<format>]
	 * : Render output in a particular format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - ids
	 *   - json
	 *   - yaml
	 * ---
	 *
	 * ## AVAILABLE FIELDS
	 *
	 * These fields will be displayed by default for each package:
	 *
	 * * name
	 * * authors
	 * * version
	 * * update
	 * * update_version
	 *
	 * These fields are optionally available:
	 *
	 * * description
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp package list
	 *     +-----------------------+------------------------------------------+---------+------------+
	 *     | name                  | description                              | authors | version    |
	 *     +-----------------------+------------------------------------------+---------+------------+
	 *     | wp-cli/server-command | Start a development server for WordPress |         | dev-master |
	 *     +-----------------------+------------------------------------------+---------+------------+
	 *
	 * @subcommand list
	 */
	public function list_( $args, $assoc_args ) {
		$this->show_packages( 'list', $this->get_installed_packages(), $assoc_args );
	}

	/**
	 * Get the path to an installed WP-CLI package, or the package directory.
	 *
	 * If you want to contribute to a package, this is a great way to jump to it.
	 *
	 * ## OPTIONS
	 *
	 * [<name>]
	 * : Name of the package to get the directory for.
	 *
	 * ## EXAMPLES
	 *
	 *     # Get package path
	 *     $ wp package path
	 *     /home/person/.wp-cli/packages/
	 *
	 *     # Change directory to package path
	 *     $ cd $(wp package path) && pwd
	 *     /home/vagrant/.wp-cli/packages
	 */
	function path( $args ) {
		$packages_dir = WP_CLI::get_runner()->get_packages_dir_path();
		if ( ! empty( $args ) ) {
			$packages_dir .= 'vendor/' . $args[0];
			if ( ! is_dir( $packages_dir ) ) {
				WP_CLI::error( 'Invalid package name.' );
			}
		}
		WP_CLI::line( $packages_dir );
	}

	/**
	 * Update all installed WP-CLI packages to their latest version.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp package update
	 *     Using Composer to update packages...
	 *     ---
	 *     Loading composer repositories with package information
	 *     Updating dependencies
	 *     Resolving dependencies through SAT
	 *     Dependency resolution completed in 0.074 seconds
	 *     Analyzed 1062 packages to resolve dependencies
	 *     Analyzed 22383 rules to resolve dependencies
	 *     Writing lock file
	 *     Generating autoload files
	 *     ---
	 *     Success: Packages updated.
	 */
	public function update() {
		try {
			$composer = $this->get_composer();
		} catch( Exception $e ) {
			WP_CLI::error( $e->getMessage() );
		}
		// Set up the EventSubscriber
		$event_subscriber = new \WP_CLI\PackageManagerEventSubscriber;
		$composer->getEventDispatcher()->addSubscriber( $event_subscriber );

		// Set up the installer
		$install = Installer::create( new ComposerIO, $composer );
		$install->setUpdate( true ); // Installer class will only override composer.lock with this flag
		$install->setPreferSource( true ); // Use VCS when VCS for easier contributions.
		WP_CLI::log( 'Using Composer to update packages...' );
		WP_CLI::log( '---' );
		$res = false;
		try {
			$res = $install->run();
		} catch ( Exception $e ) {
			WP_CLI::warning( $e->getMessage() );
		}
		WP_CLI::log( '---' );

		if ( 0 === $res ) {
			WP_CLI::success( "Packages updated." );
		} else {
			WP_CLI::error( "Failed to update packages (Composer return code {$res})." );
		}
	}

	/**
	 * Uninstall a WP-CLI package.
	 *
	 * ## OPTIONS
	 *
	 * <name>
	 * : Name of the package to uninstall.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp package uninstall wp-cli/server-command
	 *     Removing require statement from /home/person/.wp-cli/packages/composer.json
	 *     Deleting package directory /home/person/.wp-cli/packages/vendor/wp-cli/server-command
	 *     Regenerating Composer autoload.
	 *     Success: Uninstalled package.
	 */
	public function uninstall( $args ) {
		list( $package_name ) = $args;

		try {
			$composer = $this->get_composer();
		} catch( Exception $e ) {
			WP_CLI::error( $e->getMessage() );
		}
		if ( false === ( $package = $this->get_installed_package_by_name( $package_name ) ) ) {
			WP_CLI::error( "Package not installed." );
		}

		$composer_json_obj = $this->get_composer_json();

		// Remove the 'require' from composer.json
		$json_path = $composer_json_obj->getPath();
		WP_CLI::log( sprintf( 'Removing require statement from %s', $json_path ) );
		$composer_backup = file_get_contents( $composer_json_obj->getPath() );
		$manipulator = new JsonManipulator( $composer_backup );
		$manipulator->removeSubNode( 'require', $package_name );
		file_put_contents( $composer_json_obj->getPath(), $manipulator->getContents() );

		try {
			$composer = $this->get_composer();
		} catch( Exception $e ) {
			WP_CLI::error( $e->getMessage() );
		}

		// Set up the installer
		$install = Installer::create( new NullIO, $composer );
		$install->setUpdate( true ); // Installer class will only override composer.lock with this flag
		$install->setPreferSource( true ); // Use VCS when VCS for easier contributions.

		WP_CLI::log( 'Removing package directories and regenerating autoloader...' );
		$res = false;
		try {
			$res = $install->run();
		} catch ( Exception $e ) {
			WP_CLI::warning( $e->getMessage() );
		}

		if ( 0 === $res ) {
			WP_CLI::success( "Uninstalled package." );
		} else {
			file_put_contents( $composer_json_obj->getPath(), $composer_backup );
			WP_CLI::error( "Package removal failed (Composer return code {$res})." );
		}
	}

	/**
	 * Check whether a package is a WP-CLI community package based
	 * on membership in our package index.
	 *
	 * @param object      $package     A package object
	 * @return bool
	 */
	private function is_community_package( $package ) {
		return $this->package_index()->hasPackage( $package );
	}

	/**
	 * Get a Composer instance.
	 */
	private function get_composer() {
		$composer_path = $this->get_composer_json_path();

		// Composer's auto-load generating code makes some assumptions about where
		// the 'vendor-dir' is, and where Composer is running from.
		// Best to just pretend we're installing a package from ~/.wp-cli or similar
		chdir( pathinfo( $composer_path, PATHINFO_DIRNAME ) );

		// Prevent DateTime error/warning when no timezone set
		date_default_timezone_set( @date_default_timezone_get() );

		return Factory::create( new NullIO, $composer_path );
	}

	/**
	 * Get all of the community packages.
	 *
	 * @return array
	 */
	private function get_community_packages() {
		static $community_packages;

		if ( null === $community_packages ) {
			try {
				$community_packages = $this->package_index()->getPackages();
			} catch( Exception $e ) {
				WP_CLI::error( $e->getMessage() );
			}
		}

		return $community_packages;
	}

	/**
	 * Get the package index instance
	 *
	 * We need to construct the instance manually, because there's no way to select
	 * a particular instance using $composer->getRepositoryManager()
	 *
	 * @return ComposerRepository
	 */
	private function package_index() {
		static $package_index;

		if ( !$package_index ) {
			$config = new Config();
			$config->merge( array(
				'config' => array(
					'secure-http' => true,
					'home' => dirname( $this->get_composer_json_path() ),
				)
			));
			$config->setConfigSource( new JsonConfigSource( $this->get_composer_json() ) );

			try {
				$package_index = new ComposerRepository( array( 'url' => self::PACKAGE_INDEX_URL ), new NullIO, $config );
			} catch ( Exception $e ) {
				WP_CLI::error( $e->getMessage() );
			}
		}

		return $package_index;
	}

	/**
	 * Display a set of packages
	 *
	 * @param string $context
	 * @param array
	 * @param array
	 */
	private function show_packages( $context, $packages, $assoc_args ) {
		if ( 'list' === $context ) {
			$default_fields = array(
				'name',
				'authors',
				'version',
				'update',
				'update_version',
			);
		} else if ( 'browse' === $context ) {
			$default_fields = array(
				'name',
				'description',
				'authors',
				'version',
			);
		}
		$defaults = array(
			'fields' => implode( ',', $default_fields ),
			'format' => 'table'
		);
		$assoc_args = array_merge( $defaults, $assoc_args );

		$list = array();
		foreach ( $packages as $package ) {
			$name = $package->getName();
			if ( isset( $list[ $name ] ) ) {
				$list[ $name ]['version'][] = $package->getPrettyVersion();
			} else {
				$package_output = array();
				$package_output['name'] = $package->getName();
				$package_output['description'] = $package->getDescription();
				$package_output['authors'] = implode( ', ', array_column( (array) $package->getAuthors(), 'name' ) );
				$package_output['version'] = array( $package->getPrettyVersion() );
				$update = 'none';
				$update_version = '';
				if ( 'list' === $context ) {
					$latest = $this->find_latest_package( $package, $this->get_composer(), null );
					if ( $latest && $latest->getFullPrettyVersion() !== $package->getFullPrettyVersion() ) {
						$update = 'available';
						$update_version = $latest->getPrettyVersion();
					}
				}
				$package_output['update'] = $update;
				$package_output['update_version'] = $update_version;
				$list[ $package_output['name'] ] = $package_output;
			}
		}

		$list = array_map( function( $package ){
			$package['version'] = implode( ', ', $package['version'] );
			return $package;
		}, $list );

		ksort( $list );
		if ( 'ids' === $assoc_args['format'] ) {
			$list = array_keys( $list );
		}
		WP_CLI\Utils\format_items( $assoc_args['format'], $list, $assoc_args['fields'] );
	}

	/**
	 * Get a community package by its name.
	 */
	private function get_community_package_by_name( $package_name ) {
		foreach( $this->get_community_packages() as $package ) {
			if ( $package_name == $package->getName() ) {
				return $package;
			}
		}
		return false;
	}

	/**
	 * Get the installed community packages.
	 */
	private function get_installed_packages() {
		try {
			$composer = $this->get_composer();
		} catch( Exception $e ) {
			WP_CLI::error( $e->getMessage() );
		}
		$repo = $composer->getRepositoryManager()->getLocalRepository();
		$installed_packages = array();
		$existing = json_decode( file_get_contents( $this->get_composer_json_path() ), true );
		$installed_package_keys = ! empty( $existing['require'] ) ? array_keys( $existing['require'] ) : array();
		if ( empty( $installed_package_keys ) ) {
			return array();
		}
		$installed_packages = array();
		foreach( $repo->getCanonicalPackages() as $package ) {
			if ( in_array( $package->getName(), $installed_package_keys, true ) ) {
				$installed_packages[] = $package;
			}
		}
		return $installed_packages;
	}

	/**
	 * Get an installed package by its name.
	 */
	private function get_installed_package_by_name( $package_name ) {
		foreach( $this->get_installed_packages() as $package ) {
			if ( $package_name == $package->getName() ) {
				return $package;
			}
		}
		return false;
	}

	/**
	 * Check if the package name provided is already installed.
	 */
	private function is_package_installed( $package_name ) {
		if ( $this->get_installed_package_by_name( $package_name ) ) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Get the name of the package from the composer.json in a directory path
	 *
	 * @param string $dir_package
	 * @return string
	 */
	private static function get_package_name_and_version_from_dir_package( $dir_package ) {
		$composer_file = $dir_package . '/composer.json';
		$package_name = '';
		$version = 'dev-master';
		if ( file_exists( $composer_file ) ) {
			$composer_data = json_decode( file_get_contents( $composer_file ), true );
			if ( ! empty( $composer_data['name'] ) ) {
				$package_name = $composer_data['name'];
			}
			if ( ! empty( $composer_data['version'] ) ) {
				$version = $composer_data['version'];
			}
		}
		if ( empty( $package_name ) ) {
			WP_CLI::error( "Invalid package." );
		}
		return array( $package_name, $version );
	}

	/**
	 * Get the composer.json object
	 */
	private function get_composer_json() {
		return new JsonFile( $this->get_composer_json_path() );
	}

	/**
	 * Get the path to composer.json
	 */
	private function get_composer_json_path() {
		static $composer_path;

		if ( null === $composer_path ) {

			if ( getenv( 'WP_CLI_PACKAGES_DIR' ) ) {
				$composer_path = rtrim( getenv( 'WP_CLI_PACKAGES_DIR' ), '/' ) . '/composer.json';
			} else {
				$composer_path = getenv( 'HOME' ) . '/.wp-cli/packages/composer.json';
			}

			// `composer.json` and its directory might need to be created
			if ( ! file_exists( $composer_path ) ) {
				$this->create_default_composer_json( $composer_path );
			}
		}

		return $composer_path;
	}

	/**
	 * Get the WP-CLI version for composer.json
	 */
	private static function get_wp_cli_version_composer() {
		preg_match( '#^[0-9\.]+(-(alpha|beta)[^-]{0,})?#', WP_CLI_VERSION, $matches );
		return isset( $matches[0] ) ? $matches[0] : '';
	}

	/**
	 * Create a default composer.json, should one not already exist
	 *
	 * @param string $composer_path Where the composer.json should be created
	 * @return true|WP_Error
	 */
	private function create_default_composer_json( $composer_path ) {

		$composer_dir = pathinfo( $composer_path, PATHINFO_DIRNAME );
		if ( ! is_dir( $composer_dir ) ) {
			\WP_CLI\Process::create( WP_CLI\Utils\esc_cmd( 'mkdir -p %s', $composer_dir ) )->run();
		}

		if ( ! is_dir( $composer_dir ) ) {
			WP_CLI::error( "Composer directory for packages couldn't be created." );
		}

		$json_file = new JsonFile( $composer_path );

		$author = (object)array(
			'name'   => 'WP-CLI',
			'email'  => 'noreply@wpcli.org'
		);

		$repositories = (object)array(
			'wp-cli'     => (object)array(
				'type'      => 'composer',
				'url'       => self::PACKAGE_INDEX_URL,
			),
		);

		$options = array(
			'name' => 'wp-cli/wp-cli',
			'description' => 'Installed community packages used by WP-CLI',
			'version' => self::get_wp_cli_version_composer(),
			'authors' => array( $author ),
			'homepage' => self::PACKAGE_INDEX_URL,
			'require' => new stdClass,
			'require-dev' => new stdClass,
			'minimum-stability' => 'dev',
			'license' => 'MIT',
			'repositories' => $repositories,
		);

		try {
			$json_file->write( $options );
		} catch( Exception $e ) {
			WP_CLI::error( $e->getMessage() );
		}

		return true;
	}

	/**
	 * Given a package, this finds the latest package matching it
	 *
	 * @param  PackageInterface $package
	 * @param  Composer         $composer
	 * @param  string           $phpVersion
	 * @param  bool             $minorOnly
	 *
	 * @return PackageInterface|null
	 */
	private function find_latest_package( PackageInterface $package, Composer $composer, $phpVersion, $minorOnly = false ) {
		// find the latest version allowed in this pool
		$name = $package->getName();
		$versionSelector = new VersionSelector($this->get_pool($composer));
		$stability = $composer->getPackage()->getMinimumStability();
		$flags = $composer->getPackage()->getStabilityFlags();
		if (isset($flags[$name])) {
			$stability = array_search($flags[$name], BasePackage::$stabilities, true);
		}
		$bestStability = $stability;
		if ($composer->getPackage()->getPreferStable()) {
			$bestStability = $package->getStability();
		}
		$targetVersion = null;
		if (0 === strpos($package->getVersion(), 'dev-')) {
			$targetVersion = $package->getVersion();
		}
		if ($targetVersion === null && $minorOnly) {
			$targetVersion = '^' . $package->getVersion();
		}
		return $versionSelector->findBestCandidate($name, $targetVersion, $phpVersion, $bestStability);
	}

	private function get_pool( Composer $composer ) {
		if (!$this->pool) {
			$this->pool = new Pool($composer->getPackage()->getMinimumStability(), $composer->getPackage()->getStabilityFlags());
			$this->pool->addRepository(new CompositeRepository($composer->getRepositoryManager()->getRepositories()));
		}
		return $this->pool;
	}
}

WP_CLI::add_command( 'package', 'Package_Command' );
