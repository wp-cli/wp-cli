<?php

namespace WP_CLI\Bootstrap;

/**
 * Class IncludePackageAutoloader.
 *
 * Loads the main autoloader that comes with the WP_CLI framework.
 *
 * @package WP_CLI\Bootstrap
 */
final class IncludePackageAutoloader extends AutoloaderStep {

	/**
	 * Get the vendor paths to scan for an autoloader.
	 *
	 * @return string[]|false Array of strings with vendor paths, or false to
	 *                        skip.
	 */
	protected function get_vendor_paths() {
		$runner        = new RunnerInstance();
		$skip_packages = $runner()->config['skip-packages'];
		if ( true === $skip_packages ) {
			\WP_CLI::debug( 'Skipped loading packages.', 'bootstrap' );

			return false;
		}

		$vendor_path = $runner()->get_packages_dir_path() . '/vendor';

		if ( is_readable( $vendor_path . '/autoload.php' ) ) {
			\WP_CLI::debug(
				'Loading packages from: ' . $vendor_path . '/autoload.php',
				'bootstrap'
			);

			return array(
				$vendor_path,
			);
		}

		return false;
	}

	/**
	 * Handle the failure to find an autoloader.
	 *
	 * @return void
	 */
	protected function handle_failure() {
		\WP_CLI::debug( 'No package autoload found to load.', 'bootstrap' );
	}
}
