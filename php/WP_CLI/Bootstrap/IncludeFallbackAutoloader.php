<?php

namespace WP_CLI\Bootstrap;

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
		$autoloader_paths = array(
			WP_CLI_VENDOR_DIR . '/autoload.php',
		);

		if ( $custom_vendor = $this->get_custom_vendor_folder() ) {
			array_unshift(
				$autoloader_paths,
				WP_CLI_ROOT . '/../../../' . $custom_vendor . '/autoload.php'
			);
		}

		return $autoloader_paths;
	}
}
