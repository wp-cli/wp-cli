<?php

namespace WP_CLI\Bootstrap;

/**
 * Class IncludeBundledAutoloader.
 *
 * Loads the bundled autoloader that is provided through the `composer.json`
 * file.
 *
 * This only contains classes for the commands.
 *
 * @package WP_CLI\Bootstrap
 */
final class IncludeBundledAutoloader extends AutoloaderStep {

	/**
	 * Get the autoloader paths to scan for an autoloader.
	 *
	 * @return string[]|false Array of strings with autoloader paths, or false
	 *                        to skip.
	 */
	protected function get_autoloader_paths() {
		$autoloader_paths = array(
			// Part of a larger project / installed via Composer (preferred).
			WP_CLI_ROOT . '/../../../vendor/autoload_commands.php',
			// Top-level project / installed as Git clone.
			WP_CLI_ROOT . '/vendor/autoload_commands.php',
		);

		$maybe_composer_json = WP_CLI_ROOT . '/../../../composer.json';
		if ( ! is_readable( $maybe_composer_json ) ) {
			return $autoloader_paths;
		}

		$composer = json_decode( file_get_contents( $maybe_composer_json ) );

		if ( ! empty( $composer->config )
			&& ! empty( $composer->config->{'vendor-dir'} )
		) {
			array_unshift(
				$autoloader_paths,
				WP_CLI_ROOT . '/../../../' . $composer->config->{'vendor-dir'} . '/autoload_commands.php'
			);
		}

		return $autoloader_paths;
	}
}
