<?php
use \Composer\Config;
use \Composer\Config\JsonConfigSource;
use \Composer\EventDispatcher\Event;
use \Composer\Factory;
use \Composer\IO\NullIO;
use \Composer\Installer;
use \Composer\Json\JsonFile;
use \Composer\Json\JsonManipulator;
use \Composer\Package;
use \Composer\Package\Version\VersionParser;
use \Composer\Repository;
use \Composer\Repository\CompositeRepository;
use \Composer\Repository\ComposerRepository;
use \Composer\Repository\RepositoryManager;
use \Composer\Util\Filesystem;
use \WP_CLI\ComposerIO;

/**
 * Manage WP-CLI community packages.
 *
 * @package WP-CLI
 *
 * @when before_wp_load
 */
class Package_Command extends WP_CLI_Command {

	// TODO: read from composer.json
	const PACKAGE_INDEX_URL = 'http://wp-cli.org/package-index/';

	private $fields = array(
		'name',
		'description',
		'authors',
	);

	private function _show_packages( $packages, $assoc_args ) {
		$defaults = array(
			'fields' => implode( ',', $this->fields ),
			'format' => 'table'
		);
		$assoc_args = array_merge( $defaults, $assoc_args );

		$list = array();
		foreach ( $packages as $package ) {
			$package_output = new stdClass;
			$package_output->name = $package->getName();
			$package_output->description = $package->getDescription();
			$package_output->authors = implode( ',', array_column( (array) $package->getAuthors(), 'name' ) );
			$list[$package_output->name] = $package_output;
		}

		WP_CLI\Utils\format_items( $assoc_args['format'], $list, $assoc_args['fields'] );
	}

	/**
	 * Browse available WP-CLI community packages.
	 *
	 * @subcommand browse
	 * @synopsis [--format=<format>]
	 */
	public function browse( $_, $assoc_args ) {
		$this->_show_packages( $this->get_community_packages(), $assoc_args );
	}

	/**
	 * Install a WP-CLI community package.
	 *
	 * @subcommand install
	 * @synopsis <package-name> [--version=<version>]
	 */
	public function install( $args, $assoc_args ) {
		list( $package_name ) = $args;

		$defaults = array(
			'version' => 'dev-master',
		);
		$assoc_args = array_merge( $defaults, $assoc_args );

		$package = $this->get_community_package_by_name( $package_name );
		if ( ! $package )
			WP_CLI::error( "Invalid package." );
		else
			WP_CLI::line( sprintf( "Installing %s (%s)", $package_name, $assoc_args['version'] ) );

		$composer = $this->get_composer();
		$composer_json_obj = $this->get_composer_json();

		// Add the 'require' to composer.json
		WP_CLI::line( "Updating composer.json to require the package..." );
		$composer_backup = file_get_contents( $composer_json_obj->getPath() );
		$json_manipulator = new JsonManipulator( $composer_backup );
		$json_manipulator->addLink( 'require', $package_name, $assoc_args['version'] );
		file_put_contents( $composer_json_obj->getPath(), $json_manipulator->getContents() );
		$composer = $this->get_composer();

		// Set up the EventSubscriber
		$event_subscriber = new \WP_CLI\PackageManagerEventSubscriber;
		$composer->getEventDispatcher()->addSubscriber( $event_subscriber );

		// Set up the installer
		$install = Installer::create( new ComposerIO, $composer );
		$install->setUpdate( true ); // Installer class will only override composer.lock with this flag

		// Try running the installer, but revert composer.json if failed
		WP_CLI::line( "Using Composer to install the package..." );
		try {
			$res = $install->run();
		} catch ( Exception $e ) {
			WP_CLI::error( $e->getMessage() );
		}

		if ( 0 === $res ) {
			WP_CLI::success( "Package installed successfully." );
		} else {
			file_put_contents( $composer_json_obj->getPath(), $composer_backup );
			WP_CLI::error( "Package installation failed. Reverted composer.json" );
		}
	}

	/**
	 * List installed WP-CLI community packages.
	 *
	 * @subcommand list
	 * @synopsis [--format=<format>]
	 */
	public function _list( $args, $assoc_args ) {
		$this->_show_packages( $this->get_installed_packages(), $assoc_args );
	}

	/**
	 * Uninstall a WP-CLI community package.
	 *
	 * @subcommand uninstall
	 * @synopsis <package-name>
	 */
	public function uninstall( $args ) {
		list( $package_name ) = $args;

		$composer = $this->get_composer();
		if ( false === ( $package = $this->get_installed_package_by_name( $package_name ) ) )
			WP_CLI::error( "Package not installed." );

		$composer_json_obj = $this->get_composer_json();

		// Remove the 'require' from composer.json
		$contents = file_get_contents( $composer_json_obj->getPath() );
		$manipulator = new JsonManipulator( $contents );
		$manipulator->removeSubNode( 'require', $package_name );
		file_put_contents( $composer_json_obj->getPath(), $manipulator->getContents() );

		// Delete the directory
		$filesystem = new Filesystem;
		$package_path = getcwd() . '/' . $composer->getConfig()->get('vendor-dir') . '/' . $package->getName();
		$filesystem->removeDirectory( $package_path );

		// Reset Composer and regenerate the auto-loader
		$composer = $this->get_composer();
		$this->regenerate_autoloader();

		WP_CLI::success( "Uninstalled package." );
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

		try {
			$composer = Factory::create( new NullIO, $composer_path );
		} catch( Exception $e ) {
			WP_CLI::error( $e->getMessage() );
		}

		$this->composer = $composer;
		return $this->composer;
	}

	/**
	 * Get all of the community packages.
	 */
	private function get_community_packages() {
		static $community_packages;

		if ( null === $community_packages ) {
			$community_packages = $this->package_index()->getPackages();
		}

		return $community_packages;
	}

	// We need to construct the instance manually, because there's no way to select
	// a particular instance using $composer->getRepositoryManager()
	private function package_index() {
		static $package_index;

		if ( !$package_index ) {
			$config = new Config();
			$config->merge(array('config' => array(
				'home' => dirname( $this->get_composer_json_path() ),
				/* 'cache-dir' => $cacheDir */
			)));
			$config->setConfigSource( new JsonConfigSource( $this->get_composer_json() ) );

			$package_index = new ComposerRepository( array( 'url' => self::PACKAGE_INDEX_URL ), new NullIO, $config );
		}

		return $package_index;
	}

	/**
	 * Get a community package by its name.
	 */
	private function get_community_package_by_name( $package_name ) {
		foreach( $this->get_community_packages() as $package ) {
			if ( $package_name == $package->getName() )
				return $package;
		}
		return false;
	}

	/**
	 * Get the installed community packages.
	 */
	private function get_installed_packages() {
		$composer = $this->get_composer();
		$repo = $composer->getRepositoryManager()->getLocalRepository();

		$installed_packages = array();
		foreach( $repo->getPackages() as $package ) {

			if ( ! $this->is_community_package( $package ) )
				continue;

			$installed_packages[] = $package;
		}

		return $installed_packages;
	}

	/**
	 * Get an installed package by its name.
	 */
	private function get_installed_package_by_name( $package_name ) {
		foreach( $this->get_installed_packages() as $package ) {
			if ( $package_name == $package->getName() )
				return $package;
		}
		return false;
	}

	/**
	 * Check if the package name provided is already installed.
	 */
	private function is_package_installed( $package_name ) {
		if ( $this->get_installed_package_by_name( $package_name ) )
			return true;
		else
			return false;
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

			$composer_path = $this->find_composer_json_path();

			// `composer.json` and its directory might need to be created
			if ( ! file_exists( $composer_path ) ) {

				$retval = $this->create_default_composer_json( $composer_path );
				if ( is_wp_error( $retval ) ) {
					WP_CLI::error( $retval->get_error_message() );
				}

			}

		}

		return $composer_path;
	}

	/**
	 * Depending on how WP-CLI is installed, find where composer.json
	 * should exist.
	 * 
	 * @return string
	 */
	private function find_composer_json_path() {

		// Phar-based installs use a standalone WP-CLI directory
		if ( 0 === strpos( WP_CLI_ROOT, 'phar:' ) ) {
			$composer_path = getenv( 'HOME' ) . '/.wp-cli/composer.json';
		} else {
			$composer_path = dirname( dirname( dirname( WP_CLI_ROOT ) ) ) . '/composer.json';
		}

		return $composer_path;
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
			@mkdir( $composer_dir );
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
			'name' => 'wp-cli/wp-cli-community-packages',
			'description' => 'Installed community packages used by WP-CLI',
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
			return new WP_Error( 'composer-json-write', $e->getMessage() );
		}

		return true;
	}

	/**
	 * Regenerate the Composer autoloader
	 */
	private function regenerate_autoloader() {
		$this->composer->getAutoloadGenerator()->dump(
			$this->composer->getConfig(),
			$this->composer->getRepositoryManager()->getLocalRepository(),
			$this->composer->getPackage(),
			$this->composer->getInstallationManager(),
			'composer'
		);
	}
}

WP_CLI::add_command( 'package', 'Package_Command' );
