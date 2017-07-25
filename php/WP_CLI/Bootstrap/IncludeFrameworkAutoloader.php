<?php

namespace WP_CLI\Bootstrap;

/**
 * Class IncludeFrameworkAutoloader.
 *
 * Loads the framework autoloader that is provided through the `composer.json`
 * file.
 *
 * This only contains classes for the framework.
 *
 * @package WP_CLI\Bootstrap
 */
final class IncludeFrameworkAutoloader extends AutoloaderStep {

	/**
	 * Get the autoloader paths to scan for an autoloader.
	 *
	 * @return string[]|false Array of strings with autoloader paths, or false
	 *                        to skip.
	 */
	protected function get_autoloader_paths() {
		$autoloader_paths = array(
			WP_CLI_VENDOR_DIR . '/autoload_framework.php',
		);

		if ( $custom_vendor = $this->get_custom_vendor_folder() ) {
			array_unshift(
				$autoloader_paths,
				WP_CLI_ROOT . '/../../../' . $custom_vendor . '/autoload_framework.php'
			);
		}

		return $autoloader_paths;
	}

	/**
	 * Handle the failure to find an autoloader.
	 *
	 * @return void
	 */
	protected function handle_failure() {
		fputs(
			STDERR,
			"Internal error: Can't find Composer autoloader.\nTry running: composer install\n"
		);
		exit( 3 );
	}
}
