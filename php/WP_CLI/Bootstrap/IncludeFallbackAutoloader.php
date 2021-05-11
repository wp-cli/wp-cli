<?php

namespace WP_CLI\Bootstrap;

use WP_CLI;

/**
 * Class IncludeFallbackAutoloader.
 *
 * Loads the fallback autoloader that is provided through the `composer.json`
 * file.
 *
 * @package WP_CLI\Bootstrap
 */
final class IncludeFallbackAutoloader extends AutoloaderStep {

	/**
	 * Get the autoloader paths to scan for an autoloader.
	 *
	 * @return string[]|false Array of strings with autoloader paths, or false
	 *                        to skip.
	 */
	protected function get_autoloader_paths() {
		$autoloader_paths = [
			WP_CLI_VENDOR_DIR . '/autoload.php',
		];

		$custom_vendor = $this->get_custom_vendor_folder();
		if ( false !== $custom_vendor ) {
			array_unshift(
				$autoloader_paths,
				WP_CLI_ROOT . '/../../../' . $custom_vendor . '/autoload.php'
			);
		}

		WP_CLI::debug(
			sprintf(
				'Fallback autoloader paths: %s',
				implode( ', ', $autoloader_paths )
			),
			'bootstrap'
		);

		return $autoloader_paths;
	}
}
