<?php

namespace WP_CLI\Bootstrap;

/**
 * Class IncludePHARAutoloader.
 *
 * Loads the main autoloader that comes with the WP_CLI framework.
 *
 * @package WP_CLI\Bootstrap
 */
final class IncludePHARAutoloader extends AutoloaderStep {

	/**
	 * Get the vendor paths to scan for an autoloader.
	 *
	 * @return string[]|false Array of strings with vendor paths, or false to
	 *                        skip.
	 */
	protected function get_vendor_paths() {
		if ( ! $this->is_inside_phar() ) {
			// Skip PHAR autoloader.
			return false;
		}

		return array(
			WP_CLI_ROOT . '/vendor',
		);
	}
}
