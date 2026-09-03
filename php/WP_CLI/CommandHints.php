<?php

namespace WP_CLI;

use WP_CLI;

/**
 * Provides guidance for commands that are not registered.
 *
 * Not every WP-CLI installation offers the same set of commands. The `package`
 * command, for example, ships with the Phar but is an optional dependency of
 * Composer-based installations. Without further guidance, a user of such an
 * installation only learns that the command is "not a registered wp command",
 * which says nothing about why it is missing or how to get it.
 *
 * Composer packages can therefore declare a hint for the commands they know
 * about through the `extra.command-hints` section of their `composer.json`:
 *
 * ```
 * "extra": {
 *     "command-hints": {
 *         "package": "The 'package' command is only bundled with the Phar."
 *     }
 * }
 * ```
 *
 * The hint is appended to the error whenever the command in question turns out
 * to be unavailable. Hints for commands that are registered are never shown.
 *
 * @package WP_CLI
 */
final class CommandHints {

	/**
	 * Key within a package's `extra` section that holds its hints.
	 *
	 * @var string
	 */
	const EXTRA_KEY = 'command-hints';

	/**
	 * Collected hints, keyed by full command name.
	 *
	 * Null for as long as the hints have not been collected yet.
	 *
	 * @var array<string, string>|null
	 */
	private static $hints = null;

	/**
	 * Get the hint for a command that is not registered.
	 *
	 * @param string $command Full name of the command that was not found.
	 * @return string Hint for the user, or an empty string if there is none.
	 */
	public static function get_hint( $command ) {
		$hints = self::get_hints();

		$hint = array_key_exists( $command, $hints ) ? $hints[ $command ] : '';

		/**
		 * Filters the hint shown for a command that is not registered.
		 *
		 * @param string $hint    Hint to show, or an empty string for none.
		 * @param string $command Full name of the command that was not found.
		 */
		$hint = WP_CLI::do_hook( 'unregistered_command_hint', $hint, $command );

		return is_string( $hint ) ? trim( $hint ) : '';
	}

	/**
	 * Get the hints declared by the currently installed Composer packages.
	 *
	 * @return array<string, string> Map of full command name to hint.
	 */
	private static function get_hints() {
		if ( null === self::$hints ) {
			self::$hints = defined( 'WP_CLI_VENDOR_DIR' )
				? self::get_hints_from_vendor_dir( WP_CLI_VENDOR_DIR )
				: [];
		}

		return self::$hints;
	}

	/**
	 * Collect the hints declared by the packages within a vendor directory.
	 *
	 * @param string $vendor_dir Path to the Composer vendor directory.
	 * @return array<string, string> Map of full command name to hint.
	 */
	private static function get_hints_from_vendor_dir( $vendor_dir ) {
		$vendor_dir = rtrim( $vendor_dir, '/\\' );

		$hints = [];

		foreach ( self::read_installed_packages( $vendor_dir . '/composer/installed.json' ) as $package ) {
			$hints = array_merge( $hints, self::extract_hints( $package ) );
		}

		// The root package is absent from `installed.json`, so it is read separately.
		$root_package = self::read_json( dirname( $vendor_dir ) . '/composer.json' );

		return array_merge( $hints, self::extract_hints( $root_package ) );
	}

	/**
	 * Read the package definitions out of Composer's `installed.json`.
	 *
	 * @param string $path Path to the `installed.json` file.
	 * @return array<array<mixed>> List of package definitions.
	 */
	private static function read_installed_packages( $path ) {
		$data = self::read_json( $path );

		if ( ! isset( $data['packages'] ) || ! is_array( $data['packages'] ) ) {
			return [];
		}

		$packages = [];

		foreach ( $data['packages'] as $package ) {
			if ( is_array( $package ) ) {
				$packages[] = $package;
			}
		}

		return $packages;
	}

	/**
	 * Extract the hints declared by a single package definition.
	 *
	 * @param array<mixed> $package Decoded `composer.json` or `installed.json` entry.
	 * @return array<string, string> Map of full command name to hint.
	 */
	private static function extract_hints( $package ) {
		$extra = isset( $package['extra'] ) && is_array( $package['extra'] ) ? $package['extra'] : [];

		if ( ! isset( $extra[ self::EXTRA_KEY ] ) || ! is_array( $extra[ self::EXTRA_KEY ] ) ) {
			return [];
		}

		$hints = [];

		foreach ( $extra[ self::EXTRA_KEY ] as $command => $hint ) {
			if ( ! is_string( $command ) || ! is_string( $hint ) ) {
				continue;
			}

			$command = trim( $command );
			$hint    = trim( $hint );

			if ( '' === $command || '' === $hint ) {
				continue;
			}

			$hints[ $command ] = $hint;
		}

		return $hints;
	}

	/**
	 * Read and decode a JSON file.
	 *
	 * @param string $path Path to the file.
	 * @return array<mixed> Decoded data, or an empty array if it could not be read.
	 */
	private static function read_json( $path ) {
		if ( ! is_file( $path ) || ! is_readable( $path ) ) {
			return [];
		}

		$contents = file_get_contents( $path );

		if ( false === $contents ) {
			return [];
		}

		$data = json_decode( $contents, true );

		return is_array( $data ) ? $data : [];
	}
}
