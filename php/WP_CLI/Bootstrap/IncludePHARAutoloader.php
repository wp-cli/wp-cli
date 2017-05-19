<?php

namespace WP_CLI\Bootstrap;

/**
 * Class IncludePHARAutoloader.
 *
 * Loads the autoloader for the PHAR bundle.
 *
 * @package WP_CLI\Bootstrap
 */
final class IncludePHARAutoloader extends AutoloaderStep {

	/**
	 * Get the autoloader paths to scan for an autoloader.
	 *
	 * @return string[]|false Array of strings with autoloader paths, or false
	 *                        to skip.
	 */
	protected function get_autoloader_paths() {
		if ( ! $this->is_inside_phar() ) {
			// Skip PHAR autoloader.
			return false;
		}

		return array(
			WP_CLI_VENDOR_DIR . '/autoload_framework.php',
		);
	}

	/**
	 * Check whether the current execution is run from within a Phar file.
	 *
	 * @return bool
	 */
	private function is_inside_phar() {
		return 0 === strpos( WP_CLI_ROOT, 'phar://' );
	}
}
