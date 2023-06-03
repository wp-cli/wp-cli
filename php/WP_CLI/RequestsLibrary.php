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
	 * @throws RuntimeException if the class name is not set.
	 */
	public static function get_class_name() {
		return self::$class_name;
	}

	/**
	 * Set the class name for the library.
	 *
	 * @param string $class_name The class name to set.
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
	 * @param Exception $exception Exception to check.
	 * @return bool Whether the provided exception was issued by the Requests library.
	 */
	public static function is_requests_exception( Exception $exception ) {
		return is_a( $exception, '\Requests_Exception' )
			|| is_a( $exception, '\WpOrg\Requests\Exception' );
	}

	/**
	 * Register the autoloader for the Requests library.
	 *
	 * This checks for the detected setup and register the corresponding
	 * autoloader if it is still needed.
	 */
	public static function register_autoloader() {
		$includes_path = defined( 'WPINC' ) ? WPINC : 'wp-includes';

		if ( self::is_v1() && ! class_exists( self::CLASS_NAME_V1 ) ) {
			if ( self::is_core() ) {
				require_once ABSPATH . $includes_path . '/class-requests.php';
			} else {
				require_once WP_CLI_VENDOR_DIR . '/rmccue/requests/library/Requests.php';
			}
			\Requests::register_autoloader();
		}

		if ( self::is_v2() && ! class_exists( self::CLASS_NAME_V2 ) ) {
			if ( self::is_core() ) {
				require_once ABSPATH . $includes_path . '/Requests/Autoload.php';
			} else {
				self::maybe_define_wp_cli_root();
				if ( file_exists( WP_CLI_ROOT . '/bundle/rmccue/requests/src/Autoload.php' ) ) {
					require_once WP_CLI_ROOT . '/bundle/rmccue/requests/src/Autoload.php';
				} else {
					require_once WP_CLI_VENDOR_DIR . '/rmccue/requests/src/Autoload.php';
				}
			}
			\WpOrg\Requests\Autoload::register();
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
	 */
	private static function maybe_define_wp_cli_root() {
		if ( ! defined( 'WP_CLI_ROOT' ) ) {
			define( 'WP_CLI_ROOT', dirname( dirname( __DIR__ ) ) );
		}
	}
}
