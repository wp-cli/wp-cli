<?php

namespace WP_CLI\Bootstrap;

use WP_CLI;

/**
 * Class IncludePackageAutoloader.
 *
 * Loads the package autoloader that includes all the external packages.
 *
 * @package WP_CLI\Bootstrap
 */
final class IncludePackageAutoloader extends AutoloaderStep {

	/**
	 * Get the autoloader paths to scan for an autoloader.
	 *
	 * @return string[]|false Array of strings with autoloader paths, or false
	 *                        to skip.
	 */
	protected function get_autoloader_paths() {
		if ( $this->state->getValue( BootstrapState::IS_PROTECTED_COMMAND, false ) ) {
			return false;
		}

		$runner        = new RunnerInstance();
		$skip_packages = $runner()->config['skip-packages'];
		if ( true === $skip_packages ) {
			WP_CLI::debug( 'Skipped loading packages.', 'bootstrap' );

			return false;
		}

		$autoloader_path = $runner()->get_packages_dir_path() . 'vendor/autoload.php';

		if ( is_readable( $autoloader_path ) ) {
			WP_CLI::debug(
				'Loading packages from: ' . $autoloader_path,
				'bootstrap'
			);

			return [
				$autoloader_path,
			];
		}

		return false;
	}

	/**
	 * Handle the failure to find an autoloader.
	 *
	 * @return void
	 */
	protected function handle_failure() {
		WP_CLI::debug( 'No package autoload found to load.', 'bootstrap' );
	}
}
