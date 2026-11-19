<?php

namespace WP_CLI;

use Exception;
use RuntimeException;
use WP_CLI;

/**
 * Class RequestsLibrary.
 *
 * A class to manage the version and source of the Requests library used by WP-CLI.
 */
final class RequestsLibrary {

	/**
	 * Version 1 of the Requests library.
	 *
	 * @var string
	 */
	const VERSION_V1 = 'v1';

	/**
	 * Version 2 of the Requests library.
	 *
	 * @var string
	 */
	const VERSION_V2 = 'v2';

	/**
	 * Array of valid versions for the Requests library.
	 *
	 * @var array<string>
	 */
	const VALID_VERSIONS = [ self::VERSION_V1, self::VERSION_V2 ];

	/**
	 * Requests library bundled with WordPress Core being used.
	 *
	 * @var string
	 */
	const SOURCE_WP_CORE = 'wp-core';

	/**
	 * Requests library bundled with WP-CLI being used.
	 *
	 * @var string
	 */
	const SOURCE_WP_CLI = 'wp-cli';

	/**
	 * Array of valid source for the Requests library.
	 *
	 * @var array<string>
	 */
	const VALID_SOURCES = [ self::SOURCE_WP_CORE, self::SOURCE_WP_CLI ];

	/**
	 * Class name of the Requests main class for v1.
	 *
	 * @var string
	 */
	const CLASS_NAME_V1 = '\Requests';

	/**
	 * Class name of the Requests main class for v2.
	 *
	 * @var string
	 */
	const CLASS_NAME_V2 = '\WpOrg\Requests\Requests';

	/**
	 * Version of the Requests library being used.
	 *
	 * @var string
	 */
	private static $version = self::VERSION_V2;

	/**
	 * Source of the Requests library being used.
	 *
	 * @var string
	 */
	private static $source = self::SOURCE_WP_CLI;

	/**
	 * Class name of the Requests library being used.
	 *
	 * @var string
	 */
	private static $class_name = self::CLASS_NAME_V2;

	/**
	 * Deferred SPL autoloader callback for loading bundled Requests on demand.
	 *
	 * @var callable|null
	 */
	private static $deferred_autoloader = null;

	/**
	 * Check if the current version is v1.
	 *
	 * @return bool Whether the current version is v1.
	 */
	public static function is_v1() {
		return self::get_version() === self::VERSION_V1;
	}

	/**
	 * Check if the current version is v2.
	 *
	 * @return bool Whether the current version is v2.
	 */
	public static function is_v2() {
		return self::get_version() === self::VERSION_V2;
	}

	/**
	 * Check if the current source for the Requests library is WordPress Core.
	 *
	 * @return bool Whether the current source is WordPress Core.
	 */
	public static function is_core() {
		return self::get_source() === self::SOURCE_WP_CORE;
	}

	/**
	 * Check if the current source for the Requests library is WP-CLI.
	 *
	 * @return bool Whether the current source is WP-CLI.
	 */
	public static function is_cli() {
		return self::get_source() === self::SOURCE_WP_CLI;
	}

	/**
	 * Get the current version.
	 *
	 * @return string The current version.
	 */
	public static function get_version() {
		return self::$version;
	}

	/**
	 * Set the version of the library.
	 *
	 * @param string $version The version to set.
	 * @throws RuntimeException if the version is invalid.
	 * @return void
	 */
	public static function set_version( $version ) {
		if ( ! is_string( $version ) ) {
			throw new RuntimeException( 'RequestsLibrary::$version must be a string.' );
		}

		if ( ! in_array( $version, self::VALID_VERSIONS, true ) ) {
			throw new RuntimeException(
				sprintf(
					'Invalid RequestsLibrary::$version, must be one of: %s.',
					implode( ', ', self::VALID_VERSIONS )
				)
			);
		}

		WP_CLI::debug( 'Setting RequestsLibrary::$version to ' . $version, 'bootstrap' );

		self::$version = $version;
	}

	/**
	 * Get the current class name.
	 *
	 * @return string The current class name.
	 */
	public static function get_class_name() {
		return self::$class_name;
	}

	/**
	 * Set the class name for the library.
	 *
	 * @param string $class_name The class name to set.
	 * @return void
	 */
	public static function set_class_name( $class_name ) {
		if ( ! is_string( $class_name ) ) {
			throw new RuntimeException( 'RequestsLibrary::$class_name must be a string.' );
		}

		WP_CLI::debug( 'Setting RequestsLibrary::$class_name to ' . $class_name, 'bootstrap' );

		self::$class_name = $class_name;
	}

	/**
	 * Get the current source.
	 *
	 * @return string The current source.
	 */
	public static function get_source() {
		return self::$source;
	}

	/**
	 * Set the source of the library.
	 *
	 * @param string $source The source to set.
	 * @throws RuntimeException if the source is invalid.
	 * @return void
	 */
	public static function set_source( $source ) {
		if ( ! is_string( $source ) ) {
			throw new RuntimeException( 'RequestsLibrary::$source must be a string.' );
		}

		if ( ! in_array( $source, self::VALID_SOURCES, true ) ) {
			throw new RuntimeException(
				sprintf(
					'Invalid RequestsLibrary::$source, must be one of: %s.',
					implode( ', ', self::VALID_SOURCES )
				)
			);
		}

		WP_CLI::debug( 'Setting RequestsLibrary::$source to ' . $source, 'bootstrap' );

		self::$source = $source;
	}

	/**
	 * Check if a given exception was issued by the Requests library.
	 *
	 * This is used because we cannot easily catch multiple different exception
	 * classes with PHP 5.6. Because of that, we catch generic exceptions, check if
	 * they match the Requests library, and re-throw them if they do not.
	 *
	 * @param \Exception|\Requests_Exception|\WpOrg\Requests\Exception $exception Exception to check.
	 * @return bool Whether the provided exception was issued by the Requests library.
	 *
	 * @phpstan-assert-if-true \Requests_Exception|\WpOrg\Requests\Exception $exception
	 */
	public static function is_requests_exception( Exception $exception ) {
		return is_a( $exception, '\Requests_Exception' )
			|| is_a( $exception, '\WpOrg\Requests\Exception' );
	}

	/**
	 * Check whether a Requests library implementation has already been loaded.
	 *
	 * @return bool
	 */
	private static function is_loaded(): bool {
		return class_exists( self::CLASS_NAME_V2, false )
			|| class_exists( self::CLASS_NAME_V1, false )
			|| class_exists( '\\WpOrg\\Requests\\Autoload', false );
	}

	/**
	 * Register a deferred SPL autoloader that loads WP-CLI's bundled Requests
	 * library on demand if a `WpOrg\Requests\*` class is referenced before
	 * WordPress loads.
	 *
	 * @return void
	 */
	public static function register_deferred_autoloader(): void {
		if ( self::is_loaded() || null !== self::$deferred_autoloader ) {
			return;
		}

		self::$deferred_autoloader = static function ( $class_name ) {
			if ( 0 !== strpos( $class_name, 'WpOrg\\Requests\\' ) ) {
				return;
			}

			self::register_bundled_autoloader();

			if ( class_exists( '\\WpOrg\\Requests\\Autoload', false ) ) {
				\WpOrg\Requests\Autoload::load( $class_name );
			}
		};

		spl_autoload_register( self::$deferred_autoloader );
	}

	/**
	 * Unregister the deferred SPL autoloader if it is currently registered.
	 *
	 * @return void
	 */
	public static function unregister_deferred_autoloader(): void {
		if ( null !== self::$deferred_autoloader ) {
			spl_autoload_unregister( self::$deferred_autoloader );
			self::$deferred_autoloader = null;
		}
	}

	/**
	 * Load and register the Requests autoloader bundled with WP-CLI.
	 *
	 * @return void
	 */
	public static function register_bundled_autoloader(): void {
		self::unregister_deferred_autoloader();

		if ( self::is_loaded() ) {
			return;
		}

		self::maybe_define_wp_cli_root();

		if ( file_exists( WP_CLI_ROOT . '/bundle/rmccue/requests/src/Autoload.php' ) ) {
			require_once WP_CLI_ROOT . '/bundle/rmccue/requests/src/Autoload.php';
		} else {
			require_once WP_CLI_VENDOR_DIR . '/rmccue/requests/src/Autoload.php';
		}

		// @phpstan-ignore staticMethod.internal
		\WpOrg\Requests\Autoload::register();

		self::set_version( self::VERSION_V2 );
		self::set_source( self::SOURCE_WP_CLI );
		self::set_class_name( self::CLASS_NAME_V2 );
	}

	/**
	 * Load and register the Requests autoloader from a WordPress Core installation.
	 *
	 * Should only be called once WP-CLI has committed to loading WordPress from
	 * the given directory.
	 *
	 * @param string $wp_root Absolute path to the WordPress root directory.
	 * @return bool Whether a WordPress Core Requests library was loaded.
	 */
	public static function register_wp_core_autoloader( string $wp_root ): bool {
		self::unregister_deferred_autoloader();

		if ( self::is_loaded() ) {
			return false;
		}

		$wp_root       = rtrim( $wp_root, '/\\' );
		$includes_path = defined( 'WPINC' ) ? WPINC : 'wp-includes';

		// First try to detect a newer Requests version bundled with WordPress.
		if ( file_exists( $wp_root . '/' . $includes_path . '/Requests/src/Autoload.php' ) ) {
			require_once $wp_root . '/' . $includes_path . '/Requests/src/Autoload.php';

			if ( class_exists( '\\WpOrg\\Requests\\Autoload', false ) ) {
				// @phpstan-ignore staticMethod.internal
				\WpOrg\Requests\Autoload::register();
				self::set_version( self::VERSION_V2 );
				self::set_source( self::SOURCE_WP_CORE );
				self::set_class_name( self::CLASS_NAME_V2 );
				return true;
			}
		}

		// Then see if we can detect the older version bundled with WordPress.
		if ( file_exists( $wp_root . '/' . $includes_path . '/class-requests.php' ) ) {
			require_once $wp_root . '/' . $includes_path . '/class-requests.php';

			if ( class_exists( '\\Requests', false ) ) {
				// @phpstan-ignore staticMethod.deprecatedClass
				\Requests::register_autoloader();
				self::set_version( self::VERSION_V1 );
				self::set_source( self::SOURCE_WP_CORE );
				self::set_class_name( self::CLASS_NAME_V1 );
				return true;
			}
		}

		return false;
	}

	/**
	 * Register the autoloader for the Requests library.
	 *
	 * This checks for the detected setup and register the corresponding
	 * autoloader if it is still needed.
	 *
	 * @return void
	 */
	public static function register_autoloader() {
		self::unregister_deferred_autoloader();

		$includes_path = defined( 'WPINC' ) ? WPINC : 'wp-includes';

		if ( self::is_v1() && ! class_exists( self::CLASS_NAME_V1 ) ) {
			if ( self::is_core() ) {
				require_once ABSPATH . $includes_path . '/class-requests.php';
			} else {
				require_once WP_CLI_VENDOR_DIR . '/rmccue/requests/library/Requests.php';
			}
			// @phpstan-ignore staticMethod.deprecatedClass
			\Requests::register_autoloader();
		}

		if ( self::is_v2() && ! class_exists( self::CLASS_NAME_V2 ) ) {
			if ( self::is_core() ) {
				if ( ! class_exists( '\\WpOrg\\Requests\\Autoload', false ) ) {
					require_once ABSPATH . $includes_path . '/Requests/src/Autoload.php';
				}
				// @phpstan-ignore staticMethod.internal
				\WpOrg\Requests\Autoload::register();
			} else {
				self::register_bundled_autoloader();
				if ( class_exists( '\\WpOrg\\Requests\\Autoload', false ) ) {
					// @phpstan-ignore staticMethod.internal
					\WpOrg\Requests\Autoload::register();
				}
			}
		}
	}

	/**
	 * Get the path to the bundled certificate.
	 *
	 * @return string The path to the bundled certificate.
	 */
	public static function get_bundled_certificate_path() {
		if ( self::is_core() ) {
			$includes_path = defined( 'WPINC' ) ? WPINC : 'wp-includes';
			return ABSPATH . $includes_path . '/certificates/ca-bundle.crt';
		} elseif ( self::is_v1() ) {
			return WP_CLI_VENDOR_DIR . '/rmccue/requests/library/Requests/Transport/cacert.pem';
		} else {
			self::maybe_define_wp_cli_root();
			if ( file_exists( WP_CLI_ROOT . '/bundle/rmccue/requests/certificates/cacert.pem' ) ) {
				return WP_CLI_ROOT . '/bundle/rmccue/requests/certificates/cacert.pem';
			}
			return WP_CLI_VENDOR_DIR . '/rmccue/requests/certificates/cacert.pem';
		}
	}

	/**
	 * Define WP_CLI_ROOT if it is not already defined.
	 *
	 * @return void
	 */
	private static function maybe_define_wp_cli_root() {
		if ( ! defined( 'WP_CLI_ROOT' ) ) {
			define( 'WP_CLI_ROOT', dirname( dirname( __DIR__ ) ) );
		}
	}
}
