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
	 * Store state for subclasses to have access.
	 *
	 * @var BootstrapState
	 */
	protected $state;

	/**
	 * Process this single bootstrapping step.
	 *
	 * @param BootstrapState $state Contextual state to pass into the step.
	 *
	 * @return BootstrapState Modified state to pass to the next step.
	 */
	public function process( BootstrapState $state ) {
		$this->state = $state;

		$found_autoloader = false;
		$autoloader_paths = $this->get_autoloader_paths();

		if ( false === $autoloader_paths ) {
			// Skip this autoloading step.
			return $state;
		}

		foreach ( $autoloader_paths as $autoloader_path ) {
			if ( is_readable( $autoloader_path ) ) {
				try {
					require $autoloader_path;
					$found_autoloader = true;
				} catch ( \Exception $exception ) {
					\WP_CLI::warning(
						"Failed to load autoloader '{$autoloader_path}'. Reason: "
						. $exception->getMessage(),
						'bootstrap'
					);
				}
			}
		}

		if ( ! $found_autoloader ) {
			$this->handle_failure();
		}

		return $this->state;
	}

	/**
	 * Handle the failure to find an autoloader.
	 *
	 * @return void
	 */
	protected function handle_failure() { }

	/**
	 * Get the autoloader paths to scan for an autoloader.
	 *
	 * @return string[]|false Array of strings with autoloader paths, or false
	 *                        to skip.
	 */
	abstract protected function get_autoloader_paths();
}
