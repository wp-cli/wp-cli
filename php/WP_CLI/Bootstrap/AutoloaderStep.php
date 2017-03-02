<?php

namespace WP_CLI\Bootstrap;

/**
 * Abstract class AutoloaderStep.
 *
 * Abstract base class for steps that include an autoloader.
 *
 * @package WP_CLI\Bootstrap
 */
abstract class AutoloaderStep implements BootstrapStep {

	/**
	 * Process this single bootstrapping step.
	 *
	 * @return void
	 */
	public function process() {
		$found_autoloader = false;
		$vendor_paths     = $this->get_vendor_paths();

		if ( false === $vendor_paths ) {
			// Skip this autoloading step.
			return;
		}

		foreach ( $vendor_paths as $vendor_path ) {
			if ( is_readable( $vendor_path . '/autoload.php' ) ) {
				try {
					require $vendor_path . '/autoload.php';
					$found_autoloader = true;
				} catch ( \Exception $exception ) {
					\WP_CLI::warning(
						"Failed to load autoloader {$vendor_path}/autoload.php. Reason: " . $exception->getMessage(),
						'bootstrap'
					);
				}
			}
		}

		if ( ! $found_autoloader ) {
			$this->handle_failure();
		}
	}

	/**
	 * Check whether WP-CLI is being run from within a PHAR bundle.
	 *
	 * @return bool
	 */
	protected function is_inside_phar() {
		return 0 === strpos( WP_CLI_ROOT, 'phar://' );
	}

	/**
	 * Handle the failure to find an autoloader.
	 *
	 * @return void
	 */
	protected function handle_failure() { }

	/**
	 * Get the vendor paths to scan for an autoloader.
	 *
	 * @return string[]|false Array of strings with vendor paths, or false to
	 *                        skip.
	 */
	abstract protected function get_vendor_paths();
}
