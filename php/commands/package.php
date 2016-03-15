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
 * Manage WP-CLI packages.
 *
 * @package WP-CLI
 *
 * @when before_wp_load
 */
class Package_Command extends WP_CLI_Command {

	const PACKAGE_INDEX_URL = 'http://wp-cli.org/package-index/';

	private $fields = array(
		'name',
		'description',
		'authors',
		'version',
	);

	/**
	 * Browse WP-CLI packages available for installation.
	 *
	 * Lists packages available for installation from the [Package Index](http://wp-cli.org/package-index/).
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Accepted values: table, json, csv, yaml, ids. Default: table.
	 */
	public function browse( $_, $assoc_args ) {
		$this->show_packages( $this->get_community_packages(), $assoc_args );
	}

	/**
	 * Install a WP-CLI package.
	 *
	 * ## OPTIONS
	 *
	 * <name>
	 * : Name of the package to install. Can optionally contain a version constraint.
	 *
	 * ## EXAMPLES
	 *
	 *     # install the latest development version
	 *     wp package install wp-cli/server-command
	 *
	 *     # install the latest stable version
	 *     wp package install wp-cli/server-command:@stable
	 */
	public function install( $args, $assoc_args ) {
		list( $package_name ) = $args;

		if ( false !== strpos( $package_name, ':' ) ) {
			list( $package_name, $version ) = explode( ':', $package_name );
		} else {
			$version = 'dev-master';
		}

		$package = $this->get_community_package_by_name( $package_name );
		if ( ! $package ) {
			WP_CLI::error( "Invalid package." );
		} else {
			WP_CLI::log( sprintf( "Installing %s (%s)", $package_name, $version ) );
		}

		try {
			$composer = $this->get_composer();
		} catch( Exception $e ) {
			WP_CLI::error( $e->getMessage() );
		}
		$composer_json_obj = $this->get_composer_json();

		// Add the 'require' to composer.json
		WP_CLI::log( sprintf( "Updating %s to require the package...", $composer_json_obj->getPath() ) );
		$composer_backup = file_get_contents( $composer_json_obj->getPath() );
		$json_manipulator = new JsonManipulator( $composer_backup );
		$json_manipulator->addMainKey( 'name', 'wp-cli/wp-cli' );
		$json_manipulator->addLink( 'require', $package_name, $version );
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
		try {
			$res = $install->run();
		} catch ( Exception $e ) {
			WP_CLI::warning( $e->getMessage() );
		}
		WP_CLI::log( '---' );

		if ( 0 === $res ) {
			WP_CLI::success( "Package installed successfully." );
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
	 * [--format=<format>]
	 * : Accepted values: table, json, csv, yaml, ids. Default: table
	 *
	 * @subcommand list
	 */
	public function list_( $args, $assoc_args ) {
		$this->show_packages( $this->get_installed_packages(), $assoc_args );
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
	 *     cd $(wp package path)
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
	 * Uninstall a WP-CLI package.
	 *
	 * ## OPTIONS
	 *
	 * <name>
	 * : Name of the package to uninstall.
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
		$contents = file_get_contents( $json_path );
		$manipulator = new JsonManipulator( $contents );
		$manipulator->removeSubNode( 'require', $package_name );
		file_put_contents( $composer_json_obj->getPath(), $manipulator->getContents() );

		// Delete the directory
		$package_path = $composer->getConfig()->get( 'vendor-dir' ) . '/' . $package->getName();
		WP_CLI::log( sprintf( 'Deleting package directory %s', $package_path ) );
		$filesystem = new Filesystem;
		$filesystem->removeDirectory( $package_path );

		// Reset Composer and regenerate the auto-loader
		WP_CLI::log( 'Regenerating Composer autoload.' );
		try {
			$composer = $this->get_composer();
		} catch( Exception $e ) {
			WP_CLI::warning( $e->getMessage() );
			WP_CLI::error( 'Composer autoload will need to be manually regenerated.' );
		}
		$this->regenerate_autoloader( $composer );

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
			$community_packages = $this->package_index()->getPackages();
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
			$config->merge(array('config' => array(
				'home' => dirname( $this->get_composer_json_path() ),
			)));
			$config->setConfigSource( new JsonConfigSource( $this->get_composer_json() ) );

			$package_index = new ComposerRepository( array( 'url' => self::PACKAGE_INDEX_URL ), new NullIO, $config );
		}

		return $package_index;
	}

	/**
	 * Display a set of packages
	 *
	 * @param array
	 * @param array
	 */
	private function show_packages( $packages, $assoc_args ) {
		$defaults = array(
			'fields' => implode( ',', $this->fields ),
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
		foreach( $repo->getPackages() as $package ) {

			if ( ! $this->is_community_package( $package ) ) {
				continue;
			}

			$installed_packages[] = $package;
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
	 * Regenerate the Composer autoloader
	 */
	private function regenerate_autoloader( $composer ) {
		$composer->getAutoloadGenerator()->dump(
			$composer->getConfig(),
			$composer->getRepositoryManager()->getLocalRepository(),
			$composer->getPackage(),
			$composer->getInstallationManager(),
			'composer'
		);
	}
}

WP_CLI::add_command( 'package', 'Package_Command' );
